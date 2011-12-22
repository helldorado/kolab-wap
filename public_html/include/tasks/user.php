<?php

class kolab_admin_task_user extends kolab_admin_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'add'  => 'user.add',
    );

    public function action_default()
    {
        $this->output->set_object('content', 'user', true);
        $this->output->set_object('task_navigation', $this->menu());

        $this->action_list();
    }

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

    public function action_info()
    {
        $id     = $this->get_input('id', 'POST');
        $result = $this->api->get('user.info', array('user' => $id));
        $user   = $result->get($id);
        $form   = new kolab_form();
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
                    'initials' => array(
                        'label'       => 'user.initials',
                        'description' => 'user.initials.desc',
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
                    'title' => array(
                        'label'       => 'user.title',
                        'description' => 'user.title.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 10,
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
                    ),
                    'kolabFreeBusyFuture' => array(
                        'label' => 'user.fbinterval',
                        'description' => 'user.fbinterval.desc',
                        'type'        => kolab_form::INPUT_TEXT,
                        'maxlength'   => 5,
                    ),
                ),
            ),
            'contact' => array(
                'label' => 'user.contact',
                'fields' => array(
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
        );


        foreach ($fields as $section_idx => $section) {
            $form->add_section($section_idx, kolab_html::escape($this->translate($section['label'])));
            foreach ($section['fields'] as $idx => $field) {
                $field['section']     = $section_idx;
                $field['value']       = kolab_html::escape($user[$idx]);
                $field['label']       = kolab_html::escape($this->translate($field['label']));
                $field['description'] = kolab_html::escape($this->translate($field['description']));

                $form->add_element($field);
            }
        }

        $this->output->set_object('taskcontent', $form->output());
    }

    public function user_add()
    {

    }

    private function user_types()
    {
        $result = $this->api->post('user_types.list');
    }
}
