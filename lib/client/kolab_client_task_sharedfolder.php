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

class kolab_client_task_sharedfolder extends kolab_client_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'add'  => 'sharedfolder.add',
    );

    /**
     * Default action.
     */
    public function action_default()
    {
        $this->output->set_object('content', 'sharedfolder', true);
        $this->output->set_object('task_navigation', $this->menu());

        $this->action_list();

        // display form to add a shared folder if logged-in user has right to do so
        $caps = $this->get_capability('actions');
        if (!empty($caps['sharedfolder.add'])) {
            $this->action_add();
        }
        else {
            $this->output->command('set_watermark', 'taskcontent');
        }
    }

    /**
     * Users list action.
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
            'sort_by'    => array('cn'),
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

        // get shared folders list
        $result = $this->api_post('sharedfolders.list', null, $post);
        $count  = $result->get('count');
        $result = (array) $result->get('list');

        //console($result);

        // calculate records
        if ($count) {
            $start = 1 + max(0, $page - 1) * $page_size;
            $end   = min($start + $page_size - 1, $count);
        }

        $rows = $head = $foot = array();
        $cols = array('name');
        $i    = 0;

        // table header
        $head[0]['cells'][] = array('class' => 'name', 'body' => $this->translate('sharedfolder.list'));

        // table footer (navigation)
        if ($count) {
            $pages = ceil($count / $page_size);
            $prev  = max(0, $page - 1);
            $next  = $page < $pages ? $page + 1 : 0;

            $count_str = kolab_html::span(array(
                'content' => $this->translate('list.records', $start, $end, $count)), true);
            $prev = kolab_html::a(array(
                'class' => 'prev' . ($prev ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $prev ? "kadm.command('sharedfolder.list', {page: $prev})" : "return false",
            ));
            $next = kolab_html::a(array(
                'class' => 'next' . ($next ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $next ? "kadm.command('sharedfolder.list', {page: $next})" : "return false",
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
                    'onclick' => "kadm.command('sharedfolder.info', '$idx')");
                $rows[] = array('id' => $i, 'class' => 'selectable', 'cells' => $cells);
            }
        }
        else {
            $rows[] = array('cells' => array(
                0 => array('class' => 'empty-body', 'body' => $this->translate('sharedfolder.norecords')
            )));
        }

        $table = kolab_html::table(array(
            'id'    => 'sharedfolderlist',
            'class' => 'list',
            'head'  => $head,
            'body'  => $rows,
            'foot'  => $foot,
        ));

        if ($this->action == 'list') {
            $this->output->command('set_watermark', 'taskcontent');
        }

        $this->output->set_env('search_request', $search_request ? base64_encode(serialize($search_request)) : null);
        $this->output->set_env('list_page', $page);
        $this->output->set_env('list_count', $count);
        $this->output->set_env('list_size', count($result));
        $this->output->set_object('sharedfolderlist', $table);
    }

    /**
     * Resource adding (form) action.
     */
    public function action_add()
    {
        $data   = $this->get_input('data', 'POST');
        $output = $this->sharedfolder_form(null, $data, true);

        $this->output->set_object('taskcontent', $output);
    }

    /**
     * Resource information (form) action.
     */
    public function action_info()
    {
        $id             = $this->get_input('id', 'POST');
        $result         = $this->api_get('sharedfolder.info', array('id' => $id));
        $sharedfolder   = $result->get();

        //console("action_info()", $sharedfolder);

        $output     = $this->sharedfolder_form(null, $sharedfolder);

        $this->output->set_object('taskcontent', $output);
    }

    private function sharedfolder_form($attribs, $data = array())
    {
        if (empty($attribs['id'])) {
            $attribs['id'] = 'sharedfolder-form';
        }

        //console("sharedfolder_form(\$attribs, \$data)", $attribs, $data);

        // Form sections
        $sections = array(
            'system'        => 'sharedfolder.system',
            'other'         => 'sharedfolder.other',
        );

        // field-to-section map and fields order
        $fields_map = array(
            'type_id'                   => 'system',
            'type_id_name'              => 'system',

            'cn'                        => 'system',
            'ou'                        => 'system',
            'preferredlanguage'         => 'system',

            'mail'                      => 'system',
            'alias'                     => 'system',
            'mailalternateaddress'      => 'system',

            'member'                    => 'system',
            'uniquemember'              => 'system',
            'memberurl'                 => 'system',

            'nsrole'                    => 'system',
            'nsroledn'                  => 'system',

            /* Kolab Settings */
            'kolabhomeserver'           => 'system',
            'mailhost'                  => 'system',
            'mailquota'                 => 'system',
            'kolabfreebusyfuture'       => 'system',
            'kolabinvitationpolicy'     => 'system',
            'kolabdelegate'             => 'system',
            'kolaballowsmtprecipient'   => 'system',
            'kolaballowsmtpsender'      => 'system',
        );

        // Prepare fields
        list($fields, $types, $type) = $this->form_prepare('sharedfolder', $data);

        //console("Result from form_prepare", $fields, $types, $type);

        $add_mode  = empty($data['id']);
        $accttypes = array();

        foreach ($types as $idx => $elem) {
            $accttypes[$idx] = array('value' => $idx, 'content' => $elem['name']);
        }

        // Add sharedfolder type id selector
        $fields['type_id'] = array(
            'section'  => 'system',
            'type'     => kolab_form::INPUT_SELECT,
            'options'  => $accttypes,
            'onchange' => "kadm.sharedfolder_save(true, 'system')",
        );

        //console($accttypes);

        // Hide account type selector if there's only one type
        if (count($accttypes) < 2 || !$add_mode) {
            //console("setting type_id form type to hidden");
            $fields['type_id']['type'] = kolab_form::INPUT_HIDDEN;
        }

        // Create mode
        if ($add_mode) {
            // Page title
            $title = $this->translate('sharedfolder.add');
        }
        // Edit mode
        else {
            $title = $data['cn'];

            // Add sharedfolder type name
            $fields['type_id_name'] = array(
                'label'    => 'sharedfolder.type_id',
                'section'  => 'system',
                'value'    => $accttypes[$type]['content'],
            );
        }

        // Create form object and populate with fields
        $form = $this->form_create('sharedfolder', $attribs, $sections, $fields, $fields_map, $data, $add_mode);

        $form->set_title(kolab_html::escape($title));

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

        $form->add_section('criteria', kolab_html::escape($this->translate('search.criteria')));
        $form->add_element(array(
            'section' => 'criteria',
            'label'   => $this->translate('search.field'),
            'name'    => 'field',
            'type'    => kolab_form::INPUT_SELECT,
            'options' => array(
                'cn'    => kolab_html::escape($this->translate('search.name')),
                'email' => kolab_html::escape($this->translate('search.email')),
                'uid'   => kolab_html::escape($this->translate('search.uid')),
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

        return $form->output();
    }
}
