<?php

    /**
     * Interface class for Kolab Admin Services
     */
    abstract class kolab_admin_api_service
    {
        protected $controller;

        public function __construct($ctrl)
        {
            $this->controller = $ctrl;
        }

        /**
        * Advertise this service's capabilities
        */
        abstract public function capabilities($domain);

    }

?>
