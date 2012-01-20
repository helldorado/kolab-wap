<?php

/**
 *
 */
class kolab_groups_actions extends kolab_api_service
{
    public function capabilities($domain)
    {
        return array(
            'list' => 'r',
            );
    }

    public function groups_list($get, $post)
    {
        $auth = Auth::get_instance();

        $groups = $auth->list_groups();
        $count  = count($groups);

        // pagination
        if (!empty($post['page_size']) && $count) {
            $size   = (int) $post['page_size'];
            $page   = !empty($post['page']) ? $post['page'] : 1;
            $page   = max(1, (int) $page);
            $offset = ($page - 1) * $size;

            $groups = array_slice($groups, $offset, $size, true);
        }

        return array(
            'list'  => $groups,
            'count' => $count,
        );
    }
}
