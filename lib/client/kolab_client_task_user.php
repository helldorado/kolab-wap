<?php

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
        if (!$page) {
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

            $post['search'] = array(
                $field => array(
                    'value' => $search,
                    'type'  => $method,
                ),
            );
            $post['search_operator'] = 'OR';
        }

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

            $count = kolab_html::span(array(
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

            $foot_body = kolab_html::span(array('content' => $prev . $count . $next));
        }
        $foot[0]['cells'][] = array('class' => 'listnav', 'body' => $foot_body);

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
        $output = $this->user_form(null, $user);

        $this->output->set_object('taskcontent', $output);
    }

    /**
     * Users adding (form) action.
     */
    public function action_add()
    {
        $output = $this->user_form(null, null);

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
        $accttypes = array();

        foreach ($utypes as $idx => $elem) {
            $accttypes[$idx] = array('value' => $elem['key'], 'content' => $elem['name']);
        }

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
                    'password' => array(
                        'label' => 'user.password',
                        'description' => 'user.password.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                        'required'    => true,
                    ),
                    'password2' => array(
                        'label' => 'user.password-confirm',
                        'description' => 'user.password-confirm.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                        'required'    => true,
                    ),
                    'kolabhomeserver' => array(
                        'label' => 'user.homeserver',
                        'description' => 'user.homeserver.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 50,
                        'required'    => true,
                    ),
                    'accttype' => array(
                        'label' => 'user.type',
                        'description' => 'user.type.desc',
                        'type'        => kolab_form::INPUT_SELECT,
                        'options'     => $accttypes,
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

        $event_fields = array();
        $auto_fields  = array();

        // Selected account type
        $utype = !empty($data['accttype']) ? $data['accttype'] : key($accttypes);

        if ($utype) {
            $auto_fields = (array) $utypes[$utype]['attributes']['auto_form_fields'];
        }

        // Mark automatically generated fields as read-only, etc.
        foreach ($auto_fields as $af_idx => $af) {
            foreach ($fields as $section_idx => $section) {
                foreach ($section['fields'] as $idx => $field) {
                    if ($idx == $af_idx) {
                        $fields[$section_idx]['fields'][$idx]['readonly'] = true;
                        $fields[$section_idx]['fields'][$idx]['disabled'] = true;
                        $fields[$section_idx]['fields'][$idx]['required'] = false;

//                        if (!empty($af['data'])) {
//                        }
                        break 2;
                    }
                }
            }
        }

/*
        // Hide account type selector if there's only one type
        if (count($accttypes)) {
            $fields['system']['fields']['accttype'] = array(
                'type' => kolab_form::INPUT_HIDDEN,
            );
        }
*/

        // Parse elements and add them to the form object
        foreach ($fields as $section_idx => $section) {
            if (empty($section['fields'])) {
                continue;
            }

            $form->add_section($section_idx, kolab_html::escape($this->translate($section['label'])));

            foreach ($section['fields'] as $idx => $field) {
                $field['section']     = $section_idx;
                $field['label']       = kolab_html::escape($this->translate($field['label']));

                if (!empty($data[$idx])) {
                    if (is_array($data[$idx])) {
                        $field['value'] = array_map(array('kolab_html', 'escape'), $data[$idx]);
                        $field['value'] = implode("\n", $field['value']);
                    }
                    else {
                        $field['value'] = kolab_html::escape($data[$idx]);
                    }
                }

                if (!empty($field['suffix'])) {
                    $field['suffix'] = kolab_html::escape($this->translate($field['suffix']));
                }

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

        $title = $data === null ? $this->translate('user.add') : $data['displayname'];
        $form->set_title(kolab_html::escape($title));

        $form->add_button(array(
            'value'   => kolab_html::escape($this->translate('submit.button')),
            'onclick' => "kadm.user_save('$form_id')",
        ));

        if ($data !== null) {
            $form->add_button(array(
                'value'   => kolab_html::escape($this->translate('delete.button')),
                'onclick' => "kadm.user_delete('$form_id')",
            ));
        }

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
