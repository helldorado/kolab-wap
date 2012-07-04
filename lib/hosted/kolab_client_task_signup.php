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
        $_SESSION['user']['token'] = $this->token;
                
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


        // retrieve user types
        $user_types = $this->api->get('user_types.list')->get();

        // We're interested in user_type 'kolab' for personal users
        foreach ($user_types['list'] as $type_id => $type_attrs) {
            if ($type_attrs['key'] == 'kolab') {
                $this->type_id = $type_id;
                
/*                foreach ($type_attrs['attributes']['form_fields'] as $field_name => $field_attrs) {
                    // Use the $field_attrs['type'] to see what type the field is, use lookup map in lib/kolab_form.php
                    if($field_attrs['optional'] != 'yes') {
                        console($field_name . " type=" . (empty($field_attrs['type']) ? "text" : $field_attrs['type']));
                    }
                }
 */
            }
        }

        
        // The sign-up form is deliberately kept minimal. All further information can be entered after sign-up.
        $form_id = 'signup-form';
        $form = new kolab_form(array('id' => $form_id));

        $form->add_element(array(
            'label'   => 'Username',
            'name'    => 'uid',
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
        // TODO make the following fields optional
        $form->add_element(array(
            'name'    => 'type_id',
            'type'    => kolab_form::INPUT_HIDDEN,
            'value'   => $this->type_id,
        ));
        $form->add_element(array(
            'name'    => 'givenname',
            'type'    => kolab_form::INPUT_HIDDEN,
            'value'   => 'test',
        ));
        $form->add_element(array(
            'name'    => 'sn',
            'type'    => kolab_form::INPUT_HIDDEN,
            'value'   => 'test',
        ));
        $form->add_element(array(
            'name'    => 'cn',
            'type'    => kolab_form::INPUT_HIDDEN,
            'value'   => 'test',
        ));
        $form->add_element(array(
            'name'    => 'userpassword',
            'type'    => kolab_form::INPUT_HIDDEN,
            'value'   => 'test',
        ));
        $form->add_element(array(
            'name'    => 'userpassword2',
            'type'    => kolab_form::INPUT_HIDDEN,
            'value'   => 'test',
        ));
        $form->add_element(array(
            'name'    => 'ou',
            'type'    => kolab_form::INPUT_HIDDEN,
            'value'   => 'ou=people,dc=notifytest,dc=tld', // TODO this would need to be updated if kept
        ));
        $form->add_element(array(
            'name'    => 'preferredlanguage',
            'type'    => kolab_form::INPUT_HIDDEN,
            'value'   => 'en_US',
        ));
        $form->add_button(array(
            'value'   => kolab_html::escape('Sign up'),
            'onclick' => "kadm.user_save()",
//            'onclick' => "kadm.command('signup.add_user')",
        ));

        // keep session
        $this->output->set_env('token', $_SESSION['user']['token']);
    
        // add message translations
        $this->output->add_translation('form.required.empty', 'user.add.success');

        // define form_id and required fields
        $this->output->set_env('form_id', $form_id);
        $this->output->set_env('required_fields', Array('uid', 'mail', 'cur_mail', 'domain'));

        // assign form output to template variable
        $this->output->assign('form', $form->output());
    }
    
    public function action_add_user() {
        // TODO actually add user here
        $this->output->command('display_message', 'Not adding user here, yet', 'notice');
    }

}
