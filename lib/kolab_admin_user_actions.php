<?php

    /**
     *
     */
    class kolab_admin_user_actions extends kolab_admin_service
    {
        public function capabilities($domain)
        {
            // TODO: check permissions of the authenticated user
            $uid = $this->controller->get_uid();

            return array(
                    'list' => 'l',
                    'info' => 'r',
                );
        }
    }

?>