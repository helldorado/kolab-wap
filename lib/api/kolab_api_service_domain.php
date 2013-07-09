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
 * Service providing domain mutations
 */
class kolab_api_service_domain extends kolab_api_service
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
        $auth = Auth::get_instance();
        $conf = Conf::get_instance();

        $domain_base_dn = $conf->get('domain_base_dn');

        if (empty($domain_base_dn)) {
            return array();
        }

        $effective_rights = $auth->list_rights($domain_base_dn);

        $rights = array();

        if (in_array('add', $effective_rights['entryLevelRights'])) {
            $rights['add'] = "w";
        }

        if (in_array('delete', $effective_rights['entryLevelRights'])) {
            $rights['delete'] = "w";
        }

        if (in_array('modrdn', $effective_rights['entryLevelRights'])) {
            $rights['edit'] = "w";
        }

        if (in_array('read', $effective_rights['entryLevelRights'])) {
            $rights['find'] = "r";
            $rights['find_by_any_attribute'] = "r";
            $rights['find_by_attribute'] = "r";
            $rights['find_by_attributes'] = "r";
            $rights['info'] = "r";
        }

        $rights['effective_rights'] = "r";

        return $rights;
    }

    public function domain_add($getdata, $postdata)
    {
        $conf = Conf::get_instance();
        $dna = $conf->get('domain_name_attribute');

        if (empty($dna)) {
            $dna = 'associateddomain';
        }

        if (empty($postdata[$dna])) {
            return;
        }

        $auth = Auth::get_instance($conf->get('kolab', 'primary_domain'));

        if (is_array($postdata[$dna])) {
            $parent_domain = array_shift($postdata[$dna]);
            return $auth->domain_add($postdata, $parent_domain);
        } else {
            return $auth->domain_add($postdata);
        }
    }

    public function domain_edit($getdata, $postdata)
    {
        //console("domain_edit \$postdata", $postdata);

        $domain_attributes  = $this->parse_input_attributes('domain', $postdata);
        $domain             = $postdata['id'];

        $auth   = Auth::get_instance();
        $result = $auth->domain_edit($postdata['id'], $domain_attributes, $postdata['type_id']);

        // @TODO: return unique attribute or all attributes as domain_add()
        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * Domain delete.
     *
     * @param array $get  GET parameters
     * @param array $post POST parameters
     *
     * @return bool True on success, False on failure
     */
    public function domain_delete($getdata, $postdata)
    {
        if (empty($postdata['domain'])) {
            return false;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->domain_delete($postdata['domain']);

        if ($result) {
            return $result;
        }

        return false;
    }

    public function domain_effective_rights($getdata, $postdata)
    {
        $auth = Auth::get_instance();

        if (!empty($getdata['domain'])) {
            $entry_dn    = $getdata['domain'];
            $unique_attr = $this->unique_attribute();
            $domain      = $auth->domain_find_by_attribute(array($unique_attr => $entry_dn));

            if (!empty($domain)) {
                $entry_dn = key($domain);
            }
        }
        else {
            $conf = Conf::get_instance();
            $entry_dn = $conf->get('ldap', 'domain_base_dn');
        }

        // TODO: Fix searching the correct base_dn... Perhaps find the entry
        // first.
        $effective_rights = $auth->list_rights($entry_dn);

        return $effective_rights;
    }

    /**
     * Domain information.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool Domain attributes, False on error
     */
    public function domain_info($getdata, $postdata)
    {
        if (!isset($getdata['domain'])) {
            return false;
        }

        $auth   = Auth::get_instance();
        $result = $auth->domain_info($getdata['domain']);

        // normalize result
        $result = $this->parse_result_attributes('domain', $result);

        if (empty($result['id'])) {
            $result['id'] = $getdata['domain'];
        }

        //console("API/domain.info() \$result:", $result);

        if ($result) {
            return $result;
        }

        return false;
    }
}
