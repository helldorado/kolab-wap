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

class kolab_client_task_main extends kolab_client_task
{
    protected $menu = array(
        'user.default'      => 'menu.users',
        'group.default'     => 'menu.groups',
        'resource.default'  => 'menu.resources',
        'domain.default'    => 'menu.domains',
        'role.default'      => 'menu.roles',
        'about.default'     => 'menu.about',
    );


    public function action_default()
    {
        // assign token
        $this->output->set_env('token', $_SESSION['user']['token']);

        // add watermark content
        $this->output->set_env('watermark', $this->output->get_template('watermark'));

        // assign default set of translations
        $this->output->add_translation('loading', 'saving', 'deleting', 'servererror',
            'search', 'search.loading', 'search.acchars');

        // Create list of tasks for dashboard
        // @TODO: check capabilities
        $this->output->assign('tasks', $this->menu);

        $this->output->assign('main_menu', $this->menu());
        $this->output->assign('user', $_SESSION['user']);
    }

}
