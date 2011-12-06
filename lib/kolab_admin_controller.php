<?php

    /**
     * Main controller class to serve the Kolab Admin API
     */
    class kolab_admin_controller
    {
        public $output;

        private $uid;
        private $request = array();
        private $services = array();
        private $domains = array('localhost');


        public function __construct()
        {
            $this->output = new kolab_admin_json_output();

            if (isset($_GET['service']) && !empty($_GET['service'])) {
                if (isset($_GET['method']) && !empty($_GET['method'])) {
                    $this->request = Array(
                            'service' => $_GET['service'],
                            'method' => $_GET['method']
                        );
                } else {
                    throw new Exception("Unknown service", 400);
                }

            } else {
                throw new Exception("Unknown service", 400);
            }

            // TODO: register services based on config or whatsoever
            // $this->add_service('user', 'kolab_admin_user_actions');
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
            if ($service == 'system')
                return $this;

            if ($handler = $this->services[$service]) {
                if (is_string($handler))
                    $handler = $this->services[$service] = new $handler($this);

                if (is_a($handler, 'kolab_admin_service'))
                    return $handler;
            }

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
            $service = $this->request['service'];
            $method = $this->request['method'];

            // validate user session
            if ($method != 'authenticate') {
                if (!$this->session_validate($postdata)) {
                    throw new Exception("Invalid session", 403);
                }
            }

            // call service method
            $service_handler = $this->get_service($service);

            if (method_exists($service_handler, $method))
                $result = $service_handler->$method($_GET, $postdata);
            else
                throw new Exception("Unknown method", 405);

            // send response
            if ($result !== false)
                $this->output->success($result);
            else
                $this->output->error("Internal error", 500);
        }


        /**
         * Validate the submitted session token
         */
        private function session_validate($postdata)
        {
            $sess_id = !empty($postdata['session_token']) ? $postdata['session_token'] : self::get_request_header('X-Session-Token');

            if (empty($sess_id))
                return false;

            session_id($sess_id);
            session_start();

            if ($_SESSION['uid']) {
                $this->uid = $_SESSION['uid'];
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
         */
        private function authenticate($request, $postdata)
        {
            $valid = false;

            // destroy old session
            if ($this->session_validate($postdata))
                session_destroy();

            $user = new User();
            $valid = $user->authenticate($postdata['username'], $postdata['password']);

            // start new (PHP) session
            if ($valid) {
                session_start();
                $_SESSION['user'] = $user;
                $_SESSION['start'] = time();
                return array('user' => $postdata['username'], 'session_token' => session_id());
            }

            return false;
        }


        /**
         * Provide a list of capabilities the backend provides to the current user
         */
        private function capabilities()
        {
            $result = array();

            // add capabilities of all registered services
            foreach ($this->domains as $domain) {
                // define our very own capabilities
                $actions = array(
                    array('action' => 'system.quit', 'type' => 'w'),
                );

                foreach ($this->services as $sname => $handler) {
                    $service = $this->get_service($sname);
                    foreach ($service->capabilities($domain) as $method => $type) {
                        $actions[] = array('action' => "$sname.$method", 'type' => $type);
                    }
                }

                $result[] = array('domain' => $domain, 'actions' => $actions);
            }

            return array('capabilities' => $result);
        }


        /**
         * End the current user ession
         */
        private function quit()
        {
            session_destroy();
            return true;
        }


        /* ========  Utility functions  ======== */


        /**
         * Make sure the string ends with a slash
         */
        public static function slashify($str)
        {
            return self::unslashify($str).'/';
        }

        /**
         * Remove slash at the end of the string
         */
        public static function unslashify($str)
        {
            return preg_replace('/\/$/', '', $str);
        }

        /**
         * Read a specific HTTP request header
         *
         * @param  string $name Header name
         * @return mixed  Header value or null if not available
         */
        public static function get_request_header($name) {
            if (function_exists('getallheaders')) {
                $hdrs = array_change_key_case(getallheaders(), CASE_UPPER);
                $key  = strtoupper($name);
            } else {
                $key  = 'HTTP_' . strtoupper(strtr($name, '-', '_'));
                $hdrs = array_change_key_case($_SERVER, CASE_UPPER);
            }

            if (array_key_exists($key, $hdrs)) {
                return $hdrs[$key];
            } else {
                return null;
            }
        }
    }

?>
