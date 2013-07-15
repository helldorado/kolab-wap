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

class kolab_client_task_domain extends kolab_client_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'add'  => 'domain.add',
    );

    /**
     * Default action.
     */
    public function action_default()
    {
        $this->output->set_object('content', 'domain', true);
        $this->output->set_object('task_navigation', $this->menu());

        $this->action_list();

        // display form to add domain if logged-in user has right to do so
        $caps = $this->get_capability('actions');
        if (!empty($caps['domain.add'])) {
            $this->action_add();
        }
        else {
            $this->output->command('set_watermark', 'taskcontent');
        }
    }

    /**
     * Groups list action.
     */
    public function action_list()
    {
        if (!empty($_POST['refresh'])) {
            // refresh domains list
            if ($domains = $this->get_domains(true)) {
                sort($domains, SORT_LOCALE_STRING);
                $this->output->set_env('domains', $domains);
            }
        }

        $page_size = 20;
        $page      = (int) self::get_input('page', 'POST');
        if (!$page || $page < 1) {
            $page = 1;
        }

        // request parameters
        $post = array(
            'attributes' => array('associateddomain'),
//            'sort_order' => 'ASC',
            'sort_by'    => 'associateddomain',
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

        // get domains list
        $result = $this->api_post('domains.list', null, $post);
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
        $head[0]['cells'][] = array('class' => 'name', 'body' => $this->translate('domain.list'));

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
                'onclick' => $prev ? "kadm.command('domain.list', {page: $prev})" : "return false",
            ));
            $next = kolab_html::a(array(
                'class' => 'next' . ($next ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $next ? "kadm.command('domain.list', {page: $next})" : "return false",
            ));

            $foot_body = kolab_html::span(array('content' => $prev . $count_str . $next));
        }
        $foot[0]['cells'][] = array('class' => 'listnav', 'body' => $foot_body);

        // table body
        if (!empty($result)) {
            foreach ($result as $idx => $item) {
                //console($idx);
                if (!is_array($item) || empty($item['associateddomain'])) {
                    continue;
                }

                $i++;
                $cells = array();

                if (is_array($item['associateddomain'])) {
                    $domain_name = $item['associateddomain'][0];
                } else {
                    $domain_name = $item['associateddomain'];
                }

                $cells[] = array('class' => 'name', 'body' => kolab_html::escape($domain_name),
                    'onclick' => "kadm.command('domain.info', '$idx')");
                $rows[] = array('id' => $i, 'class' => 'selectable', 'cells' => $cells);
            }
        }
        else {
            $rows[] = array('cells' => array(
                0 => array('class' => 'empty-body', 'body' => $this->translate('domain.norecords')
            )));
        }

        $table = kolab_html::table(array(
            'id'    => 'domainlist',
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
        $this->output->set_env('list_size', $i);
        $this->output->set_object('domainlist', $table);
    }

    /**
     * Domain information (form) action.
     */
    public function action_info()
    {
        $id     = $this->get_input('id', 'POST');
        //console("action_info() on", $id);

        $result = $this->api_get('domain.info', array('id' => $id));
        //console("action_info() \$result", $result);

        $domain  = $result->get();
        //console("action_info() \$domain", $domain);

        $output = $this->domain_form(array_keys($domain), $domain);

        $this->output->set_object('taskcontent', $output);
    }

    /**
     * Domain adding (form) action.
     */
    public function action_add()
    {
        $data   = $this->get_input('data', 'POST');
        $output = $this->domain_form(null, $data, true);

        $this->output->set_object('taskcontent', $output);
    }

    /**
     * Domain edit/add form.
     */
    private function domain_form($attribs, $data = array())
    {
        if (empty($attribs['id'])) {
            $attribs['id'] = 'domain-form';
        }

        // Form sections
        $sections = array(
            'system'   => 'domain.system',
            'other'    => 'domain.other',
            'admins'    => 'domain.admins',
        );

        // field-to-section map and fields order
        $fields_map = array(
            'type_id'           => 'system',
            'type_id_name'      => 'system',
            'associateddomain'  => 'system',
            'domainadmin'       => 'admins',
        );

        //console("domain_form() \$data", $data);

        // Prepare fields
        list($fields, $types, $type) = $this->form_prepare('domain', $data);

        //console("Result from form_prepare", $fields, $types, $type);

        $add_mode  = empty($data['id']);
        $accttypes = array();

        foreach ($types as $idx => $elem) {
            $accttypes[$idx] = array('value' => $idx, 'content' => $elem['name']);
        }

        // Add domain type id selector
        $fields['type_id'] = array(
            'section'  => 'system',
            'type'     => kolab_form::INPUT_SELECT,
            'options'  => $accttypes,
            'onchange' => "kadm.domain_save(true, 'system')",
        );

        // Hide account type selector if there's only one type
        if (count($accttypes) < 2 || !$add_mode) {
            $fields['type_id']['type'] = kolab_form::INPUT_HIDDEN;
        }

        // Create mode
        if ($add_mode) {
            // Page title
            $title = $this->translate('domain.add');
        }
        // Edit mode
        else {
            if (array_key_exists('primary_domain', $data)) {
                $title = $data['primary_domain'];
            }
            // TODO: Domain name attribute.
            else if (!is_array($data['associateddomain'])) {
                $title = $data['associateddomain'];
            }
            else {
                $title = $data['associateddomain'][0];
            }

            // Add domain type name
            $fields['type_id_name'] = array(
                'label'    => 'domain.type_id',
                'section'  => 'system',
                'value'    => $accttypes[$type]['content'],
            );
        }

        // load all domain admins, ie. all users from the default domain
        $param = array();
        $param['attributes'] = array('domainadmin');
        $resp = $this->api_post('form_value.select_options', null, $param);
        $resp = $resp->get('domainadmin');

        $default         = $resp['default'];
        $data['domainadmin_options'] = $resp['list'];

        // Create form object and populate with fields
        $form = $this->form_create('domain', $attribs, $sections, $fields, $fields_map, $data, $add_mode);

        //console("domain_form() \$form", $form);

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
                'associateddomain' => kolab_html::escape($this->translate('search.name')),
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
