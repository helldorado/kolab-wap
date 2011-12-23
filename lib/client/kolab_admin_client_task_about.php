<?php

class kolab_admin_client_task_about extends kolab_admin_client_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'kolab'      => 'menu.kolab',
        'kolabsys'   => 'menu.kolabsys',
        'technology' => 'menu.technology',
    );

    public function action_default()
    {
        $this->output->set_object('content', 'about', true);
        $this->output->set_object('task_navigation', $this->menu());
    }

    public function action_kolab()
    {
        $this->output->set_object('content', 'about_kolab', true);
    }

    public function action_kolabsys()
    {
        $this->output->set_object('content', 'about_kolabsys', true);
    }

    public function action_technology()
    {
        $this->output->set_object('content', 'about_technology', true);
    }

}
