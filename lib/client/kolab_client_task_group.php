<?php

class kolab_client_task_group extends kolab_client_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'add'  => 'group.add',
    );

    public function action_default()
    {
        $this->output->set_object('content', '');
        $this->output->set_object('task_navigation', $this->menu());

        $this->action_list();
    }

    public function action_list()
    {
        $page_size = 20;
        $page      = (int) self::get_input('page', 'POST');
        if (!$page) {
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

        $result = $this->api->get('groups.list');
        $count  = (int) $result->get('count');
        $result = (array) $result->get('list');

        foreach ($result as $idx => $item) {
            if (!is_array($item) || empty($item['cn'])) {
                unset($result[$idx]);
                continue;
            }
            $result[$idx] = sprintf('<li><a href="#" onclick="kadm.command(\'group.info\', \'%s\')">%s</a></li>',
                $idx, $item['cn']);
        }

        $result = '<ul id="grouplist">' . implode("\n", $result) . '</ul>';
        $this->output->set_object('content', $result);
    }

    public function action_info()
    {

        $id     = $this->get_input('id', 'POST');
        $result = $this->api->get('group.info', array('group' => $id));

        $group = $result->get($id);
        $this->output->set_object('content', print_r($group, true));
    }

    public function group_add()
    {
    
    }
}
