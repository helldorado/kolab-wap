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
        'type_list'  => 'type.list',
        'type.add'   => 'type.add',
    );

    protected $form_element_types = array(
        'text', 'select', 'multiselect', 'list', 'list-autocomplete', 'checkbox', 'password'
    );


    /**
     * Default action.
     */
    public function action_default()
    {
        $caps_actions = $this->get_capability('actions');

        // Display user info by default
        if (self::can_edit_self($caps_actions)) {
            $this->action_info();
        }
        // otherwise display object types list
        else if (self::can_edit_types($caps_actions)) {
            $this->output->set_object('content', 'type', true);
            $this->action_type_list();
            unset($this->menu['type_list']);

            // ... and type add form
            if (!empty($caps_actions['type.add'])) {
                $this->action_type_add();
            }
            else {
                $this->output->command('set_watermark', 'taskcontent');
            }
        }
        // fallback
        else {
            $this->output->command('set_watermark', 'content');
        }

        $this->output->set_object('task_navigation', $this->menu());
    }

    /**
     * Checks if it's possible to edit data of current user
     */
    private static function can_edit_self($caps_actions)
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
     * Checks if it's possible to edit object types
     */
    private static function can_edit_types($caps_actions)
    {
        // I think type management interface shouldn't be displayed at all
        // if user has no write rights to 'type' service
        if (!empty($caps_actions['type.edit']) || !empty($caps_actions['type.add']) || !empty($caps_actions['type.delete'])) {
            return true;
        }

        return false;
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

        if (self::can_edit_types($caps_actions)) {
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
            if (strpos($idx, '.') && !array_key_exists($idx, (array)$caps['actions'])) {
                continue;
            }

            $idx    = str_replace('.', '_', $idx);
            $action = 'settings.' . $idx;
            $class  = $idx;

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
        $user_task   = new kolab_client_task_user($this->output);
        $user_task->action_info();

        $this->output->set_object('content', $this->output->get_object('taskcontent'));
    }

    /**
     * Groups list action.
     */
    public function action_type_list()
    {
        $page_size = 20;
        $page      = (int) self::get_input('page', 'POST');
        if (!$page || $page < 1) {
            $page = 1;
        }

        // request parameters
        $post = array(
            'attributes' => array('name', 'key'),
//            'sort_order' => 'ASC',
            'sort_by'    => array('name', 'key'),
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

        // object type
        $type = self::get_input('type', 'POST');
        if (empty($type) || !in_array($type, $this->object_types)) {
            $type = 'user';
        }

        // get object types list
        $result = $this->object_types($type);

        // assign ID
        foreach (array_keys($result) as $idx) {
            $result[$idx]['id'] = $idx;
        }

        $result = array_values($result);
        $count  = count($result);

        // calculate records
        if ($count) {
            $start = 1 + max(0, $page - 1) * $page_size;
            $end   = min($start + $page_size - 1, $count);

            // sort and slice the result array

            if ($count > $page_size) {
                $result = array_slice($result, $start - 1, $page_size);
            }
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
                'content' => $this->translate('list.records', $start, $end, $count)), true);
            $prev = kolab_html::a(array(
                'class' => 'prev' . ($prev ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $prev ? "kadm.command('settings.type_list', {page: $prev})" : "return false",
            ));
            $next = kolab_html::a(array(
                'class' => 'next' . ($next ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $next ? "kadm.command('settings.type_list', {page: $next})" : "return false",
            ));

            $foot_body = kolab_html::span(array('content' => $prev . $count_str . $next));
        }
        $foot[0]['cells'][] = array('class' => 'listnav', 'body' => $foot_body);

        // table body
        if (!empty($result)) {
            foreach ($result as $idx => $item) {
                if (!is_array($item) || empty($item['name'])) {
                    continue;
                }

                $i++;
                $cells = array();
                $cells[] = array('class' => 'name', 'body' => kolab_html::escape($item['name']),
                    'onclick' => "kadm.command('settings.type_info', '$type:" . $item['id'] . "')");
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

        if ($this->action == 'type_list') {
            $this->output->command('set_watermark', 'taskcontent');
        }

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
        $id   = $this->get_input('id', 'POST');
        $data = array();

        list($type, $idx) = explode(':', $id);

        if ($idx && $type && ($result = $this->object_types($type))) {
            if (!empty($result[$idx])) {
                $data = $result[$idx];
            }
        }

        // prepare data for form
        if (!empty($data)) {
            $data['id']   = $idx;
            $data['type'] = $type;

            $data['objectclass'] = $data['attributes']['fields']['objectclass'];
            unset($data['attributes']['fields']['objectclass']);
        }

        $output = $this->type_form(null, $data);

        $this->output->set_object('taskcontent', $output);
    }

    /**
     * Groups adding (form) action.
     */
    public function action_type_add()
    {
        $data = $this->get_input('data', 'POST');

        if (empty($data['type'])) {
            $data['type'] = self::get_input('type', 'POST');
            if (empty($data['type']) || !in_array($data['type'], $this->object_types)) {
                $data['type'] = 'user';
            }
        }

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
            'id'            => 'props',
            'type'          => 'props',
            'key'           => 'props',
            'name'          => 'props',
            'description'   => 'props',
            'objectclass'   => 'props',
            'used_for'      => 'props',
            'attributes'    => 'attribs',
        );

        // Prepare fields
        $fields   = $this->type_form_prepare($data);
        $add_mode = empty($data['id']);
        $title    = $add_mode ? $this->translate('type.add') : $data['name'];

        // unset $data for correct form_create() run, we've got already data specified
        $effective_rights = $data['effective_rights'];
        $id = $data['id'] ? $data['type'].':'.$data['id'] : null;
        $data = array();
        $data['effective_rights'] = $effective_rights;
        $data['id'] = $id;

        // Create form object and populate with fields
        $form = $this->form_create('type', $attribs, $sections, $fields, $fields_map, $data, $add_mode);

        $form->set_title(kolab_html::escape($title));

        return $form->output();
    }

    /**
     * HTML Form elements preparation.
     *
     * @param array $data Object data
     *
     * @return array Fields list
     */
    protected function type_form_prepare(&$data)
    {
        // select top class by default for new type
        if (empty($data['objectclass'])) {
            $data['objectclass'] = array('top');
        }

        $name     = 'type';
        $add_mode = empty($data['id']);
        $fields   = array(
            'key' => array(
                'type' => kolab_form::INPUT_TEXT,
                'required' => true,
                'value' => $data['key'],
            ),
            'name' => array(
                'type' => kolab_form::INPUT_TEXT,
                'required' => true,
                'value' => $data['name'],
            ),
            'description' => array(
                'type'  => kolab_form::INPUT_TEXTAREA,
                'value' => $data['description'],
            ),
            'objectclass' => array(
                'type'     => kolab_form::INPUT_SELECT,
                'name'     => 'objectclass', // needed for form_element_select_data() below
                'multiple' => true,
                'required' => true,
                'value'    => $data['objectclass'],
                'onchange' => "kadm.type_attr_class_change(this)",
            ),
            'used_for' => array(
                'value'   => 'hosted',
                'type'    => kolab_form::INPUT_CHECKBOX,
                'checked' => !empty($data['used_for']) && $data['used_for'] == 'hosted',
            ),
            'attributes' => array(
                'type'    => kolab_form::INPUT_CONTENT,
                'content' => $this->type_form_attributes($data),
            ),
        );

        if ($data['type'] != 'user') {
            unset($form_fields['used_for']);
        }


        // Get the rights on the entry and attribute level
        $data['effective_rights'] = $this->effective_rights($name, $data['id']);
        $attribute_rights         = $data['effective_rights']['attribute'];
        $entry_rights             = $data['effective_rights']['entry'];

        // See if "administrators" (those who can delete and add back on the entry
        // level) may override the automatically generated contents of auto_form_fields.
        //$admin_auto_fields_rw = $this->config_get('admin_auto_fields_rw', false, Conf::BOOL);

        foreach ($fields as $idx => $field) {
            if (!array_key_exists($idx, $attribute_rights)) {
                // If the entry level rights contain 'add' and 'delete', well, you're an admin
                if (in_array('add', $entry_rights) && in_array('delete', $entry_rights)) {
                    if ($admin_auto_fields_rw) {
                        $fields[$idx]['readonly'] = false;
                    }
                }
                else {
                    $fields[$idx]['readonly'] = true;
                }
            }
            else {
                if (in_array('add', $entry_rights) && in_array('delete', $entry_rights)) {
                    if ($admin_auto_fields_rw) {
                        $fields[$idx]['readonly'] = false;
                    }
                }
                // Explicit attribute level rights, check for 'write'
                elseif (!in_array('write', $attribute_rights[$idx])) {
                    $fields[$idx]['readonly'] = true;
                }
            }
        }

        // (Re-|Pre-)populate auto_form_fields
        if (!$add_mode) {
            // Add debug information
            if ($this->devel_mode) {
                ksort($data);
                $debug = kolab_html::escape(print_r($data, true));
                $debug = preg_replace('/(^Array\n\(|\n*\)$|\t)/', '', $debug);
                $debug = str_replace("\n    ", "\n", $debug);
                $debug = '<pre class="debug">' . $debug . '</pre>';
                $fields['debug'] = array(
                    'label'   => 'debug',
                    'section' => 'props',
                    'value'   => $debug,
                );
            }
        }

        // Get object classes
        $sd = $this->form_element_select_data($fields['objectclass'], null, true);
        $fields['objectclass'] = array_merge($fields['objectclass'], $sd);

        // Add entry identifier
        if (!$add_mode) {
            $fields['id'] = array(
                'section'   => 'props',
                'type'      => kolab_form::INPUT_HIDDEN,
                'value'     => $data['id'],
            );
        }

        $fields['type'] = array(
            'section'   => 'props',
            'type'      => kolab_form::INPUT_HIDDEN,
            'value'     => $data['type'] ?: 'user',
        );

        return $fields;
    }

    /**
     * Type attributes table
     */
    private function type_form_attributes($data)
    {
        $attributes = array();
        $rows       = array();
        $attr_table = array();
        $table      = array(
            'id'    => 'type_attr_table',
            'class' => 'list',
        );
        $cells      = array(
            'name' => array(
                'body'  => $this->translate('attribute.name'),
            ),
            'type' => array(
                'body'  => $this->translate('attribute.type'),
            ),
            'readonly' => array(
                'body'  => $this->translate('attribute.readonly'),
            ),
            'optional' => array(
                'body'  => $this->translate('attribute.optional'),
            ),
            'value' => array(
                'body'  => $this->translate('attribute.value'),
            ),
            'actions' => array(
            ),
        );

        foreach ($cells as $idx => $cell) {
            $cells[$idx]['class'] = $idx;
        }

        // get attributes list from $data
        if (!empty($data) && count($data) > 1) {
            $attributes = array_merge(
                array_keys((array) $data['attributes']['auto_form_fields']),
                array_keys((array) $data['attributes']['form_fields']),
                array_keys((array) $data['attributes']['fields'])
            );
            $attributes = array_filter($attributes);
            $attributes = array_unique($attributes);
        }

        // get all available attributes
        $available = $this->type_attributes($data['objectclass']);

        // table header
        $table['head'] = array(array('cells' => $cells));

        $yes = $this->translate('yes');
        $no  = '';
        // defined attributes
        foreach ($attributes as $attr) {
            $row          = $cells;
            $type         = $data['attributes']['form_fields'][$attr]['type'];
            $optional     = $data['attributes']['form_fields'][$attr]['optional'];
            $autocomplete = $data['attributes']['form_fields'][$attr]['autocomplete'];
            $valtype      = 'normal';
            $value        = '';

            if ($type == 'list' && $autocomplete) {
                $type = 'list-autocomplete';
            }

            if ($data['attributes']['fields'][$attr]) {
                $valtype = 'static';
                $_data   = $data['attributes']['fields'][$attr];
                $value   = $this->translate('attribute.value.static') . ': ' . kolab_html::escape($_data);
            }
            else if (isset($data['attributes']['auto_form_fields'][$attr])) {
                $valtype = 'auto';
                if (is_array($data['attributes']['auto_form_fields'][$attr]['data'])) {
                    $_data = implode(',', $data['attributes']['auto_form_fields'][$attr]['data']);
                }
                else {
                    $_data = '';
                }
                $value = $this->translate('attribute.value.auto') . ': ' . kolab_html::escape($_data);

                if (empty($data['attributes']['form_fields'][$attr])) {
                    $valtype = 'auto-readonly';
                }
            }

            // set cell content
            $row['name']['body']     = !empty($available[$attr]) ? $available[$attr] : $attr;
            $row['type']['body']     = !empty($type) ? $type : 'text';
            $row['value']['body']    = $value;
            $row['readonly']['body'] = $valtype == 'auto-readonly' ? $yes : $no;
            $row['optional']['body'] = $optional ? $yes : $no;
            $row['actions']['body']  = 
                kolab_html::a(array('href' => '#delete', 'onclick' => "kadm.type_attr_delete('$attr')",
                    'class' => 'button delete', 'title' => $this->translate('delete')))
                . kolab_html::a(array('href' => '#edit', 'onclick' => "kadm.type_attr_edit('$attr')",
                    'class' => 'button edit', 'title' => $this->translate('edit')));

            $rows[] = array(
                'id'    => 'attr_table_row_' . $attr,
                'cells' => $row,
            );

            // data array for the UI
            $attr_table[$attr] = array(
                'type'     => !empty($type) ? $type : 'text',
                'valtype'  => $valtype,
                'optional' => $optional,
                'maxcount' => $data['attributes']['form_fields'][$attr]['maxcount'],
                'data'     => $_data,
                'values'   => $data['attributes']['form_fields'][$attr]['values'],
            );
        }

        // edit form
        $rows[] = array(
            'cells' => array(
                array(
                    'body'    => $this->type_form_attributes_form($available),
                    'colspan' => count($cells),
                ),
            ),
            'id' => 'type_attr_form',
        );

        $table['body'] = $rows;

        // sort attr_table by attribute name
        ksort($attr_table);

        // set environment variables
        $this->output->set_env('attr_table', $attr_table);
        $this->output->set_env('yes_label', $yes);
        $this->output->set_env('no_label', $no);
        $this->output->add_translation('attribute.value.auto', 'attribute.value.static',
            'attribute.key.invalid');

        // Add attribute link
        $link = kolab_html::a(array(
            'href' => '#add_attr', 'class' => 'add_attr',
            'onclick' => "kadm.type_attr_add()",
            'content' =>  $this->translate('attribute.add')), true);

        return kolab_html::table($table) . $link;
    }

    /**
     * Attributes edit form
     */
    private function type_form_attributes_form($attributes)
    {
        // build form
        $rows = array();
        $form = array(
            'name' => array(
                'type' => kolab_form::INPUT_SELECT,
                'options' => $attributes,
            ),
            'type' => array(
                'type' => kolab_form::INPUT_SELECT,
                'options' => array_combine($this->form_element_types, $this->form_element_types),
                'onchange' => 'kadm.type_attr_type_change(this)',
            ),
            'options' => array(
                'type'      => kolab_form::INPUT_TEXTAREA,
                'data-type' => kolab_form::TYPE_LIST,
            ),
            'maxcount' => array(
                'type' => kolab_form::INPUT_TEXT,
                'size' => 5,
            ),
            'value' => array(
                'type'  => kolab_form::INPUT_SELECT,
                'options' => array(
                    'normal'        => $this->translate('attribute.value.normal'),
                    'auto'          => $this->translate('attribute.value.auto'),
                    'auto-readonly' => $this->translate('attribute.value.auto-readonly'),
                    'static'        => $this->translate('attribute.value.static'),
                ),
                'onchange' => 'kadm.type_attr_value_change(this)',
            ),
            'optional' => array(
                'type'  => kolab_form::INPUT_CHECKBOX,
                'value' => 1,
            ),
        );

        foreach ($form as $idx => $element) {
            $element['name'] = 'attr_' . $idx;
            $body = kolab_form::get_element($element);

            if ($idx == 'value') {
                $body .= kolab_form::get_element(array(
                    'name' => 'attr_data',
                    'type' => kolab_form::INPUT_TEXT,
                ));
            }

            $rows[] = array(
                'id' => 'attr_form_row_' . $idx,
                'cells' => array(
                    array(
                        'class' => 'label',
                        'body'  => $this->translate('attribute.' . $idx),
                    ),
                    array(
                        'class' => 'value',
                        'body'  => $body,
                    ),
                ),
            );
        }

        $rows[] = array(
            'cells' => array(
                array(
                    'colspan' => 2,
                    'class' => 'formbuttons',
                    'body' => kolab_html::input(array(
                        'type'    => 'button',
                        'value'   => $this->translate('button.save'),
                        'onclick' => "kadm.type_attr_save()",
                    ))
                    . kolab_html::input(array(
                        'type'    => 'button',
                        'value'   => $this->translate('button.cancel'),
                        'onclick' => "kadm.type_attr_cancel()",
                    )),
                ),
            ),
        );

        $table = array(
            'class' => 'form',
            'body'  => $rows,
        );

        return kolab_html::table($table);
    }

    /**
     * Returns list of LDAP attributes for specified opject classes.
     */
    public function type_attributes($object_class = null)
    {
        $post_data = array(
            'attributes' => array('attribute'),
            'classes'    => $object_class,
        );

        // get all available attributes
        $response   = $this->api->post('form_value.select_options', null, $post_data);
        $response   = $response->get('attribute');
        $attributes = array();

        // convert to hash array
        if (!empty($response['list'])) {
            $attributes = array_combine(array_map('strtolower', $response['list']), $response['list']);
        }

        $this->output->set_env('attributes', $attributes);
        // @TODO: check if all required attributes are used
//        $this->output->set_env('attributes_required', $attributes['required']);

        return $attributes;
    }

    /**
     * Users search form.
     *
     * @return string HTML output of the form
     */
    public function type_search_form()
    {
        $form = new kolab_form(array('id' => 'search-form'));

        $form->add_section('criteria', kolab_html::escape($this->translate('search.criteria')));
        $form->add_element(array(
            'section' => 'criteria',
            'label'   => $this->translate('search.field'),
            'name'    => 'field',
            'type'    => kolab_form::INPUT_SELECT,
            'options' => array(
                'name'        => kolab_html::escape($this->translate('search.name')),
                'key'         => kolab_html::escape($this->translate('search.key')),
                'description' => kolab_html::escape($this->translate('search.description')),
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

    /**
     * Users search form.
     *
     * @return string HTML output of the form
     */
    public function type_filter()
    {
        $options = array();

        foreach ($this->object_types as $type) {
            $options[$type] = $this->translate('type.' . $type);
        }

        $filter = array(
            'type'     => kolab_form::INPUT_SELECT,
            'name'     => 'type',
            'id'       => 'type_list_filter',
            'options'  => $options,
            'value'    => $this->type_selected ? $this->type_selected : 'user',
            'onchange' => "kadm.command('settings.type_list')",
        );

        return kolab_form::get_element($filter);
    }
}
