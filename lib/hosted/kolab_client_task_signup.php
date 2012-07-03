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
 | Author: Torsten Grote <grote@kolabsys.com>                               |
 +--------------------------------------------------------------------------+
*/

class kolab_client_task_signup extends kolab_client_task
{
    protected $ajax_only = true;

    /**
     * Overwrite Main execution.
     */
    public function run()
    {
        // Initialize locales
        $this->locale_init();

        // Assign self to template variable
        $this->output->assign('engine', $this);
        
        // Login ($result is a kolab_client_api_result instance))
        $result = $this->api->login($this->config->get('ldap', 'bind_dn'), $this->config->get('ldap', 'bind_pw'), 'notifytest.tld');

        // Set the session token we got in the API client instance, so subsequent
        // API calls are made in the same session.
        $this->token = $result->get('session_token');
        $this->api->set_session_token($this->token);
                
        // Run security checks
        // TODO figure out to reenable this
//        $this->input_checks();

        $action = $this->get_input('action', 'GET');

        if ($action) {
            $method = 'action_' . $action;
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        else if (method_exists($this, 'action_default')) {
            $this->action_default();
        }
    }

    public function action_default()
    {
        // Get a list of domains ($domains again is a kolab_client_api_result instance)
        $domains = $this->api->get('domains.list')->get();

        // The domain name attribute (the name of the LDAP attribute that holds the actual domain name space)
        // is configurable as well. Provide a fallback.
        $domain_name_attribute = $this->config->get('ldap','domain_name_attribute');
        if (empty($domain_name_attribute)) {
            $domain_name_attribute = 'associateddomain';
        }

        // Placeholder for the domain names in this deployment
        $domain_names = array();

        foreach ($domains['list'] as $domain_dn => $domain_attrs) {
            // If $domain_attrs[$domain_name_attribute] is an array, the primary domain name space
            // is the first value in the array.
            if (is_array($domain_attrs[$domain_name_attribute])) {
                $_domain_names = $domain_attrs[$domain_name_attribute];
                $domain_name = array_shift($domain_attrs[$domain_name_attribute]);
            } else {
                $_domain_names = (array)($domain_attrs[$domain_name_attribute]);
                $domain_name = $domain_attrs[$domain_name_attribute];
            }

            // TODO: Perform a check to see if this domain is available for public registration somehow.
            // Lacking business support, everything but 'kolab.net' (the primary domain) is available.
            if ($domain_name == $this->config->get('kolab', 'primary_domain')) {
                continue;
            }

            $domain_names = array_merge($domain_names, $_domain_names);
        }

        // prepare array with proper key ids for form building
        foreach ($domain_names as $domain) {
           $domain_form_names[$domain] = $domain;
        }

        
        // The sign-up form is deliberately kept minimal. All further information can be entered after sign-up.

        $form = new kolab_form(array('id' => 'signup-form'));

        $form->add_element(array(
            'label'   => 'Username',
            'name'    => 'alias',
            'type'    => kolab_form::INPUT_TEXT,
            'value'   => 'john.doe',
            'onchange'=> "kadm.check_user_availability()",
        ));
        $form->add_element(array(
            'label'   => 'Domain',
            'name'    => 'domain',
            'type'    => kolab_form::INPUT_SELECT,
            'options' => $domain_form_names,
            'onchange'=> "kadm.check_user_availability()",
        ));
        $form->add_element(array(
            'label'   => 'Current Email Address',
            'name'    => 'cur_mail',
            'type'    => kolab_form::INPUT_TEXT,
        ));
        $form->add_element(array(
            'label'   => 'Future Email Address',
            'name'    => 'mail',
            'type'    => kolab_form::INPUT_TEXT,
            'readonly'=> true,
        ));
        $form->add_button(array(
            'value'   => kolab_html::escape('Sign up'),
            'onclick' => "alert('not working yet')",
        ));

        // keep session
        $this->output->set_env('token', $this->token);

        // assign form output to template variable
        $this->output->assign('form', $form->output());
    }
    
    public function action_add_user() {
        // TODO actually add user here
    }

}
