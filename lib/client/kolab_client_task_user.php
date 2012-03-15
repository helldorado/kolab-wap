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

class kolab_client_task_user extends kolab_client_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'add'  => 'user.add',
    );

    /**
     * Default action.
     */
    public function action_default()
    {
        $this->output->set_object('content', 'user', true);
        $this->output->set_object('task_navigation', $this->menu());

        $this->action_list();
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
            'attributes' => array('displayname'),
//            'sort_order' => 'ASC',
            'sort_by'    => 'displayname',
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

        // get users list
        $result = $this->api->post('users.list', null, $post);
        $count  = $result->get('count');
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
        $head[0]['cells'][] = array('class' => 'name', 'body' => $this->translate('user.list'));

        // table footer (navigation)
        if ($count) {
            $pages = ceil($count / $page_size);
            $prev  = max(0, $page - 1);
            $next  = $page < $pages ? $page + 1 : 0;

            $count_str = kolab_html::span(array(
                'content' => $this->translate('user.list.records', $start, $end, $count)), true);
            $prev = kolab_html::a(array(
                'class' => 'prev' . ($prev ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $prev ? "kadm.command('user.list', {page: $prev})" : "return false",
            ));
            $next = kolab_html::a(array(
                'class' => 'next' . ($next ? '' : ' disabled'),
                'href'  => '#',
                'onclick' => $next ? "kadm.command('user.list', {page: $next})" : "return false",
            ));

            $foot_body = kolab_html::span(array('content' => $prev . $count_str . $next));
        }
        $foot[0]['cells'][] = array('class' => 'listnav', 'body' => $foot_body);

        // table body
        if (!empty($result)) {
            foreach ($result as $idx => $item) {
                if (!is_array($item) || empty($item['displayname'])) {
                    continue;
                }

                $i++;
                $cells = array();
                $cells[] = array('class' => 'name', 'body' => kolab_html::escape($item['displayname']),
                    'onclick' => "kadm.command('user.info', '$idx')");
                $rows[] = array('id' => $i, 'class' => 'selectable', 'cells' => $cells);
            }
        }
        else {
            $rows[] = array('cells' => array(
                0 => array('class' => 'empty-body', 'body' => $this->translate('user.norecords')
            )));
        }

        $table = kolab_html::table(array(
            'id'    => 'userlist',
            'class' => 'list',
            'head'  => $head,
            'body'  => $rows,
            'foot'  => $foot,
        ));

        $this->output->set_env('search_request', $search_request ? base64_encode(serialize($search_request)) : null);
        $this->output->set_env('list_page', $page);
        $this->output->set_env('list_count', $count);

        $this->watermark('taskcontent');
        $this->output->set_object('userlist', $table);
    }

    /**
     * User information (form) action.
     */
    public function action_info()
    {
        $id     = $this->get_input('id', 'POST');
        $result = $this->api->get('user.info', array('user' => $id));
        $user   = $result->get($id);
        $user['user'] = $id;
        $output = $this->user_form(null, $user);

        $this->output->set_object('taskcontent', $output);
    }

    /**
     * Users adding (form) action.
     */
    public function action_add()
    {
        $data   = $this->get_input('data', 'POST');
        $output = $this->user_form(null, $data, true);

        $this->output->set_object('taskcontent', $output);
    }

    private function user_form($attribs, $data = array())
    {
        if (empty($attribs['id'])) {
            $attribs['id'] = 'user-form';
        }

        $form      = new kolab_form($attribs);
        $utypes    = (array) $this->user_types();
        $form_id   = $attribs['id'];
        $add_mode  = empty($data['user']);
        $accttypes = array();

        foreach ($utypes as $idx => $elem) {
            $accttypes[$idx] = array('value' => $idx, 'content' => $elem['name']);
        }
/*
        $fields = array(
            'personal' => array(
                'label' => 'user.personal',
                'fields' => array(
                    'givenname' => array(
                        'label'       => 'user.givenname',
                        'description' => 'user.givenname.desc',
                        'required'    => true,
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'sn' => array(
                        'label'       => 'user.surname',
                        'description' => 'user.surname.desc',
                        'required'    => true,
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'displayname' => array(
                        'label'       => 'user.displayname',
                        'description' => 'user.displayname.desc',
                        'required'    => true,
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'initials' => array(
                        'label'       => 'user.initials',
                        'description' => 'user.initials.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'title' => array(
                        'label'       => 'user.title',
                        'description' => 'user.title.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 10,
                    ),
                    'telephoneNumber' => array(
                        'label' => 'user.phone',
                        'description' => 'user.phone.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'facsimileTelephoneNumber' => array(
                        'label' => 'user.fax',
                        'description' => 'user.fax.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'o' => array(
                        'label' => 'user.org',
                        'description' => 'user.org.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'ou' => array(
                        'label' => 'user.orgunit',
                        'description' => 'user.orgunit.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'roomNumber' => array(
                        'label' => 'user.room',
                        'description' => 'user.room.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 10,
                    ),
                    'street' => array(
                        'label' => 'user.street',
                        'description' => 'user.street.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'l' => array(
                        'label' => 'user.city',
                        'description' => 'user.city.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                    ),
                    'postOfficeBox' => array(
                        'label' => 'user.postbox',
                        'description' => 'user.postbox.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 20,
                    ),
                    'postalCode' => array(
                        'label' => 'user.postcode',
                        'description' => 'user.postcode.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 10,
                    ),
                    'c' => array(
                        'label' => 'user.country',
                        'description' => 'user.country.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 2,
                    ),
                ),
            ),
            'system' => array(
                'label' => 'user.system',
                'fields' => array(
                    'mail' => array(
                        'label' => 'user.email',
                        'description' => 'user.email.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                        'required'    => true,
                    ),
                    'uid' => array(
                        'label' => 'user.uid',
                        'description' => 'user.uid.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                        'required'    => true,
                    ),
                    'userpassword' => array(
                        'label' => 'user.password',
                        'description' => 'user.password.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                        'required'    => $add_mode ? true : false,
                        'system'      => true,
                    ),
                    'userpassword2' => array(
                        'label' => 'user.password-confirm',
                        'description' => 'user.password-confirm.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                        'required'    => $add_mode ? true : false,
                        'system'      => true,
                    ),
                    'kolabhomeserver' => array(
                        'label' => 'user.homeserver',
                        'description' => 'user.homeserver.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                        'required'    => true,
                    ),
                    'user_type_id' => array(
                        'label' => 'user.type',
                        'description' => 'user.type.desc',
                        'type'        => kolab_form::INPUT_SELECT,
                        'options'     => $accttypes,
                        'system'      => $add_mode ? true : false,
                        'onchange'    => "kadm.user_save(true, 'system')",
                    ),
                ),
            ),
            'config' => array(
                'label' => 'user.config',
                'fields' => array(
                    'cyrus-userquota' => array(
                        'label' => 'user.quota',
                        'description' => 'user.quota.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 10,
                        'suffix'      => 'MB',
                    ),
                    'kolabFreeBusyFuture' => array(
                        'label' => 'user.fbinterval',
                        'description' => 'user.fbinterval.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 5,
                        'suffix'      => 'days',
                    ),
                    'kolabinvitationpolicy' => array(
                        'label' => 'user.invitation-policy',
                        'description' => 'user.invitation-policy.desc',
                        'type'        => kolab_form::INPUT_TEXTAREA,
                    ),
                    'alias' => array(
                        'label' => 'user.alias',
                        'description' => 'user.alias.desc',
                        'type'        => kolab_form::INPUT_TEXTAREA,
                        'data-type'   => kolab_form::TYPE_LIST,
                    ),
                    'kolabdelegate' => array(
                        'label' => 'user.delegate',
                        'description' => 'user.delegate.desc',
                        'type'        => kolab_form::INPUT_TEXTAREA,
                        'data-type'   => kolab_form::TYPE_LIST,
                    ),
                    'kolabAllowSMTPRecipient' => array(
                        'label' => 'user.smtp-recipients',
                        'description' => 'user.smtp-recipients.desc',
                        'type'        => kolab_form::INPUT_TEXTAREA,
                        'data-type'   => kolab_form::TYPE_LIST,
                    ),
                ),
            ),
        );
*/
        // Form sections
        $sections = array(
            'personal' => 'user.personal',
            'system'   => 'user.system',
            'config'   => 'user.config',
            'other'    => 'user.other',
        );

        // field-to-section map and fields order
        $fields = array(
            'givenname'                 => 'personal',
            'sn'                        => 'personal',
            'displayname'               => 'personal',
            'cn'                        => 'personal',
            'initials'                  => 'personal',
            'title'                     => 'personal',
            'telephonenumber'           => 'personal',
            'facsimiletelephonenumber'  => 'personal',
            'o'                         => 'personal',
            'ou'                        => 'personal',
            'roomnumber'                => 'personal',
            'street'                    => 'personal',
            'l'                         => 'personal',
            'postofficebox'             => 'personal',
            'postalcode'                => 'personal',
            'c'                         => 'personal',
            'preferredlanguage'         => 'personal',

            'uid'                       => 'system',
            'userpassword'              => 'system',
            'userpassword2'             => 'system',
            'mail'                      => 'system',
            'mailalternateaddress'      => 'system',
            'alias'                     => 'system',
            'mailhost'                  => 'system',
            'kolabhomeserver'           => 'system',
            'uidnumber'                 => 'system',
            'gidnumber'                 => 'system',
            'homedirectory'             => 'system',

            'mailquota'                 => 'config',
            'cyrususerquota'            => 'config',
            'kolabfreebusyfuture'       => 'config',
            'kolabinvitationpolicy'     => 'config',
            'kolabdelegate'             => 'config',
            'kolaballowsmtprecipient'   => 'config',
            'kolaballowsmtpsender'      => 'config',
            'shell'                     => 'config',
        );

        $event_fields = array();
        $auto_fields  = array();
        $form_fields  = array();
        $_fields      = array();

        // Selected account type
        if (!empty($data['user_type_id'])) {
            $utype = $data['user_type_id'];
        }
        else {
            $utype = key($accttypes);
            $data['user_type_id'] = $utype;
        }

        if ($utype) {
            $auto_fields = (array) $utypes[$utype]['attributes']['auto_form_fields'];
            $form_fields = (array) $utypes[$utype]['attributes']['form_fields'];
        }

        // Mark automatically generated fields as read-only, etc.
        foreach ($auto_fields as $idx => $field) {
            $_fields[$idx] = array(
                'readonly' => true,
                'disabled' => true,
                'section'  => isset($fields[$idx]) ? $fields[$idx] : 'other',
                // assume auto-generated field is of type text
                'type'        => kolab_form::INPUT_TEXT,
                'maxlength'   => 50,
            );

            if (!empty($field['data'])) {
                 foreach ($field['data'] as $fd) {
                     $event_fields[$fd][] = $idx;
                 }
            }
        }

        // Other fields
        foreach ($form_fields as $idx => $field) {
            $_fields[$idx] = array(
                'section'  => isset($fields[$idx]) ? $fields[$idx] : 'other',
                'required' => true,
            );

            switch ($field['type']) {
            case 'select':
                if (!isset($field['values'])) {
                    // @TODO: call form_value.list_options
                }

                if (!empty($field['values']['default'])) {
                    $_fields[$idx]['value'] = $field['values']['default'];
                    unset($field['values']['default']);
                }

                $_fields[$idx]['options'] = array_combine($field['values'], $field['values']);
                $_fields[$idx]['type'] = kolab_form::INPUT_SELECT;
                break;
            default:
                $_fields[$idx]['type'] = kolab_form::INPUT_TEXT;
                if (isset($field['maxlength'])) {
                    $_fields[$idx]['maxlength'] = $field['maxlength'];
                }
            }

            // Attach on-change events to some fields, to update
            // auto-generated field values
            if (!empty($event_fields[$idx])) {
                $event = json_encode(array_unique($event_fields[$idx]));
                $_fields[$idx]['onchange'] = "kadm.form_value_change($event)";
            }
        }

        // Add user type id selector
        $_fields['user_type_id'] = array(
            'section'  => 'system',
            'type'     => kolab_form::INPUT_SELECT,
            'options'  => $accttypes,
            'onchange' => "kadm.user_save(true, 'system')",
        );

        // Add password confirmation
        if (isset($_fields['userpassword'])) {
            $_fields['userpassword2'] = $_fields['userpassword'];
        }

        // Hide account type selector if there's only one type
        if (count($accttypes) < 2 || !$add_mode) {
            $_fields['user_type_id'] = array(
                'type' => kolab_form::INPUT_HIDDEN,
            );
        }

        // Create mode
        if ($add_mode) {
            if (empty($data['userpassword'])) {
                // Pre-populate password fields
                $post = array('attribute' => 'userpassword');
                $pass = $this->api->post('form_value.generate', null, $post);
                $data['userpassword'] = $data['userpassword2'] = $pass->get('userpassword');
            }

            // Page title
            $title = $this->translate('user.add');
        }
        // Edit mode
        else {
            $title = $data['displayname'];

            // remove password
            $data['userpassword'] = '';
        }

        // Sort
        foreach ($fields as $idx => $val) {
            if (array_key_exists($idx, $_fields)) {
                $fields[$idx] = $_fields[$idx];
                unset($_fields[$idx]);
            }
            else {
                unset($fields[$idx]);
            }
        }
        if (!empty($_fields)) {
            $fields = array_merge($fields, $_fields);
        }

        // Parse elements and add them to the form object
        foreach ($sections as $section_idx => $section) {
            $form->add_section($section_idx, kolab_html::escape($this->translate($section)));

            foreach ($fields as $idx => $field) {
                if ($field['section'] != $section_idx) {
                    continue;
                }

                $field['label']       = kolab_html::escape($this->translate("user.$idx"));
                $field['description'] = "user.$idx.desc";
                $field['section']     = $section_idx;

                if (!empty($data[$idx])) {
                    if (is_array($data[$idx])) {
                        $field['value'] = array_map(array('kolab_html', 'escape'), $data[$idx]);
                        $field['value'] = implode("\n", $field['value']);
                    }
                    else {
                        $field['value'] = kolab_html::escape($data[$idx]);
                    }
                }
/*
                if (!empty($field['suffix'])) {
                    $field['suffix'] = kolab_html::escape($this->translate($field['suffix']));
                }
*/
                if (!empty($field['options'])) {
                    foreach ($field['options'] as $opt_idx => $option) {
                        if (is_array($option)) {
                            $field['options'][$opt_idx]['content'] = kolab_html::escape($this->translate($option['content']));
                        }
                        else {
                            $field['options'][$opt_idx] = kolab_html::escape($this->translate($option));
                        }
                    }
                }

                if (!empty($field['description'])) {
                    $description = $this->translate($field['description']);
                    if ($description != $field['description']) {
                        $field['title'] = $description;
                    }
                    unset($field['description']);
                }

                if (empty($field['name'])) {
                    $field['name'] = $idx;
                }

                $form->add_element($field);
            }
        }

        $form->set_title(kolab_html::escape($title));

        $form->add_button(array(
            'value'   => kolab_html::escape($this->translate('submit.button')),
            'onclick' => "kadm.user_save()",
        ));

        if (!$add_mode) {
            $user = $data['user'];
            $form->add_button(array(
                'value'   => kolab_html::escape($this->translate('delete.button')),
                'onclick' => "kadm.user_delete('$user')",
            ));
        }

        if (!empty($data['section'])) {
            $form->activate_section($data['section']);
        }

        $this->output->set_env('auto_fields', $auto_fields);
        $this->output->set_env('form_id', $form_id);
        $this->output->add_translation('user.password.mismatch',
            'user.add.success', 'user.delete.success');

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
                'displayname' => kolab_html::escape($this->translate('search.name')),
                'email'       => kolab_html::escape($this->translate('search.email')),
                'uid'         => kolab_html::escape($this->translate('search.uid')),
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
