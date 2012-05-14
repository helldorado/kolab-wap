<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab Web Admin Panel                           |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 +--------------------------------------------------------------------------+
*/

/**
 * Main controller class to serve the Kolab Admin API
 */
class kolab_api_controller
{
    public $output;

    private $uid;
    private $request  = array();
    private $services = array();
    private $domains  = array('localhost.localdomain');
    private static $translation = array();

    public function __construct()
    {
        $this->output = new kolab_json_output();

        if (!empty($_GET['service'])) {
            if (!empty($_GET['method'])) {
                $this->request = array(
                    'service' => $_GET['service'],
                    'method'  => $_GET['method']
                );
            }
            else {
                throw new Exception("Unknown method", 400);
            }
        }
        else {
            throw new Exception("Unknown service", 400);
        }

        // TODO: register services based on config or whatsoever
        $this->add_service('domain', 'kolab_api_service_domain');
        $this->add_service('domains', 'kolab_api_service_domains');
        $this->add_service('form_value', 'kolab_api_service_form_value');
        $this->add_service('group_types', 'kolab_api_service_group_types');
        $this->add_service('group', 'kolab_api_service_group');
        $this->add_service('groups', 'kolab_api_service_groups');
        $this->add_service('roles', 'kolab_api_service_roles');
        $this->add_service('user_types', 'kolab_api_service_user_types');
        $this->add_service('user', 'kolab_api_service_user');
        $this->add_service('users', 'kolab_api_service_users');
    }

    /**
     * Register a class that serves a particular backend service
     */
    public function add_service($service, $handler)
    {
        if ($this->services[$service]) {
            error_log("Service $service is already registered.");
            return false;
        }

        $this->services[$service] = $handler;
    }

    /**
     * Getter for a certain service object
     */
    public function get_service($service)
    {
        // we are the system!
        if ($service == 'system') {
            return $this;
        }

        if ($handler = $this->services[$service]) {
            if (is_string($handler)) {
                $handler = $this->services[$service] = new $handler($this);
            }

            if (is_a($handler, 'kolab_api_service')) {
                return $handler;
            }
        }

        error_log("Unknown service $service");

        throw new Exception("Unknown service", 400);
    }


    /**
     * Getter for the authenticated user (ID)
     */
    public function get_uid()
    {
        return $this->uid;
    }


    /**
     * Process the request and dispatch it to the requested service
     */
    public function dispatch($postdata)
    {
        $config = Conf::get_instance();

        // Use proxy
        if (empty($_GET['proxy']) && ($url = $config->get('kolab_wap', 'api_url'))) {
            $this->proxy($postdata, $url);
            return;
        }

        $service = $this->request['service'];
        $method  = $this->request['method'];
        $postdata = @json_decode($postdata, true);

        console("Calling method " . $method . " on service " . $service);

        // validate user session
        if ($service != 'system' || $method != 'authenticate') {
            if (!$this->session_validate($postdata)) {
                throw new Exception("Invalid session", 403);
            }
        }

        // init localization
        $this->locale_init();

        // call service method
        $service_handler = $this->get_service($service);

        // get only public methods
        $service_methods = get_class_methods($service_handler);

        if (in_array($method, $service_methods)) {
            $result = $service_handler->$method($_GET, $postdata);
        }
        else if (in_array($service . "_" . $method, $service_methods)) {
            $call_method = $service . "_" . $method;
            $result = $service_handler->$call_method($_GET, $postdata);
        }
        else {
            throw new Exception("Unknown method", 405);
        }

        // send response
        if ($result !== false) {
            $this->output->success($result);
        }
        else {
            $this->output->error("Internal error", 500);
        }
    }


    /**
     * Proxies request to the API host
     */
    private function proxy($postdata, $url)
    {
        $service = $this->request['service'];
        $method  = $this->request['method'];
        $url     = rtrim($url, '/') . '/' . $service . '.' . $method;

        console("Proxying " . $url);

        $request = new HTTP_Request2();
        $url     = new Net_URL2($url);
        $method  = strtoupper($_SERVER['REQUEST_METHOD']);
        $get     = array('proxy' => 1); // Prevent from infinite redirect

        $request->setMethod($method == 'GET' ? HTTP_Request2::METHOD_GET : HTTP_Request2::METHOD_POST);
        $request->setHeader('X-Session-Token', kolab_utils::get_request_header('X-Session-Token'));

        if ($method == 'GET') {
            parse_str($_SERVER['QUERY_STRING'], $query);
            unset($query['service']);
            unset($query['method']);

            $query = array_map('urldecode', $query);
            $get   = array_merge($query, $get);
        }
        else {
            $request->setBody($postdata);
        }

        try {
            $url->setQueryVariables($get);
            $request->setUrl($url);
            $response = $request->send();
        }
        catch (Exception $e) {
            $this->output->error("Internal error", 500);
        }

        try {
            $body = $response->getBody();
        }
        catch (Exception $e) {
            $this->output->error("Internal error", 500);
        }

        header("Content-Type: application/json");
        echo $body;
        exit;
    }


