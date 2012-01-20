<?php

/**
 *
 */
class kolab_users_actions extends kolab_api_service
{
    public $list_attribs = array(
        'uid',
        'cn',
        'displayname',
        'sn',
        'givenname',
        'mail',
        'objectclass',
        'uidnumber',
        'gidnumber',
        'mailhost',
    );


    public function capabilities($domain)
    {
        return array(
            'list' => 'r',
        );
    }

    public function users_list($get, $post)
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

        $search = array();
        $params = array();

        // searching
        if (!empty($post['search']) && is_array($post['search'])) {
            $params = $post['search'];
            foreach ($params as $idx => $param) {
                // get only supported attributes
                if (!in_array($idx, $this->list_attribs)) {
                    unset($params[$idx]);
                    continue;
                }

                // search string
                if (empty($param['value'])) {
                    unset($params[$idx]);
                    continue;
                }
            }

            $search['params'] = $params;
            if (!empty($post['search_operator'])) {
                $search['operator'] = $post['search_operator'];
            }
        }

        if (!empty($post['sort_by'])) {
            // check if sort attribute is supported
            if (in_array($post['sort_by'], $this->list_attribs)) {
                $params['sort_by'] = $post['sort_by'];
            }
        }

        if (!empty($post['sort_order'])) {
            $params['sort_order'] = $post['sort_order'] == 'DESC' ? 'DESC' : 'ASC';
        }

        $users = $auth->list_users(null, $attributes, $search, $params);
        $count = count($users);

        // pagination
        if (!empty($post['page_size']) && $count) {
            $size   = (int) $post['page_size'];
            $page   = !empty($post['page']) ? $post['page'] : 1;
            $page   = max(1, (int) $page);
            $offset = ($page - 1) * $size;

            $users = array_slice($users, $offset, $size, true);
        }

        return array(
            'list'  => $users,
            'count' => $count,
        );
    }

}
