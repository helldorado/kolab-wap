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


class kolab_client_task
{
    /**
     * @var kolab_client_output
     */
    protected $output;

    /**
     * @var kolab_client_api
     */
    protected $api;

    /**
     * @var Conf
     */
    protected $config;

    protected $ajax_only = false;
    protected $page_title = 'Kolab Web Admin Panel';
    protected $menu = array();
    protected $cache = array();
    protected $devel_mode = false;
    protected $object_types = array('user', 'group', 'role', 'resource', 'sharedfolder', 'domain');

    protected static $translation = array();


    /**
     * Class constructor.
     *
     * @param kolab_client_output $output Optional output object
     */
    public function __construct($output = null)
    {
        $this->config_init();

        $this->devel_mode = $this->config_get('devel_mode', false, Conf::BOOL);

        $this->output_init($output);
        $this->api_init();

        ini_set('session.use_cookies', 'On');
        session_start();

        $this->auth();
    }

    /**
     * Localization initialization.
     */
    protected function locale_init()
    {
        if (!empty(self::$translation)) {
            return;
        }

        $language = $this->get_language();
        $LANG     = array();

        if (!$language) {
            $language = 'en_US';
        }

        @include INSTALL_PATH . '/locale/en_US.php';

        if ($language != 'en_US') {
            @include INSTALL_PATH . "/locale/$language.php";
        }

        setlocale(LC_ALL, $language . '.utf8', 'en_US.utf8');

        self::$translation = $LANG;
    }

    /**
     * Configuration initialization.
     */
    private function config_init()
    {
        $this->config = Conf::get_instance();
    }

    /**
     * Output initialization.
     */
    private function output_init($output = null)
    {
        if ($output) {
            $this->output = $output;
            return;
        }

        $skin = $this->config_get('skin', 'default');
        $this->output = new kolab_client_output($skin);

        // Assign self to template variable
        $this->output->assign('engine', $this);
    }

    /**
     * API initialization
     */
    private function api_init()
    {
        $url = $this->config_get('api_url', '');

        // TODO: Debug logging
        //console($url);

        if (!$url) {
            $url = kolab_utils::https_check() ? 'https://' : 'http://';
            $url .= $_SERVER['SERVER_NAME'];
            $url .= preg_replace('/\/?\?.*$/', '', $_SERVER['REQUEST_URI']);
            $url .= '/api';
        }

        // TODO: Debug logging
        //console($url);

        $this->api = new kolab_client_api($url);
    }

