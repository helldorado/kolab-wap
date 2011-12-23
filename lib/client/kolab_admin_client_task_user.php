<?php

class kolab_admin_client_task_user extends kolab_admin_client_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'add'  => 'user.add',
        'list' => 'user.list',
    );

    public function action_default()
    {
        $this->output->set_object('content', 'user', true);
        $this->output->set_object('task_navigation', $this->menu());
    }

    public function action_list()
    {
        $result = $this->api->post('users.list');
        $result = (array) $result->get();

        $rows = $head = array();
        $cols = array('name', 'actions', 'test');
        $i    = 0;

        foreach ($cols as $col) {
            $body = $col != 'actions' ? $this->translate('user.' . $col) : '';
            $head[0]['cells'][] = array('class' => $col, 'body' => $body);
        }

        if (!empty($result)) {
            foreach ($result as $idx => $item) {
                if (!is_array($item) || empty($item['uid'])) {
                    continue;
                }

                $i++;
                $cells = array();
                $cells[] = array('class' => 'name', 'body' => kolab_html::escape($item['uid']),
                    'onclick' => "kadm.command('user.info', '$idx')");
                $cells[] = array('class' => 'links', 'body' => '<a>test</a>');
                $cells[] = array('class' => 'links', 'body' => 'test sdf sdf sd fs df sdf sd f');
                $rows[] = array('id' => $i, 'class' => 'selectable', 'cells' => $cells);
            }
        }
        else {
            $rows[] = array('cells' => array(
                0 => array('class' => 'empty-body', 'colspan' => count($cols),
                    'body' => $this->translate('user.norecords')
            )));
        }

        $table = kolab_html::table(array('id' => 'userlist', 'class' => 'list',
            'head' => $head, 'body' => $rows));
        $this->output->set_object('task_content', $table);
    }

    public function action_info()
    {
        $id     = $this->get_input('id', 'POST');
        $result = $this->api->get('user.info', array('user' => $id));

        $user = $result->get($id);

        $definition = array(
            'personal' => array(
                'label' => $this->translate('user.personal'),
                'fields' => array(
                    'givenname' => array(
                        'label' => $this->translate('user.givenname'),
                        'description' => '',
                        'type' => '',
                        'value' => $user['givenname'],
                    ),
                    'sn' => array(
                        'label' => $this->translate('user.surname'),
                        'description' => '',
                        'type' => '',
                        'value' => $user['sn'],
                    ),
                    'mail' => array(
                        'label' => $this->translate('user.email'),
                        'description' => 'sdf sdf sd fs  sdf s dfsdfsdf sdfsdfsfsfs df',
                        'type' => '',
                        'value' => $user['mail'],
                    ),
                ),
            ),
        );

        $form = kolab_html::form_table(null, $definition);

        $this->output->set_object('content', $form);
    }

    public function user_add()
    {

    }

    private function user_types()
    {
        $result = $this->api->post('user_types.list');
    }
}
