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
 * Service providing domains listing
 */
class kolab_api_service_domains extends kolab_api_service
{

    public $list_attribs = array(
            'associateddomain',
            'objectclass',
            'entrydn',
        );

    /**
     * Returns service capabilities.
     *
     * @param string $domain Domain name
     *
     * @return array Capabilities list
     */
    public function capabilities($domain)
    {
        $auth = Auth::get_instance();
        $conf = Conf::get_instance();

        $domain_base_dn = $conf->get('domain_base_dn');

        if (empty($domain_base_dn)) {
            return array();
        }

        $effective_rights = $auth->list_rights($domain_base_dn);

        $rights = array();

        if (in_array('add', $effective_rights['entryLevelRights'])) {
            $rights['list'] = "r";
        }

        if (in_array('delete', $effective_rights['entryLevelRights'])) {
            $rights['list'] = "r";
        }

        if (in_array('modrdn', $effective_rights['entryLevelRights'])) {
            $rights['list'] = "r";
        }

        if (in_array('read', $effective_rights['entryLevelRights'])) {
            $rights['list'] = "r";
        }

        $rights['effective_rights'] = "r";

        return $rights;
    }

    /**
     * Users listing (with searching).
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array List result with 'list' and 'count' items
     */
    public function domains_list($get, $post)
    {
        $auth = Auth::get_instance();

        $attributes = $this->parse_list_attributes($post);
        $params = $this->parse_list_params($post);
        $search = $this->parse_list_search($post);

        $domains = $auth->list_domains($attributes, $search, $params);
        $domains = $this->parse_list_result($domains);

        return $domains;
    }
}
