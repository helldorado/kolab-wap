<?php

class kolab_client_task_main extends kolab_client_task
{
    protected $menu = array(
        'user.default'   => 'menu.users',
        'group.default'  => 'menu.groups',
        'about.default'  => 'menu.about',
    );


    public function action_default()
    {
        // assign token
        $this->output->set_env('token', $_SESSION['user']['token']);

        // add watermark content
        $this->output->set_env('watermark', $this->output->get_template('watermark'));

        // assign default set of translations
        $this->output->add_translation('loading', 'servererror', 'search');

        // Create list of tasks for dashboard
        // @TODO: check capabilities
        $this->output->assign('tasks', $this->menu);

        $this->output->assign('main_menu', $this->menu());
        $this->output->assign('user', $_SESSION['user']);
    }

}
