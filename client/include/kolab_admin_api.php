<?php


class kolab_admin_api
{
    /**
     * @var HTTP_Request2
     */
    private $request;

    /**
     * @var string
     */
    private $base_url;


    const STATUS_OK    = 0;
    const STATUS_ERROR = 1;

    const ERROR_INTERNAL = 500;

    /**
     * Class constructor.
     *
     * @param string $base_url Base URL of Kolab API
     */
    public function __construct($base_url)
    {
        $this->base_url = $base_url;
        $this->init();
    }

    /**
     * Initializes HTTP Request object.
     */
    public function init()
    {
        require_once 'HTTP/Request2.php';

        $this->request = new HTTP_Request2();
    }

    /**
     *
     * @return array Session user data (token, domain)
     */
    public function login($username, $password)
    {
        $query = array(
            'username' => $username,
            'password' => $password
        );

        $response = $this->post('system.authenticate', null, $query);

        if ($token = $response->get('session_token')) {
            return array(
                'token'  => $token,
                'domain' => $response->get('domain'),
            );
        }
    }

    public function logout()
    {
        $response = $this->get('system.quit');

        return $response->get_error_code() ? false : true;
    }

    public function set_session_token($token)
    {
        $this->request->setHeader('X-Session-Token', $token);
    }

    public function get_capabilities()
    {
        $this->get('system.capabilities');
    }

    public function get($action, $args = array())
    {
        $url = $this->build_url($action, $args);

        $this->request->setMethod(HTTP_Request2::METHOD_GET);

        return $this->get_response($url);
    }

    public function post($action, $url_args = array(), $post = array())
    {
        $url = $this->build_url($action, $url_args);

        $this->request->setMethod(HTTP_Request2::METHOD_POST);
        $this->request->setBody(@json_encode($post));

        return $this->get_response($url);
    }

    /**
     * @param string $action Action GET parameter
     * @param array  $args   GET parameters (hash array: name => value)
     *
     * @return Net_URL2 URL object
     */
    private function build_url($action, $args)
    {
        $url = $this->base_url;

        if ($action) {
            $url .= '/' . urlencode($action);
        }

        $url = new Net_URL2($url);

        if (!empty($args)) {
            $url->setQueryVariables($args);
        }

        return $url;
    }

    /**
     * HTTP Response handler.
     *
     * @param Net_URL2 $url URL object
     *
     * @return kolab_admin_api_result Response object
     */
    private function get_response($url)
    {
        try {
            $this->request->setUrl($url);
            $response = $this->request->send();
        }
        catch (Exception $e) {
            return new kolab_admin_api_result(null,
                self::ERROR_INTERNAL, $e->getMessage());
        }

        try {
            $body = $response->getBody();
        }
        catch (Exception $e) {
            return new kolab_admin_api_result(null,
                self::ERROR_INTERNAL, $e->getMessage());
        }

//print_r($body);
        $body     = @json_decode($body, true);
        $err_code = null;
        $err_str  = null;

        if (is_array($body) && (empty($body['status']) || $body['status'] != 'OK')) {
            $err_code = !empty($data['code']) ? $data['code'] : self::ERROR_INTERNAL;
            $err_str  = !empty($data['reason']) ? $data['reason'] : 'Unknown error';
        }
        else if (!is_array($body)) {
            $err_code = self::ERROR_INTERNAL;
            $err_str  = 'Unable to decode response';
        }

        return new kolab_admin_api_result($body, $err_code, $err_str);
    }
}
