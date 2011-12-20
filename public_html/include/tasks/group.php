<?php

class kolab_admin_task_group extends kolab_admin_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'add'  => 'group.add',
        'list' => 'group.list',
    );

    public function action_default()
    {
        $this->output->set_object('content', '');
        $this->output->set_object('task_navigation', $this->menu());
    }

    public function action_list()
    {
//        $content = 'test output from users.list';
        $result = $this->api->post('groups.list');
        $result = (array) $result->get();
/*
        foreach ($result as $idx => $item) {
            if (!is_array($item) || empty($item['uid'])) {
                unset($result[$idx]);
                continue;
            }
            $result[$idx] = sprintf('<li><a href="#" onclick="kadm.command(\'user.info\', \'%s\')">%s</a></li>',
                $idx, $item['uid']);
        }

        $result = '<ul id="userlist">' . implode("\n", $result) . '</ul>';
        $this->output->set_object('content', $result);
*/
    }

    public function action_info()
    {
/*
        $id     = $this->get_input('id', 'POST');
        $result = $this->api->get('user.info', array('user' => $id));

        $user = $result->get($id);
        $this->output->set_object('content', print_r($user, true));
*/
    }

    public function group_add()
    {
    
    }
}
