<?php


class kolab_api
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

    const ERROR_INTERNAL   = 100;
    const ERROR_CONNECTION = 200;

    /**
     * Class constructor.
     *
     * @param string $base_url Base URL of the Kolab API
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
        $this->request = new HTTP_Request2();
    }

    /**
     * Logs specified user into the API
     *
     * @param string $username User name
     * @param string $password User password
     *
     * @return kolab_api_result Request response
     */
    public function login($username, $password)
    {
        $query = array(
            'username' => $username,
            'password' => $password
        );

        $response = $this->post('system.authenticate', null, $query);

        return $response;
    }

    /**
     * Logs specified user out of the API
     *
     * @return bool True on success, False on failure
     */
    public function logout()
    {
        $response = $this->get('system.quit');

        return $response->get_error_code() ? false : true;
    }

    /**
     * Sets session token value.
     *
     * @param string $token  Token string
     */
    public function set_session_token($token)
    {
        $this->request->setHeader('X-Session-Token', $token);
    }

    /**
     * Gets capabilities of the API (according to logged in user).
     *
     * @return kolab_api_result  Capabilities response
     */
    public function get_capabilities()
    {
        $this->get('system.capabilities');
    }

    /**
     * API's GET request.
     *
     * @param string $action  Action name
     * @param array  $args    Request arguments
     *
     * @return kolab_api_result  Response
     */
    public function get($action, $args = array())
    {
        $url = $this->build_url($action, $args);

        $this->request->setMethod(HTTP_Request2::METHOD_GET);

        return $this->get_response($url);
    }

    /**
     * API's POST request.
     *
     * @param string $action    Action name
     * @param array  $url_args  URL arguments
     * @param array  $post      POST arguments
     *
     * @return kolab_api_result  Response
     */
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
     * @return kolab_api_result Response object
     */
    private function get_response($url)
    {
        try {
            $this->request->setUrl($url);
            $response = $this->request->send();
        }
        catch (Exception $e) {
            return new kolab_api_result(null,
                self::ERROR_CONNECTION, $e->getMessage());
        }

        try {
            $body = $response->getBody();
        }
        catch (Exception $e) {
            return new kolab_api_result(null,
                self::ERROR_INTERNAL, $e->getMessage());
        }

        $body     = @json_decode($body, true);
        $err_code = null;
        $err_str  = null;

        if (is_array($body) && (empty($body['status']) || $body['status'] != 'OK')) {
            $err_code = !empty($body['code']) ? $body['code'] : self::ERROR_INTERNAL;
            $err_str  = !empty($body['reason']) ? $body['reason'] : 'Unknown error';
        }
        else if (!is_array($body)) {
            $err_code = self::ERROR_INTERNAL;
            $err_str  = 'Unable to decode response';
        }

        return new kolab_api_result($body, $err_code, $err_str);
    }

}
