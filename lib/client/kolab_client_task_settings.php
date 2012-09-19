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

class kolab_client_task_settings extends kolab_client_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'type_list'  => 'types.list',
        'type_add'   => 'type.add',
    );

    /**
     * Default action.
     */
    public function action_default()
    {
        $this->output->set_object('task_navigation', $this->menu());
//        $this->output->set_object('content', 'settings', true);

        $caps_actions = $this->get_capability('actions');
        if (self::can_edit_self($caps_actions)) {
            $this->action_info();
        }
        else {
            $this->output->command('set_watermark', 'content');
        }
    }

    /**
     * Checks if it's possible to edit data of current user
     */
    public static function can_edit_self($caps_actions)
    {
        // Disable user form for directory manager (see #1025)
        if (preg_match('/^cn=([a-z ]+)/i', $_SESSION['user']['id'])) {
            return false;
        }

        if (empty($caps_actions['user.info'])) {
            return false;
        }

        // If we can do user.info, we can at least display
        // the form, effective rights will be checked later
        // there's a very small chance that user cannot view his data
        return true;
    }

    /**
     * Check if any of task actions is accessible for current user
     *
     * @return bool
     */
    public static function is_enabled($caps_actions)
    {
        // User form
        if (self::can_edit_self($caps_actions)) {
            return true;
        }

        if (!empty($caps_actions['types.list'])) {
            return true;
        }

        return false;
    }

    /**
     * Returns task menu output (overrides parent's menu method).
     *
     * @return string HTML output
     */
    protected function menu()
    {
        $caps = $this->capabilities();
        $menu = array();

        foreach ($this->menu as $idx => $label) {
            if (!array_key_exists($idx, (array)$caps['actions'])) {
                continue;
            }

            if (strpos($idx, '.')) {
                $action = $idx;
                $class  = preg_replace('/\.[a-z_-]+$/', '', $idx);
            }
            else {
                $action = $task . '.' . $idx;
                $class  = $idx;
            }

            $menu[$idx] = sprintf('<li class="%s">'
                .'<a href="#%s" onclick="return kadm.command(\'%s\', \'\', this)">%s</a></li>',
                $class, $idx, $action, $this->translate($label));
        }

        return '<ul>' . implode("\n", $menu) . '</ul>';
    }

    /**
     * User info action.
     */
    public function action_info()
    {
        $_POST['id'] = $_SESSION['user']['id'];
        $user_task    = new kolab_client_task_user($this->output);
        $user_task->action_info();

        $this->output->set_object('content', $this->output->get_object('taskcontent'));
    }

    /**
     * Groups list action.
     */
    public function action_types_list()
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

        // get groups list
        $result = $this->api->post('types.list', null, $post);
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
        $head[0]['cells'][] = array('class' => 'name', 'body' => $this->translate('type.list'));

        // table footer (navigation)
        if ($count) {
            $pages = ceil($count / $page_size);
            $prev  = max(0, $page - 1);
            $next  = $page < $pages ? $page + 1 : 0;

            $count_str = kolab_html::span(array(
                'content' => $this->translate('type.list.records', $start, $end, $count)), true);
            $prev = kolab_html::a(array(
                'class' => 'prev' . ($prev ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $prev ? "kadm.command('type.list', {page: $prev})" : "return false",
            ));
            $next = kolab_html::a(array(
                'class' => 'next' . ($next ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $next ? "kadm.command('type.list', {page: $next})" : "return false",
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
                    'onclick' => "kadm.command('type.info', '$idx')");
                $rows[] = array('id' => $i, 'class' => 'selectable', 'cells' => $cells);
            }
        }
        else {
            $rows[] = array('cells' => array(
                0 => array('class' => 'empty-body', 'body' => $this->translate('type.norecords')
            )));
        }

        $table = kolab_html::table(array(
            'id'    => 'typelist',
            'class' => 'list',
            'head'  => $head,
            'body'  => $rows,
            'foot'  => $foot,
        ));

        $this->output->set_env('search_request', $search_request ? base64_encode(serialize($search_request)) : null);
        $this->output->set_env('list_page', $page);
        $this->output->set_env('list_count', $count);
        $this->output->set_object('typelist', $table);
    }

    /**
     * Group information (form) action.
     */
    public function action_type_info()
    {
        $id     = $this->get_input('id', 'POST');
        $result = $this->api->get('type.info', array('type' => $id));
        $type   = $result->get();
        $output = $this->group_form(null, $type);

        $this->output->set_object('taskcontent', $output);
    }

    /**
     * Groups adding (form) action.
     */
    public function action_type_add()
    {
        $data   = $this->get_input('data', 'POST');
        $output = $this->type_form(null, $data, true);

        $this->output->set_object('taskcontent', $output);
    }

    /**
     * Group edit/add form.
     */
    private function type_form($attribs, $data = array())
    {
        if (empty($attribs['id'])) {
            $attribs['id'] = 'type-form';
        }

        // Form sections
        $sections = array(
            'props'   => 'type.properties',
            'attribs' => 'type.attributes',
        );

        // field-to-section map and fields order
        $fields_map = array(
            'type_id'       => 'props',
            'objectclasses' => 'props',
            'type_id_name'  => 'attribs',
        );

        // Prepare fields
        list($fields, $types, $type) = $this->form_prepare('type', $data);

        $add_mode = empty($data['id']);

        // Add type id selector
        $fields['type_id'] = array(
            'section'  => 'props',
            'type'     => kolab_form::INPUT_HIDDEN,
        );

        // Create mode
        if ($add_mode) {
            // Page title
            $title = $this->translate('type.add');
        }
        // Edit mode
        else {
            $title = $data['cn'];
        }

        // Create form object and populate with fields
        $form = $this->form_create('type', $attribs, $sections, $fields, $fields_map, $data, $add_mode);

        $form->set_title(kolab_html::escape($title));

        $this->output->add_translation('type.add.success', 'type.edit.success', 'type.delete.success');

        return $form->output();
    }

    /**
     * Users search form.
     *
     * @return string HTML output of the form
     */
    public function search_form()
    {
        $form = new kolab_form(array('id' => 'search-form'));
/*
        $form->add_section('criteria', kolab_html::escape($this->translate('search.criteria')));
        $form->add_element(array(
            'section' => 'criteria',
            'label'   => $this->translate('search.field'),
            'name'    => 'field',
            'type'    => kolab_form::INPUT_SELECT,
            'options' => array(
                'cn'   => kolab_html::escape($this->translate('search.name')),
                'mail' => kolab_html::escape($this->translate('search.email')),
            ),
        ));
        $form->add_element(array(
            'section' => 'criteria',
            'label'   => $this->translate('search.method'),
            'name'    => 'method',
            'type'    => kolab_form::INPUT_SELECT,
            'options' => array(
                'both'   => kolab_html::escape($this->translate('search.contains')),
                'exact'  => kolab_html::escape($this->translate('search.is')),
                'prefix' => kolab_html::escape($this->translate('search.prefix')),
            ),
        ));
*/
        return $form->output();
    }

}
