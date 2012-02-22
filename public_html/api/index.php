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
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 +--------------------------------------------------------------------------+
*/

require_once dirname(__FILE__) . "/../../lib/functions.php";

// init frontend controller
$controller = new kolab_api_controller;

try {
    $postdata = $_SERVER['REQUEST_METHOD'] == 'POST' ? file_get_contents('php://input') : null;
    $controller->dispatch($postdata);
} catch(Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    $controller->output->error($e->getMessage(), $e->getCode());
}

// if we arrive here the controller didn't generate output
$controller->output->error("Invalid request");
