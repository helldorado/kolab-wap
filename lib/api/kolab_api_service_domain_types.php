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

/**
 *
 */
class kolab_api_service_domain_types extends kolab_api_service
{
    /**
     * Returns service capabilities.
     *
     * @param string $domain Domain name
     *
     * @return array Capabilities list
     */
    public function capabilities($domain)
    {
        return array(
            'list' => 'r',
        );
    }

    /**
     * Domain types listing.
     *
     * @param array $get  GET parameters
     * @param array $post POST parameters
     *
     * @return array List result with 'list' and 'count' items
     */
    public function domain_types_list($get, $post)
    {
        // @TODO: move to database
        $types = array(
            1 => array(
                'key' => 'standard',
                'name' => 'Standard domain',
                'description' => 'A standard domain name space',
                'attributes' => array(
                    'auto_form_fields' => array(),
                    'form_fields' => array(
                        'associateddomain' => array(
                            'type' => 'list',
                        ),
                        'domainadmin' => array(
                            'type' => 'list-domainadmins',
                            'optional' => 'true',
                        ),
                        'inetdomainbasedn' => array(
                            'optional' => 'true',
                        ),
                    ),
                    'fields' => array(
                        'objectclass' => array(
                            'top',
                            'domainrelatedobject',
                        ),
                    ),
                ),
            ),
        );

        return array(
            'list'  => $types,
            'count' => count($types),
        );
    }
}