    /**
     * Validate the submitted session token
     */
    private function session_validate($postdata)
    {
        if (!empty($postdata['session_token'])) {
            $sess_id = $postdata['session_token'];
        }
        else {
            $sess_id = kolab_utils::get_request_header('X-Session-Token');
        }

        if (empty($sess_id)) {
             return false;
        }

        session_id($sess_id);
        session_start();

        if (isset($_SESSION['user']) && $_SESSION['user']->authenticated()) {
            return true;
        }

        return false;
    }


    /* ========  system.* method handlers  ======== */


    /**
     * Authenticate a user with the given credentials
     *
     * @param array GET request parameters
     * @param array POST data
     *
     * @param array|false Authentication result
     */
    private function authenticate($request, $postdata)
    {
        $valid = false;

        // destroy old session
        if ($this->session_validate($postdata)) {
            session_destroy();
        }

        session_start();

        $_SESSION['user'] = new User();
        $valid = $_SESSION['user']->authenticate($postdata['username'], $postdata['password']);

        // start new (PHP) session
        if ($valid) {
            $_SESSION['start'] = time();
            return array(
                'user'          => $_SESSION['user']->get_username(),
                'userid'        => $_SESSION['user']->get_userid(),
                'domain'        => $_SESSION['user']->get_domain(),
                'session_token' => session_id(),
            );
        }

        return false;
    }


    /**
     * Provide a list of capabilities the backend provides to the current user
     */
    private function capabilities()
    {
        $auth = Auth::get_instance();
        $this->domains = $auth->list_domains();

        $result = array();

        // Should we have no permissions to list domain name spaces,
        // we should always return our own.
        if (count($this->domains) < 1) {
            $this->domains[] = $_SESSION['user']->get_domain();
        }

        // add capabilities of all registered services
        foreach ($this->domains as $domain) {
            // TODO: 'associateddomain' is very specific to 389ds based deployments, and this
            // is supposed to be very generic.
            $domain_name = is_array($domain) ? $domain['associateddomain'] : $domain;
            // define our very own capabilities
            $actions = array(
                'system.quit'      => array('type' => 'w'),
                'system.configure' => array('type' => 'w'),
            );

            foreach ($this->services as $sname => $handler) {
                $service = $this->get_service($sname);
                foreach ($service->capabilities($domain) as $method => $type) {
                    $actions["$sname.$method"] = array('type' => $type);
                }
            }

            $result[$domain_name] = array('actions' => $actions);
        }

        return array(
            'list'  => $result,
            'count' => count($result),
        );
    }

    private function get_domain() {
        return array('domain' => $_SESSION['user']->get_domain());
    }

    /**
     * End the current user session
     */
    private function quit()
    {
        session_destroy();
        return true;
    }

    private function select_domain($getdata) {
        if (isset($getdata['domain'])) {
            $_SESSION['user']->set_domain($getdata['domain']);
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Configure current user session parameters
     *
     * @param array $request  GET request parameters
     * @param array $postdata POST data
     *
     * @return array|false
     */
    private function configure($request, $postdata)
    {
        $result = array();

        foreach ($postdata as $key => $value) {
            switch ($key) {
            case 'language':
                if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $value)) {
                    $_SESSION['language'] = $value;
                    $result[$key] = $value;
                }
                break;
            }
        }

        return $result;
    }

    /* ========  Utility functions  ======== */


    /**
     * Localization initialization.
     */
    private function locale_init()
    {
        $lang = 'en_US';

        // @TODO: read language of logged user in authenticate?
        if (!empty($_SESSION['language'])) {
            $lang = $_SESSION['language'];
        }

        $LANG = array();
        @include INSTALL_PATH . '/locale/en_US.api.php';

        if ($lang != 'en_US' && file_exists(INSTALL_PATH . "/locale/$lang.api.php")) {
            @include INSTALL_PATH . "/locale/$language.api.php";
        }

        setlocale(LC_ALL, $lang . '.utf8', 'en_US.utf8');

        self::$translation = $LANG;
    }

    /**
     * Returns translation of defined label/message.
     *
     * @return string Translated string.
     */
    public static function translate()
    {
        $args = func_get_args();

        if (is_array($args[0])) {
            $args = $args[0];
        }

        $label = $args[0];

        if (isset(self::$translation[$label])) {
	        $content = trim(self::$translation[$label]);
        }
	    else {
	        $content = $label;
        }

        for ($i = 1, $len = count($args); $i < $len; $i++) {
            $content = str_replace('$'.$i, $args[$i], $content);
        }

        return $content;
    }
}
