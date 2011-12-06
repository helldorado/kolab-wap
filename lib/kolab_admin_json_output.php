<?php

    /**
     * View class generating JSON output
     */
    class kolab_admin_json_output
    {

        /**
         *
         */
        public function success($data)
        {
            if (!is_array($data))
                $data = array();

            $this->send(array('status' => 'OK') + $data);
        }


        /**
         *
         */
        public function error($errdata, $code = 400)
        {
            if (is_string($errdata))
                $errdata = array('reason' => $errdata);

            $this->send(array('status' => 'ERROR', 'code' => $code) + $errdata);
        }


        /**
         *
         */
        public function send($data)
        {
            header("Content-Type: application/json");
            echo json_encode($data);
            exit;
        }

    }

?>