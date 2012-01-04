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
        $result = $this->api->post('users.list');
        $result = (array) $result->get();

        $rows = $head = array();
        $cols = array('name');
        $i    = 0;

        // table header
        $head[0]['cells'][] = array('class' => 'name', 'body' => $this->translate('user.name'));

        if (!empty($result)) {
            foreach ($result as $idx => $item) {
                if (!is_array($item) || empty($item['uid'])) {
                    continue;
                }

                $i++;
                $cells = array();
                $cells[] = array('class' => 'name', 'body' => kolab_html::escape($item['uid']),
                    'onclick' => "kadm.command('user.info', '$idx')");
                $rows[] = array('id' => $i, 'class' => 'selectable', 'cells' => $cells);
            }
        }
        else {
            $rows[] = array('cells' => array(
                0 => array('class' => 'empty-body', 'body' => $this->translate('user.norecords')
            )));
        }

        $table = kolab_html::table(array('id' => 'userlist', 'class' => 'list',
            'head' => $head, 'body' => $rows));

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

        $form    = new kolab_form($attribs);
        $utypes  = $this->user_types();
        $form_id = $attribs['id'];

        foreach ($utypes as $idx => $elem) {
            $utypes[$idx] = array('value' => $elem['key'], 'content' => $elem['name']);
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
                        'options'     => $utypes,
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
                    ),
                    'kolabdelegate' => array(
                        'label' => 'user.delegate',
                        'description' => 'user.delegate.desc',
                        'type'        => kolab_form::INPUT_TEXTAREA,
                    ),
                    'kolabAllowSMTPRecipient' => array(
                        'label' => 'user.smtp-recipients',
                        'description' => 'user.smtp-recipients.desc',
                        'type'        => kolab_form::INPUT_TEXTAREA,
                    ),
                ),
            ),
        );

        // Parse elements and add them to the form object
        foreach ($fields as $section_idx => $section) {
            $form->add_section($section_idx, kolab_html::escape($this->translate($section['label'])));
            foreach ($section['fields'] as $idx => $field) {
                $field['section']     = $section_idx;
                $field['label']       = kolab_html::escape($this->translate($field['label']));

                if (isset($data[$idx])) {
                    $field['value'] = kolab_html::escape($data[$idx]);
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
                    $description = kolab_html::escape($this->translate($field['description']));
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

        $form->set_title(kolab_html::escape($data['displayname']));

        $form->add_button(array(
            'value'   => kolab_html::escape($this->translate('submit.button')),
            'onclick' => "kadm.save_user('$form_id')",
        ));
        $form->add_button(array(
            'value'   => kolab_html::escape($this->translate('delete.button')),
            'onclick' => "kadm.delete_user('$form_id')",
        ));

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
                'name' => kolab_html::escape($this->translate('search.name')),
            ),
        ));

        return $form->output();
    }

}
