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

class kolab_client_task_about extends kolab_client_task
{
    protected $ajax_only = true;

    protected $menu = array(
        'kolab'      => 'about.kolab',
        'kolabsys'   => 'about.kolabsys',
        'technology' => 'about.technology',
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
