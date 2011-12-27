<?php


class kolab_client_task
{
    /**
     * @var kolab_admin_output
     */
    protected $output;

    /**
     * @var kolab_admin_api
     */
    protected $api;

    protected $ajax_only = false;
    protected $page_title = 'Kolab Admin Panel';
    protected $menu = array();

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
                $language = $lang;
                break;
            }
            if (isset($aliases[$lang]) && ($alias = $aliases[$lang])
                && file_exists(INSTALL_PATH . "/locale/$alias.php")
            ) {
                $language = $alias;
                break;
            }
        }

        $LANG = array();
        @include INSTALL_PATH . '/locale/en_US.php';

        if (isset($language)) {
            @include INSTALL_PATH . "/locale/$language.php";
            setlocale(LC_ALL, $language . '.utf8', 'en_US.utf8');
        }
        else {
            setlocale(LC_ALL, 'en_US.utf8');
        }

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
        $this->api = new kolab_api($url);
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

                if ($token = $result->get('session_token')) {
                    $user = array('token' => $token, 'domain' => $result->get('domain'));

                    $this->api->set_session_token($user['token']);

                    // find user settings
                    $res = $this->api->get('user.info', array('user' => $login['username']));
                    $res = $res->get();

                    if (is_array($res) && ($res = array_shift($res))) {
                        $user['language'] = $res['preferredlanguage'];
                        $user['fullname'] = $res['cn'];
                    }

                    // Initialize list of user types
                    $this->user_types();

                    $_SESSION['user'] = $user;
                    header('Location: ?');
                    die;
                }
                else {
                    $code  = $result->get_error_code();
                    $str   = $result->get_error_str();
                    $label = 'loginerror';

                    if ($code == kolab_api::ERROR_INTERNAL
                        || $code == kolab_api::ERROR_CONNECTION
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

        // Run security checks
        $this->input_checks();

        if (empty($_SESSION['user']) || empty($_SESSION['user']['token'])) {
            $this->action_logout();
        }

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
        $token  = $ajax ? kolab_utils::request_header('X-KAP-Request') : $this->get_input('token');
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
            $this->output->assign('login', $this->get_input('login', 'POST'));
            $this->output->add_translation('loginerror', 'internalerror');
            $this->output->send('login');
        }
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
            trigger_error($msg);
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
     * @param bool   $allow_html Enable to strip invalid/unsecure content
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

        foreach ($this->menu as $idx => $label) {
            if (strpos($idx, '.')) {
                $action = $idx;
                $class  = preg_replace('/\.[a-z_-]+$/', '', $idx);
            }
            else {
                $action = $task . '.' . $idx;
                $class  = $idx;
            }

            $menu[$idx] = sprintf('<li class="%s"><a href="#%s" '
                .'onclick="return kadm.command(\'%s\', \'\', this)">%s</a></li>',
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
     * @param array List of user types
     */
    protected function user_types()
    {
        if (!isset($_SESSION['user_types'])) {
            $result = $this->api->post('user_types.list');
            $list   = $result->get();

            if (is_array($list)) {
                $_SESSION['user_types'] = $list;
            }
        }

        return $_SESSION['user_types'];
    }

}
