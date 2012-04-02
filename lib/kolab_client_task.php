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

    protected $ajax_only = false;
    protected $page_title = 'Kolab Admin Panel';
    protected $menu = array();
    protected $cache = array();

    protected static $translation = array();


    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->config_init();
        $this->output_init();
        $this->api_init();

        ini_set('session.use_cookies', 'On');
        session_start();

        $this->auth();
    }

    /**
     * Localization initialization.
     */
    private function locale_init()
    {
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
    private function output_init()
    {
        $skin = $this->config_get('skin', 'default');
        $this->output = new kolab_client_output($skin);
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
                $result = $this->api->login($login['username'], $login['password']);

                //console($result);

                if ($token = $result->get('session_token')) {
                    $user = array('token' => $token, 'domain' => $result->get('domain'));

                    $this->api->set_session_token($user['token']);

                    // find user settings
                    $res = $this->api->get('user.info', array('user' => $login['username']));
                    $res = $res->get();

                    if (is_array($res) && !empty($res)) {
                        $user['language'] = $res['preferredlanguage'];
                        $user['fullname'] = $res['cn'];
                    }
                    // @TODO: why user.info returns empty result for 'cn=Directory Manager' login?
                    else if (preg_match('/^cn=([a-zA-Z ]+)/', $login['username'], $m)) {
                        $user['fullname'] = ucwords($m[1]);
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
            $this->api->set_session_token($_SESSION['user']['token']);
            return;
        }
    }

    /**
     * Main execution.
     */
    public function run()
    {
        // Initialize locales
        $this->locale_init();

        // Assign self to template variable
        $this->output->assign('engine', $this);

        // Session check
        if (empty($_SESSION['user']) || empty($_SESSION['user']['token'])) {
            $this->action_logout();
        }

        // Run security checks
        $this->input_checks();

        $action = $this->get_input('action', 'GET');

        if ($action) {
            $method = 'action_' . $action;
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
    private function action_logout()
    {
        if (!empty($_SESSION['user']) && !empty($_SESSION['user']['token'])) {
            $this->api->logout();
        }
        $_SESSION = array();

        if ($this->output->is_ajax()) {
            $this->output->command('main_logout');
        }
        else {
            $this->output->add_translation('loginerror', 'internalerror');
        }

        $this->output->send('login');
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

        if (!write_log('errors', $log_line)) {
            // send error to PHPs error handler if write_log() didn't succeed
            trigger_error($msg, E_USER_ERROR);
        }

        if (!$output) {
            return;
        }

        if ($this->output->is_ajax()) {
            header("HTTP/1.0 $code $msg");
            die;
        }

        $this->output->assign('error_code', $code);
        $this->output->assign('error_message', $msg);
        $this->output->send('error');
        exit;
    }

    /**
     * Output sending.
     */
    public function send()
    {
        $template = $this->get_task();

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
     * Returns configuration option value.
     *
     * @param string $name      Option name
     * @param mixed  $fallback  Default value
     *
     * @return mixed Option value
     */
    public function config_get($name, $fallback = null)
    {
        if ($name == "devel_mode")
            return TRUE;

        $value = $this->config->get('kolab_wap', $name);
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

        return kolab_utils::get_input($name, $type, $allow_html);
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

        $task = $this->get_task();
// @TODO: hide menu items according to capabilities
//        $caps = (array) $this->capabilities();

        foreach ($this->menu as $idx => $label) {
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
     * Returns list of user types.
     *
     * @return array List of user types
     */
    protected function user_types()
    {
        if (isset($_SESSION['user_types'])) {
            return $_SESSION['user_types'];
        }

        $result = $this->api->post('user_types.list');
        $list   = $result->get('list');

        if (is_array($list) && !$this->config_get('devel_mode')) {
            $_SESSION['user_types'] = $list;
        }

        return $list;
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
        if (!empty($this->cache['user_names']) && isset($this->cache['user_names'][$dn])) {
            return $this->cache['user_names'][$dn];
        }

        $result   = $this->api->get('user.info', array('user' => $dn));
        $username = $result->get('displayname');

        if (empty($username)) {
            if (preg_match('/^cn=([a-zA=Z ]+)/', $dn, $m)) {
                $username = ucwords($m[1]);
            }
        }

        return $this->cache['user_names'][$dn] = $username;
    }

    /**
     * Returns list of system capabilities.
     *
     * @return array List of system capabilities
     */
    protected function capabilities()
    {
        if (!isset($_SESSION['capabilities'])) {
            $result = $this->api->post('system.capabilities');
            $list   = $result->get('list');

            if (is_array($list)) {
                $_SESSION['capabilities'] = $list;
            }
        }

        $domain = $_SESSION['user']['domain'];

        return $_SESSION['capabilities'][$domain];
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
            if (!isset($field['values'])) {
                $data['attributes'] = array($field['name']);
                $resp = $this->api->post('form_value.select_options', null, $data);
                $field['values'] = $resp->get($field['name']);
            }

            if (!empty($field['values']['default'])) {
                $result['value'] = $field['values']['default'];
                unset($field['values']['default']);
            }

            $result['type'] = kolab_form::INPUT_SELECT;
            if (!empty($field['values'])) {
                $result['options'] = array_combine($field['values'], $field['values']);
            }
            else {
                $result['options'] = array('');
            }

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
            if (!empty($field['autocomplete'])) {
                $result['data-autocomplete'] = true;
            }
            break;

        default:
            $result['type'] = kolab_form::INPUT_TEXT;
            if (isset($field['maxlength'])) {
                $result['maxlength'] = $field['maxlength'];
            }
        }

        return $result;
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
    protected function form_prepare($name, &$data, $extra_fields = array())
    {
        $types        = (array) $this->{$name . '_types'}();
        $form_id      = $attribs['id'];
        $add_mode     = empty($data['id']);

        $event_fields = array();
        $auto_fields  = array();
        $form_fields  = array();
        $fields       = array();
        $auto_attribs = array();

        $extra_fields = array_flip($extra_fields);

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
            if (!is_array($field)) {
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
            $fields[$idx] = $this->form_element_type($field);
            $fields[$idx]['readonly'] = true;
            $fields[$idx]['disabled'] = true;

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
                $auto_attribs[] = $idx;
                unset($auto_fields[$idx]);
            }
        }

        // Other fields
        foreach ($form_fields as $idx => $field) {
            if (!isset($fields[$idx])) {
                $field['name'] = $idx;
                $fields[$idx] = $this->form_element_type($field);
            }
            else {
                unset($extra_fields[$idx]);
            }
//            $fields[$idx]['required'] = true;
            $fields[$idx]['readonly'] = false;
            $fields[$idx]['disabled'] = false;

            // Attach on-change events to some fields, to update
            // auto-generated field values
            if (!empty($event_fields[$idx])) {
                $event = json_encode(array_unique($event_fields[$idx]));
                $fields[$idx]['onchange'] = "kadm.form_value_change($event)";
            }
        }

        // Register list of auto-generated fields
        $this->output->set_env('auto_fields', $auto_fields);
        // Register list of disabled fields
        $this->output->set_env('extra_fields', array_keys($extra_fields));

        // (Re-|Pre-)populate auto_form_fields
        if ($add_mode) {
            if (!empty($auto_attribs)) {
                $data = array_merge((array)$data, array(
                    'attributes'  => $auto_attribs,
                    'object_type' => $name,
                ));
                $resp = $this->api->post('form_value.generate', null, $data);
                $data = array_merge((array)$data, (array)$resp->get());
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
            if ($this->config_get('devel_mode')) {
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

        // Add entry identifier
        if (!$add_mode) {
            $fields['id'] = array(
                    'section'   => 'system',
                    'type'      => kolab_form::INPUT_HIDDEN,
                    'value'     => $data['id']
                );
        }

        return array($fields, $types, $type);
    }

    /**
     * HTML Form creation.
     *
     * @param string $name       Object name (user, group, etc.)
     * @param array  $attribs    HTML attributes of the form
     * @param array  $sections   List of form sections
     * @param array  $fields     Fields list (from self::form_prepare())
     * @param array  $fields_map Fields map (used for sorting and sections assignment)
     * @param array  $data       Object data
     *
     * @return kolab_form HTML Form object
     */
    protected function form_create($name, $attribs, $sections, $fields, $fields_map, $data, $add_mode)
    {
        // Assign sections to fields
        foreach ($fields as $idx => $field) {
            if (!$field['section']) {
                $fields[$idx]['section'] = isset($fields_map[$idx]) ? $fields_map[$idx] : 'other';
            }
        }

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

        $form = new kolab_form($attribs);
        $assoc_fields = array();

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
                    $field['value'] = $data[$idx];

                    // Convert data for the list field with autocompletion
                    if ($field['data-type'] == kolab_form::TYPE_LIST && kolab_utils::is_assoc($data[$idx])) {
                        $assoc_fields[$idx] = $data[$idx];
                        $field['value'] = array_keys($data[$idx]);
                    }

                    if (is_array($field['value'])) {
                        $field['value'] = implode("\n", $field['value']);
                    }
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

                $form->add_element($field);
            }
        }

        if (!empty($data['section'])) {
            $form->activate_section($data['section']);
        }

        $form->add_button(array(
            'value'   => kolab_html::escape($this->translate('submit.button')),
            'onclick' => $add_mode ? "kadm.{$name}_add()" : "kadm.{$name}_edit()",
        ));

        if (!empty($data['id'])) {
            $id = $data[$name];
            $form->add_button(array(
                'value'   => kolab_html::escape($this->translate('delete.button')),
                'onclick' => "kadm.{$name}_delete('{$id}')",
            ));
        }

        $this->output->set_env('form_id', $attribs['id']);
        $this->output->set_env('assoc_fields', $assoc_fields);

        return $form;
    }

}
