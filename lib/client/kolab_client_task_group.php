<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab Web Admin Panel                           |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 +--------------------------------------------------------------------------+
*/

class kolab_client_task_group extends kolab_client_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'add'  => 'group.add',
    );

    /**
     * Default action.
     */
    public function action_default()
    {
        $this->output->set_object('content', 'group', true);
        $this->output->set_object('task_navigation', $this->menu());

        $this->action_list();
    }

    /**
     * Groups list action.
     */
    public function action_list()
    {
        $page_size = 20;
        $page      = (int) self::get_input('page', 'POST');
        if (!$page || $page < 1) {
            $page = 1;
        }

        // request parameters
        $post = array(
            'attributes' => array('cn'),
//            'sort_order' => 'ASC',
            'sort_by'    => 'cn',
            'page_size'  => $page_size,
            'page'       => $page,
        );

/*
        // search parameters
        if (!empty($_POST['search'])) {
            $search = self::get_input('search', 'POST', true);
            $field  = self::get_input('field',  'POST');
            $method = self::get_input('method', 'POST');

            $search_request = array(
                $field => array(
                    'value' => $search,
                    'type'  => $method,
                ),
            );
        }
        else if (!empty($_POST['search_request'])) {
            $search_request = self::get_input('search_request', 'POST');
            $search_request = @unserialize(base64_decode($search_request));
        }

        if (!empty($search_request)) {
            $post['search']          = $search_request;
            $post['search_operator'] = 'OR';
        }
*/
        // get groups list
        $result = $this->api->post('groups.list', null, $post);
        $count  = (int) $result->get('count');
        $result = (array) $result->get('list');

        // calculate records
        if ($count) {
            $start = 1 + max(0, $page - 1) * $page_size;
            $end   = min($start + $page_size - 1, $count);
        }

        $rows = $head = $foot = array();
        $cols = array('name');
        $i    = 0;

        // table header
        $head[0]['cells'][] = array('class' => 'name', 'body' => $this->translate('group.list'));

        // table footer (navigation)
        if ($count) {
            $pages = ceil($count / $page_size);
            $prev  = max(0, $page - 1);
            $next  = $page < $pages ? $page + 1 : 0;

            $count_str = kolab_html::span(array(
                'content' => $this->translate('group.list.records', $start, $end, $count)), true);
            $prev = kolab_html::a(array(
                'class' => 'prev' . ($prev ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $prev ? "kadm.command('group.list', {page: $prev})" : "return false",
            ));
            $next = kolab_html::a(array(
                'class' => 'next' . ($next ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $next ? "kadm.command('group.list', {page: $next})" : "return false",
            ));

            $foot_body = kolab_html::span(array('content' => $prev . $count_str . $next));
        }
        $foot[0]['cells'][] = array('class' => 'listnav', 'body' => $foot_body);

        // table body
        if (!empty($result)) {
            foreach ($result as $idx => $item) {
                if (!is_array($item) || empty($item['cn'])) {
                    continue;
                }

                $i++;
                $cells = array();
                $cells[] = array('class' => 'name', 'body' => kolab_html::escape($item['cn']),
                    'onclick' => "kadm.command('group.info', '$idx')");
                $rows[] = array('id' => $i, 'class' => 'selectable', 'cells' => $cells);
            }
        }
        else {
            $rows[] = array('cells' => array(
                0 => array('class' => 'empty-body', 'body' => $this->translate('group.norecords')
            )));
        }

        $table = kolab_html::table(array(
            'id'    => 'grouplist',
            'class' => 'list',
            'head'  => $head,
            'body'  => $rows,
            'foot'  => $foot,
        ));

        $this->output->set_env('search_request', $search_request ? base64_encode(serialize($search_request)) : null);
        $this->output->set_env('list_page', $page);
        $this->output->set_env('list_count', $count);

        $this->watermark('taskcontent');
        $this->output->set_object('grouplist', $table);
    }

    /**
     * Group information (form) action.
     */
    public function action_info()
    {
        $id     = $this->get_input('id', 'POST');
        $result = $this->api->get('group.info', array('group' => $id));
        $group  = $result->get($id);
        $group['group'] = $id;

        $this->output->set_object('taskcontent', print_r($group, true));
    }

    public function group_add()
    {
    
    }

    public function search_form()
    {
        return '';
    }
}
