<?php

require_once 'SQL.php';

/**
 * Interface class for Kolab Admin Services
 */
abstract class kolab_api_service
{
    protected $controller;
    protected $db;

    public function __construct($ctrl)
    {
        $this->db         = SQL::get_instance();
        $this->controller = $ctrl;
    }

    /**
     * Advertise this service's capabilities
     */
    abstract public function capabilities($domain);

}
