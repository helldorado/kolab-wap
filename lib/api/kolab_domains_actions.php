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
//             'search' => 'r',
        );
    }

    public function domains_list($get, $post) {
        $auth = Auth::get_instance();

        $domains = $auth->list_domains();
        $count   = count($domains);

        // pagination
        if (!empty($post['page_size']) && $count) {
            $size   = (int) $post['page_size'];
            $page   = !empty($post['page']) ? $post['page'] : 1;
            $page   = max(1, (int) $page);
            $offset = ($page - 1) * $size;

            $domains = array_slice($domains, $offset, $size, true);
        }

        return array(
            'list'  => $domains,
            'count' => $count,
        );
    }
}

