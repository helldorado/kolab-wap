<?php

/**
 *
 */
class kolab_groups_actions extends kolab_api_service
{
    public $list_attribs = array(
        'cn',
        'gidnumber',
        'objectclass',
        'mail',
    );

    public function capabilities($domain)
    {
        return array(
            'list' => 'r',
            );
    }

    public function groups_list($get, $post)
    {
        $auth = Auth::get_instance();

        // returned attributes
        if (!empty($post['attributes']) && is_array($post['attributes'])) {
            // get only supported attributes
            $attributes = array_intersect($this->list_attribs, $post['attributes']);
            // need to fix array keys
            $attributes = array_values($attributes);
        }
        if (empty($attributes)) {
            $attributes = (array)$this->list_attribs[0];
        }

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
