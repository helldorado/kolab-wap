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

        if (!empty($post['attributes']) && is_array($post['attributes'])) {
            // get only supported attributes
            $attributes = array_intersect($this->list_attribs, $post['attributes']);
            // need to fix array keys
            $attributes = array_values($attributes);
        }
        if (empty($attributes)) {
            $attributes = (array)$this->list_attribs[0];
        }

        $users = $auth->list_users(null, $attributes);
        $users = $auth->normalize_result($users);

        return $users;
    }

}
