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
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 +--------------------------------------------------------------------------+
*/

/**
 * View class generating JSON output
 */
class kolab_json_output
{

    /**
     *
     */
    public function success($data)
    {
        if (!is_array($data)) {
            $data = array();
        }

        $this->send(array('status' => 'OK', 'result' => $data));
    }


    /**
     *
     */
    public function error($errdata, $code = 400)
    {
        if (is_string($errdata)) {
            $errdata = array('reason' => $errdata);
        }

        $this->send(array('status' => 'ERROR', 'code' => $code) + $errdata);
    }


    /**
     *
     */
    public function send($data)
    {
        header("Content-Type: application/json");
        echo json_encode($data);
        exit;
    }

}
