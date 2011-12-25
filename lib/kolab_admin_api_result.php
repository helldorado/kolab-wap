<?php

class kolab_admin_api_result
{
    /**
     * @var array
     */
    private $data = array();

    private $error_code;
    private $error_str;


    public function __construct($data = array(), $error_code = null, $error_str = null)
    {
        if (is_array($data) && isset($data['result'])) {
            $this->data = $data['result'];
        }

        $this->error_code = $error_code;
        $this->error_str = $error_str;
    }

    public function get_error_code()
    {
        return $this->error_code;
    }

    public function get_error_str()
    {
        return $this->error_str;
    }

    public function get($name = null)
    {
        if ($name !== null) {
            return isset($this->data[$name]) ? $this->data[$name] : null;
        }

        return $this->data;
    }
}