    /**
     * Returns system language (locale) setting.
     *
     * @return string Language code
     */
    private function get_language()
    {
        $aliases = array(
            'de' => 'de_DE',
            'en' => 'en_US',
            'pl' => 'pl_PL',
        );

        // UI language
        $langs = !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        $langs = explode(',', $langs);

        if (!empty($_SESSION['user']) && !empty($_SESSION['user']['language'])) {
            array_unshift($langs, $_SESSION['user']['language']);
        }

        while ($lang = array_shift($langs)) {
            $lang = explode(';', $lang);
            $lang = $lang[0];
            $lang = str_replace('-', '_', $lang);

            if (file_exists(INSTALL_PATH . "/locale/$lang.php")) {
                return $lang;
            }

            if (isset($aliases[$lang]) && ($alias = $aliases[$lang])
                && file_exists(INSTALL_PATH . "/locale/$alias.php")
            ) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * User authentication (and authorization).
     */
    private function auth()
    {
        if (isset($_POST['login'])) {
            $login = $this->get_input('login', 'POST');

            if ($login['username']) {
                $result = $this->api->login($login['username'], $login['password'], $login['domain']);

                if ($token = $result->get('session_token')) {
                    $user = array(
                        'token'  => $token,
                        'id'     => $result->get('userid'),
                        'domain' => $result->get('domain')
                    );

                    $this->api->set_session_token($user['token']);

                    // Find user settings
                    // Don't call API user.info for non-existing users (#1025)
                    if (preg_match('/^cn=([a-z ]+)/i', $login['username'], $m)) {
                        $user['fullname'] = ucwords($m[1]);
                    }
                    else {
                        $res = $this->api->get('user.info', array('id' => $user['id']));
                        $res = $res->get();

                        if (is_array($res) && !empty($res)) {
                            $user['language'] = $res['preferredlanguage'];
                            $user['fullname'] = $res['cn'];
                        }
                    }

                    // Save user data
                    $_SESSION['user'] = $user;

                    if (($language = $this->get_language()) && $language != 'en_US') {
                        $_SESSION['user']['language'] = $language;
                        $session_config['language']   = $language;
                    }

                    // Configure API session
                    if (!empty($session_config)) {
                        $this->api->post('system.configure', null, $session_config);
                    }

                    header('Location: ?');
                    die;
                }
                else {
                    $code  = $result->get_error_code();
                    $str   = $result->get_error_str();
                    $label = 'loginerror';

                    if ($code == kolab_client_api::ERROR_INTERNAL
                        || $code == kolab_client_api::ERROR_CONNECTION
                    ) {
                        $label = 'internalerror';
                        $this->raise_error(500, 'Login failed. ' . $str);
                    }

                    $this->output->command('display_message', $label, 'error');
                }
            }
        }
        else if (!empty($_SESSION['user']) && !empty($_SESSION['user']['token'])) {
            // Validate session
            $timeout = $this->config_get('session_timeout', 3600);
            if ($timeout && $_SESSION['time'] && $_SESSION['time'] < time() - $timeout) {
                $this->action_logout(true);
            }

            // update session time
            $_SESSION['time'] = time();

            // Set API session key
            $this->api->set_session_token($_SESSION['user']['token']);
        }
    }

    /**
     * Main execution.
     */
    public function run()
    {
        // Initialize locales
        $this->locale_init();

        // Session check
        if (empty($_SESSION['user']) || empty($_SESSION['user']['token'])) {
            $this->action_logout();
        }

        // Run security checks
        $this->input_checks();

        $this->action = $this->get_input('action', 'GET');

        if ($this->action) {
            $method = 'action_' . $this->action;
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        else if (method_exists($this, 'action_default')) {
            $this->action_default();
        }
    }

    /**
     * Security checks and input validation.
     */
    public function input_checks()
    {
        $ajax = $this->output->is_ajax();

        // Check AJAX-only tasks
        if ($this->ajax_only && !$ajax) {
            $this->raise_error(500, 'Invalid request type!', null, true);
        }

        // CSRF prevention
        $token  = $ajax ? kolab_utils::get_request_header('X-Session-Token') : $this->get_input('token');
        $task   = $this->get_task();

        if ($task != 'main' && $token != $_SESSION['user']['token']) {
            $this->raise_error(403, 'Invalid request data!', null, true);
        }
    }

    /**
     * Logout action.
     */
    private function action_logout($sess_expired = false, $stop_sess = true)
    {
        // Initialize locales
        $this->locale_init();

        if (!empty($_SESSION['user']) && !empty($_SESSION['user']['token']) && $stop_sess) {
            $this->api->logout();
        }
        $_SESSION = array();

        if ($this->output->is_ajax()) {
            if ($sess_expired) {
                $args = array('error' => 'session.expired');
            }
            $this->output->command('main_logout', $args);

            if ($sess_expired) {
                $this->output->send();
                exit;
            }
        }
        else {
            $this->output->add_translation('loginerror', 'internalerror', 'session.expired');
        }

        if ($sess_expired) {
            $error = 'session.expired';
        }
        else {
            $error = $this->get_input('error', 'GET');
        }

        if ($error) {
            $this->output->command('display_message', $error, 'error', 60000);
        }

        $this->send('login');
        exit;
    }

    /**
     * Error action (with error logging).
     *
     * @param int    $code   Error code
     * @param string $msg    Error message
     * @param array  $args   Optional arguments (type, file, line)
     * @param bool   $output Enable to send output and finish
     */
    public function raise_error($code, $msg, $args = array(), $output = false)
    {
        $log_line = sprintf("%s Error: %s (%s)",
            isset($args['type']) ? $args['type'] : 'PHP',
            $msg . (isset($args['file']) ? sprintf(' in %s on line %d', $args['file'], $args['line']) : ''),
            $_SERVER['REQUEST_METHOD']);

        write_log('errors', $log_line);

        if (!$output) {
            return;
        }

        if ($this->output->is_ajax()) {
            header("HTTP/1.0 $code $msg");
            die;
        }

        $this->output->assign('error_code', $code);
        $this->output->assign('error_message', $msg);
        $this->send('error');
        exit;
    }

    /**
     * Output sending.
     */
    public function send($template = null)
    {
        if (!$template) {
            $template = $this->get_task();
        }

        if ($this->page_title) {
            $this->output->assign('pagetitle', $this->page_title);
        }

        $this->output->send($template);
        exit;
    }

    /**
     * Returns name of the current task.
     *
     * @return string Task name
     */
    public function get_task()
    {
        $class_name = get_class($this);

        if (preg_match('/^kolab_client_task_([a-z]+)$/', $class_name, $m)) {
            return $m[1];
        }
    }

    /**
     * Returns output environment variable value
     *
     * @param string $name Variable name
     *
     * @return mixed Variable value
     */
    public function get_env($name)
    {
        return $this->output->get_env($name);
    }

    /**
     * Returns configuration option value.
     *
     * @param string $name      Option name
     * @param mixed  $fallback  Default value
     * @param int    $type      Value type (one of Conf class constants)
     *
     * @return mixed Option value
     */
    public function config_get($name, $fallback = null, $type = null)
    {
        $value = $this->config->get('kolab_wap', $name, $type);
        return $value !== null ? $value : $fallback;
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

    /**
     * Returns input parameter value.
     *
     * @param string $name       Parameter name
     * @param string $type       Parameter type (GET|POST|NULL)
     * @param bool   $allow_html Disables stripping of insecure content (HTML tags)
     *
     * @see kolab_utils::get_input
     * @return mixed Input value.
     */
    public static function get_input($name, $type = null, $allow_html = false)
    {
        if ($type == 'GET') {
            $type = kolab_utils::REQUEST_GET;
        }
        else if ($type == 'POST') {
            $type = kolab_utils::REQUEST_POST;
        }
        else {
            $type = kolab_utils::REQUEST_ANY;
        }

        $result = kolab_utils::get_input($name, $type, $allow_html);
        return $result;
    }

    /**
     * Returns task menu output.
     *
     * @return string HTML output
     */
    protected function menu()
    {
        if (empty($this->menu)) {
            return '';
        }

        $menu = array();
        $task = $this->get_task();
        $caps = (array) $this->get_capability('actions');

        foreach ($this->menu as $idx => $label) {
            if (in_array($task, array('domain', 'group', 'resource', 'role', 'user'))) {
                if (!array_key_exists($task . "." . $idx, $caps)) {
                    continue;
                }
            }

            if (strpos($idx, '.')) {
                $action = $idx;
                $class  = preg_replace('/\.[a-z_-]+$/', '', $idx);
            }
            else {
                $action = $task . '.' . $idx;
                $class  = $idx;
            }

            $menu[$idx] = sprintf('<li class="%s">'
                .'<a href="#%s" onclick="return kadm.command(\'%s\', \'\', this)">%s</a></li>',
                $class, $idx, $action, $this->translate($label));
        }

        return '<ul>' . implode("\n", $menu) . '</ul>';
    }

    /**
     * Adds watermark page definition into main page.
     */
    protected function watermark($name)
    {
        $this->output->command('set_watermark', $name);
    }

    /**
     * API GET request wrapper
     */
    protected function api_get($action, $get = array())
    {
        return $this->api_call('get', $action, $get);
    }

    /**
     * API POST request wrapper
     */
    protected function api_post($action, $get = array(), $post = array())
    {
        return $this->api_call('post', $action, $get, $post);
    }

    /**
     * API request wrapper with error handling
     */
    protected function api_call($type, $action, $get = array(), $post = array())
    {
        if ($type == 'post') {
            $result = $this->api->post($action, $get, $post);
        }
        else {
            $result = $this->api->get($action, $get);
        }

        // error handling
        if ($code = $result->get_error_code()) {
            // Invalid session, do logout
            if ($code == 403) {
                $this->action_logout(true, false);
            }

            // Log communication errors, other should be logged on API side
            if ($code < 400) {
                $this->raise_error($code, 'API Error: ' . $result->get_error_str());
            }
        }

        return $result;
    }

    /**
     * Returns list of object types.
     *
     * @para string  $type     Object type name
     * @param string $used_for Used_for attribute of object type
     *
     * @return array List of user types
     */
    protected function object_types($type, $used_for = null)
    {
        if (empty($type) || !in_array($type, $this->object_types)) {
            return array();
        }

        $cache_idx = $type . '_types' . ($used_for ? ":$used_for" : '');

        if (!array_key_exists($cache_idx, $this->cache)) {
            $result = $this->api_post($type . '_types.list');
            $list   = $result->get('list');

            if (!empty($used_for) && is_array($list)) {
                foreach ($list as $type_id => $type_attrs) {
                    if ($type_attrs['used_for'] != $used_for) {
                        unset($list[$type_id]);
                    }
                }
            }

            $this->cache[$cache_idx] = !empty($list) ? $list : array();

            Log::trace("kolab_client_task::${type}_types() returns: " . var_export($list, true));
        }

        return $this->cache[$cache_idx];
    }

    /**
     * Returns user name.
     *
     * @param string $dn User DN attribute value
     *
     * @return string User name (displayname)
     */
    protected function user_name($dn)
    {
        if (!$this->devel_mode) {
            if (!empty($this->cache['user_names']) && isset($this->cache['user_names'][$dn])) {
                return $this->cache['user_names'][$dn];
            }
        }

        $result   = $this->api_get('user.info', array('id' => $dn));
        $username = $result->get('displayname');

        if (empty($username)) {
            $username = $result->get('cn');
        }

        if (empty($username)) {
            if (preg_match('/^cn=([a-zA=Z ]+)/', $dn, $m)) {
                $username = ucwords($m[1]);
            }
        }

        if (!$this->devel_mode) {
            $this->cache['user_names'][$dn] = $username;
        }

        return $username;
    }

    /**
     * Returns list of system capabilities.
     *
     * @param bool $all     If enabled capabilities for all domains will be returned
     * @param bool $refresh Disable session cache
     *
     * @return array List of system capabilities
     */
    protected function capabilities($all = false, $refresh = false)
    {
        if (!$refresh && isset($_SESSION['capabilities']) && !$this->devel_mode) {
            $list = $_SESSION['capabilities'];
        }
        else {
            $result = $this->api_post('system.capabilities');
            $list   = $result->get('list');

            if (is_array($list) && !$this->devel_mode) {
                $_SESSION['capabilities'] = $list;
            }
        }

        $domain = $this->domain ? $this->domain : $_SESSION['user']['domain'];

        return !$all ? $list[$domain] : $list;
    }

    /**
     * Returns system capability
     *
     * @param string $name Capability (key) name
     *
     * @return array Capability value if supported, NULL otherwise
     */
    protected function get_capability($name)
    {
        $caps = $this->capabilities();

        return $caps[$name];
    }

    /**
     * Returns domains list (based on capabilities response)
     *
     * @param bool $refresh Refresh session cache
     *
     * @return array List of domains
     */
    protected function get_domains($refresh = false)
    {
        $caps = $this->capabilities(true, $refresh);

        return is_array($caps) ? array_keys($caps) : array();
    }

    /**
     * Returns effective rights for the specified object
     *
     * @param string $type Object type
     * @param string $id   Object identifier
     *
     * @return array Two element array with 'attribute' and 'entry' elements
     */
    protected function effective_rights($type, $id = null)
    {
        $caps = $this->get_capability('actions');

        if (empty($caps[$type . '.effective_rights'])) {
            return array(
                'attribute' => array(),
                'entry'     => array(),
            );
        }

        // Get the rights on the entry and attribute level
        $result = $this->api_get($type . '.effective_rights', array('id' => $id));

        $result = array(
            'attribute' => $result->get('attributeLevelRights'),
            'entry'     => $result->get('entryLevelRights'),
        );

        return $result;
    }

    /**
     * Returns execution time in seconds
     *
     * @param string Execution time
     */
    public function gentime()
    {
        return sprintf('%.4f', microtime(true) - KADM_START);
    }

    /**
     * Returns HTML output of login form
     *
     * @param string HTML output
     */
    public function login_form()
    {
        $post = $this->get_input('login', 'POST');

        $username = kolab_html::label(array(
                'for'     => 'login_name',
                'content' => $this->translate('login.username')), true)
            . kolab_html::input(array(
                'type'  => 'text',
                'id'    => 'login_name',
                'name'  => 'login[username]',
                'value' => $post['username'],
                'autofocus' => true));

        $password = kolab_html::label(array(
                'for'     => 'login_pass',
                'content' => $this->translate('login.password')), true)
            . kolab_html::input(array(
                'type'  => 'password',
                'id'    => 'login_pass',
                'name'  => 'login[password]',
                'value' => ''));

        $button = kolab_html::input(array(
            'type'  => 'submit',
            'id'    => 'login_submit',
            'value' => $this->translate('login.login')));

        $form = kolab_html::form(array(
            'id'     => 'login_form',
            'name'   => 'login',
            'method' => 'post',
            'action' => '?'),
            kolab_html::span(array('content' => $username))
            . kolab_html::span(array('content' => $password))
            . $button);

        return $form;
    }

    /**
     * Returns form element definition based on field attributes
     *
     * @param array $field Field attributes
     * @param array $data  Attribute values
     *
     * @return array Field definition
     */
    protected function form_element_type($field, $data = array())
    {
        $result = array();

        switch ($field['type']) {
        case 'select':
        case 'multiselect':
            $opts = $this->form_element_select_data($field, $data);

            $result['type']    = kolab_form::INPUT_SELECT;
            $result['options'] = $opts['options'];
            $result['value']   = $opts['default'];

            if ($field['type'] == 'multiselect') {
                $result['multiple'] = true;
            }

            break;

        case 'list':
            $result['type']      = kolab_form::INPUT_TEXTAREA;
            $result['data-type'] = kolab_form::TYPE_LIST;

            if (!empty($field['maxlength'])) {
                $result['data-maxlength'] = $field['maxlength'];
            }
            if (!empty($field['maxcount'])) {
                $result['data-maxcount'] = $field['maxcount'];
            }
            if (!empty($field['autocomplete'])) {
                $result['data-autocomplete'] = true;
            }
            break;

        case 'checkbox':
            $result['type'] = kolab_form::INPUT_CHECKBOX;
            break;

        case 'password':
            $result['type'] = kolab_form::INPUT_PASSWORD;

            if (isset($field['maxlength'])) {
                $result['maxlength'] = $field['maxlength'];
            }
            break;

        case 'text-quota':
            $result['type'] = kolab_form::INPUT_TEXTQUOTA;
            break;

        default:
            $result['type'] = kolab_form::INPUT_TEXT;
            if (isset($field['maxlength'])) {
                $result['maxlength'] = $field['maxlength'];
            }
            if ($field['type'] && $field['type'] != 'text') {
                $result['data-type'] = $field['type'];
                if ($field['type'] == 'ldap_url') {
                    $this->output->add_translation('ldap.one', 'ldap.sub', 'ldap.base',
                        'ldap.host', 'ldap.basedn','ldap.scope', 'ldap.conditions',
                        'ldap.filter_any', 'ldap.filter_both', 'ldap.filter_prefix', 'ldap.filter_suffix',
                        'ldap.filter_exact'
                    );
                }
            }
        }

        $result['required'] = empty($field['optional']);

        return $result;
    }

    /**
     * Prepares options/value of select element
     *
     * @param array $field Field attributes
     * @param array $data  Attribute values
     * @param bool  $lc    Convert option values to lower-case
     *
     * @return array Options/Default definition
     */
    protected function form_element_select_data($field, $data = array(), $lc = false)
    {
        $options = array();
        $default = null;

        if (!isset($field['values'])) {
            $data['attributes'] = array($field['name']);
            $resp = $this->api_post('form_value.select_options', null, $data);
            $resp = $resp->get($field['name']);
            unset($data['attributes']);

            $default         = $resp['default'];
            $field['values'] = $resp['list'];
        }

        if (!empty($field['values'])) {
            if ($lc) {
                $options = array_combine(array_map('strtolower', $field['values']), $field['values']);
            }
            else {
                $options = array_combine($field['values'], $field['values']);
            }

            // Exceptions
            if ($field['name'] == 'ou') {
                foreach ($options as $idx => $dn) {
                    $options[$idx] = ldap_dn2ufn($dn);
                }
            }
        }

        return array(
            'options' => $options,
            'default' => $default,
        );
    }

    /**
     * HTML Form elements preparation.
     *
     * @param string $name         Object name (user, group, etc.)
     * @param array  $data         Object data
     * @param array  $extra_fields Extra field names
     *
     * @return array Fields list, Object types list, Current type ID
     */
    protected function form_prepare($name, &$data, $extra_fields = array(), $used_for = null)
    {
        $types        = (array) $this->object_types($name, $used_for);
        $add_mode     = empty($data['id']);
        $event_fields = array();
        $auto_fields  = array();
        $form_fields  = array();
        $fields       = array();
        $auto_attribs = array();
        $extra_fields = array_flip($extra_fields);

        // Object type
        $data['object_type'] = $name;

        // Selected account type
        if (!empty($data['type_id'])) {
            $type = $data['type_id'];
        }
        else {
            $data['type_id'] = $type = key($types);
        }

        if ($type) {
            $auto_fields = (array) $types[$type]['attributes']['auto_form_fields'];
            $form_fields = (array) $types[$type]['attributes']['form_fields'];
        }

        // Mark automatically generated fields as read-only, etc.
        foreach ($auto_fields as $idx => $field) {
            //console("\$field value for \$auto_fields[\$idx] (idx: $idx)", $auto_fields[$idx]);
            if (!is_array($field)) {
                //console("not an array... unsetting");
                unset($auto_fields[$idx]);
                continue;
            }
            // merge with field definition from
            if (isset($form_fields[$idx])) {
                $field = array_merge($field, $form_fields[$idx]);
            }
            // remove auto-generated value on type change, it will be re-generated
            else if ($add_mode) {
                unset($data[$idx]);
            }

            $field['name'] = $idx;
            $fields[$idx]  = $this->form_element_type($field, $data);
            $fields[$idx]['readonly'] = true;

            $extra_fields[$idx] = true;

            // build auto_attribs and event_fields lists
            $is_data = 0;
            if (!empty($field['data'])) {
                 foreach ($field['data'] as $fd) {
                     $event_fields[$fd][] = $idx;
                     if (isset($data[$fd])) {
                        $is_data++;
                     }
                 }
                 if (count($field['data']) == $is_data) {
                     $auto_attribs[] = $idx;
                 }
            }
            else {
                //console("\$field['data'] is empty for \$auto_fields[\$idx] (idx: $idx)");
                $auto_attribs[] = $idx;
                // Unset the $auto_field array key to prevent the form field from
                // becoming disabled/readonly
                unset($auto_fields[$idx]);
            }
        }

        // Other fields
        foreach ($form_fields as $idx => $field) {
            if (!isset($fields[$idx])) {
                $field['name'] = $idx;
                $fields[$idx] = $this->form_element_type($field, $data);
            }
            else {
                unset($extra_fields[$idx]);
            }

            $fields[$idx]['readonly'] = false;

            // Attach on-change events to some fields, to update
            // auto-generated field values
            if (!empty($event_fields[$idx])) {
                $event = json_encode(array_unique($event_fields[$idx]));
                $fields[$idx]['onchange'] = "kadm.form_value_change($event)";
            }
        }

        // Get the rights on the entry and attribute level
        $data['effective_rights'] = $this->effective_rights($name, $data['id']);
        $attribute_rights         = (array) $data['effective_rights']['attribute'];
        $entry_rights             = (array) $data['effective_rights']['entry'];

        // See if "administrators" (those who can delete and add back on the entry
        // level) may override the automatically generated contents of auto_form_fields.
        $admin_auto_fields_rw = $this->config_get('admin_auto_fields_rw', false, Conf::BOOL);

        foreach ($fields as $idx => $field) {
            if (!array_key_exists($idx, $attribute_rights)) {
                // If the entry level rights contain 'add' and 'delete', well, you're an admin
                if (in_array('add', $entry_rights) && in_array('delete', $entry_rights)) {
                    if ($admin_auto_fields_rw) {
                        $fields[$idx]['readonly'] = false;
                    }
                }
                else {
                    $fields[$idx]['readonly'] = true;
                }
            }
            else {
                if (in_array('add', $entry_rights) && in_array('delete', $entry_rights)) {
                    if ($admin_auto_fields_rw) {
                        $fields[$idx]['readonly'] = false;
                    }
                }
                // Explicit attribute level rights, check for 'write'
                elseif (!in_array('write', $attribute_rights[$idx])) {
                    $fields[$idx]['readonly'] = true;
                }
            }
        }

        // Register list of auto-generated fields
        $this->output->set_env('auto_fields', $auto_fields);
        // Register list of disabled fields
        $this->output->set_env('extra_fields', array_keys($extra_fields));

        // (Re-|Pre-)populate auto_form_fields
        if ($add_mode) {
            if (!empty($auto_attribs)) {
                $data['attributes'] = $auto_attribs;
                $resp = $this->api_post('form_value.generate', null, $data);
                $data = array_merge((array)$data, (array)$resp->get());
                unset($data['attributes']);
            }
        }
        else {
            // Add common information fields
            $add_fields = array(
                'creatorsname'  => 'createtimestamp',
                'modifiersname' => 'modifytimestamp',
            );
            foreach ($add_fields as $idx => $val) {
                if (!empty($data[$idx])) {
                    if ($value = $this->user_name($data[$idx])) {
                        if ($data[$val]) {
                            $value .= ' (' . strftime('%x %X', strtotime($data[$val])) . ')';
                        }

                        $fields[$idx] = array(
                            'label'   => $idx,
                            'section' => 'system',
                            'value'   => $value,
                        );
                    }
                }
            }

            // Add debug information
            if ($this->devel_mode) {
                ksort($data);
                $debug = kolab_html::escape(print_r($data, true));
                $debug = preg_replace('/(^Array\n\(|\n*\)$|\t)/', '', $debug);
                $debug = str_replace("\n    ", "\n", $debug);
                $debug = '<pre class="debug">' . $debug . '</pre>';
                $fields['debug'] = array(
                        'label'   => 'debug',
                        'section' => 'system',
                        'value'   => $debug,
                    );
            }
        }

        // Add object type hidden field
        $fields['object_type'] = array(
            'section'  => 'system',
            'type'     => kolab_form::INPUT_HIDDEN,
            'value'    => $name,
        );

        // Get user-friendly names for lists
        foreach ($fields as $fname => $field) {
            if (!empty($field['data-autocomplete']) && !empty($data[$fname])) {
                if (!is_array($data[$fname])) {
                    $data[$fname] = (array) $data[$fname];
                }

                //console("The data for field $fname at this point is", $data[$fname]);
                // request parameters
                $post = array(
                    'list'        => $data[$fname],
                    'attribute'   => $fname,
                    'object_type' => $name,
                    'type_id'     => $data['type_id'],
                );

                // get options list
                $result = $this->api_post('form_value.list_options', null, $post);
                $result = $result->get('list');

                $data[$fname] = $result;
                //console("Set \$data['$fname'] to", $result);
            }
        }

        // Add entry identifier
        if (!$add_mode) {
            $fields['id'] = array(
                'section'   => 'system',
                'type'      => kolab_form::INPUT_HIDDEN,
                'value'     => $data['id']
            );
        }

        $result = array($fields, $types, $type);
        return $result;
    }

    /**
     * HTML Form creation.
     *
     * @param string $name       Object name (user, group, etc.)
     * @param array  $attribs    HTML attributes of the form
     * @param array  $sections   List of form sections
     * @param array  $fields     Fields list (from self::form_prepare())
     * @param array  $fields_map Fields map (used for sorting and sections assignment)
     * @param array  $data       Object data (with effective rights, see form_prepare())
     *
     * @return kolab_form HTML Form object
     */
    protected function form_create($name, $attribs, $sections, $fields, $fields_map, $data, $add_mode)
    {
        //console("Creating form for $name with data", $data);

        //console("Assign fields to sections", $fields);
        // Assign sections to fields
        foreach ($fields as $idx => $field) {
            if (!$field['section']) {
                $fields[$idx]['section'] = isset($fields_map[$idx]) ? $fields_map[$idx] : 'other';
                //console("Assigned field $idx to section " . $fields[$idx]['section']);
            }
        }

        //console("Using fields_map", $fields_map);

        // Sort
        foreach ($fields_map as $idx => $val) {
            if (array_key_exists($idx, $fields)) {
                $fields_map[$idx] = $fields[$idx];
                unset($fields[$idx]);
            }
            else {
                unset($fields_map[$idx]);
            }
        }
        if (!empty($fields)) {
            $fields_map = array_merge($fields_map, $fields);
        }

        //console("Using attribs", $attribs);

        $form = new kolab_form($attribs);
        $assoc_fields = array();
        $req_fields   = array();
        $writeable    = 0;

        $auto_fields = $this->output->get_env('auto_fields');

        //console("form_create() \$attribs", $attribs);
        //console("form_create() \$auto_fields", $auto_fields);

        //console("Going to walk through sections", $sections);

        // Parse elements and add them to the form object
        foreach ($sections as $section_idx => $section) {
            $form->add_section($section_idx, kolab_html::escape($this->translate($section)));

            foreach ($fields_map as $idx => $field) {
                if ($field['section'] != $section_idx) {
                    continue;
                }

                if (empty($field['label'])) {
                    $field['label'] = "$name.$idx";
                }

                $field['label']       = kolab_html::escape($this->translate($field['label']));
                $field['description'] = "$name.$idx.desc";
                $field['section']     = $section_idx;

                if (empty($field['value']) && !empty($data[$idx])) {

                    //console("Using data value", $data[$idx], "for value of field $idx");

                    $field['value'] = $data[$idx];

                    // Convert data for the list field with autocompletion
                    if ($field['data-type'] == kolab_form::TYPE_LIST) {
                        if (!is_array($data[$idx])) {
                            if (!empty($field['data-autocomplete'])) {
                                $data[$idx] = array($data[$idx] => $data[$idx]);
                            }
                            else {
                                $data[$idx] = (array) $data[$idx];
                            }
                        }

                        $field['value'] = !empty($field['data-autocomplete']) ? array_keys($data[$idx]) : array_values($data[$idx]);
                    }

                    if (is_array($field['value'])) {
                        $field['value'] = implode("\n", $field['value']);
                    }
                }

                // @TODO: We assume here that all autocompletion lists are associative
                // It's likely that we'll need autocompletion on ordinary lists
                if (!empty($field['data-autocomplete'])) {
                    $assoc_fields[$idx] = !empty($data[$idx]) ? $data[$idx] : array();
                }
/*
                if (!empty($field['suffix'])) {
                    $field['suffix'] = kolab_html::escape($this->translate($field['suffix']));
                }
*/
                if (!empty($field['options'])) {
                    foreach ($field['options'] as $opt_idx => $option) {
                        if (is_array($option)) {
                            $field['options'][$opt_idx]['content'] = kolab_html::escape($this->translate($option['content']));
                        }
                        else {
                            $field['options'][$opt_idx] = kolab_html::escape($this->translate($option));
                        }
                    }
                }

                if (!empty($field['description'])) {
                    $description = $this->translate($field['description']);
                    if ($description != $field['description']) {
                        $field['title'] = $description;
                    }
                    unset($field['description']);
                }

                if (empty($field['name'])) {
                    $field['name'] = $idx;
                }

                if (empty($field['readonly']) && empty($field['disabled'])) {
                    // count writeable fields
                    if ($field['type'] && $field['type'] != kolab_form::INPUT_HIDDEN) {
                        $writeable++;
                    }
                    if ($idx != "userpassword2") {
                        if (!empty($field['required'])) {
                            $req_fields[] = $idx;
                        }
                    }
                }

                //console("Adding field to form", $field);

                $form->add_element($field);
            }
        }

        if (!empty($data['section'])) {
            $form->activate_section($data['section']);
        }

        if ($writeable) {
            $form->add_button(array(
                'value'   => kolab_html::escape($this->translate('button.submit')),
                'onclick' => "kadm.{$name}_save()",
            ));
        }

        if (!empty($data['id']) && in_array('delete', (array) $data['effective_rights']['entry'])) {
            $id = $data['id'];
            $form->add_button(array(
                'value'   => kolab_html::escape($this->translate('button.delete')),
                'onclick' => "kadm.{$name}_delete('{$id}')",
            ));
        }

        $ac_min_len = $this->config_get('autocomplete_min_length', 1, Conf::INT);

        $this->output->set_env('form_id', $attribs['id']);
        $this->output->set_env('assoc_fields', $assoc_fields);
        $this->output->set_env('required_fields', $req_fields);
        $this->output->set_env('autocomplete_min_length', $ac_min_len);
        $this->output->add_translation('form.required.empty', 'form.maxcount.exceeded',
            $name . '.add.success', $name . '.edit.success', $name . '.delete.success',
            'add', 'edit', 'delete');

        return $form;
    }

}
