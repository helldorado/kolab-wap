<?php

    /**
     *
     */
    class kolab_domains_actions extends kolab_api_service
    {
        public function capabilities($domain)
        {
            return array(
                    'list' => 'r',
//                     'search' => 'r',
                );
        }

        public function domains_list($get, $post) {
            $auth = Auth::get_instance();
            $domains = $auth->list_domains();
            return $domains;
        }
    }

?>
