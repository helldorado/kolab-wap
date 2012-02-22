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

class kolab_client_api_result
{
    /**
     * @var array
     */
    private $data = array();

    private $error_code;
    private $error_str;


    public function __construct($data = array(), $error_code = null, $error_str = null)
    {
        if (is_array($data) && isset($data['result'])) {
            $this->data = $data['result'];
        }

        $this->error_code = $error_code;
        $this->error_str = $error_str;
    }

    public function get_error_code()
    {
        return $this->error_code;
    }

    public function get_error_str()
    {
        return $this->error_str;
    }

    public function get($name = null)
    {
        if ($name !== null) {
            return isset($this->data[$name]) ? $this->data[$name] : null;
        }

        return $this->data;
    }
}
