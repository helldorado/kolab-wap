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

require_once('hosted/recaptchalib.php');

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
        
        // Session handling
        $timeout = $this->config_get('session_timeout', 3600);

        // TODO
        // Do not use the API token for the user browser session.
        // Use a different token for the user browser session, to verify whether subsequent interactions
        // belong to the same user nicely progressing through the signup (and not bastardizing the process).
        //
        // Do not maintain the API session across hits to this interface.
        //
        // So...
        //
        // One session token for user browser <-> hosted/index.php
        // One API session token for a single run/hit of/against hosted/index.php
        if (empty($_SESSION['user']) || empty($_SESSION['user']['token']) || ($timeout && $_SESSION['time'] && $_SESSION['time'] < time() - $timeout)) {
            // Login ($result is a kolab_client_api_result instance))
            // TODO log in with different primary domain
            $result = $this->api->login($this->config_get('bind_dn'), $this->config_get('bind_pw'), $this->config->get('kolab', 'primary_domain') );

            // Set the session token we got in the API client instance, so subsequent
            // API calls are made in the same session.
            $this->token = $result->get('session_token');
            $this->api->set_session_token($this->token);

            // TODO don't expose session to browser
            $_SESSION['user']['token'] = $this->token;

            // update session time
            $_SESSION['time'] = time();
        }

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
        // keep session
        $this->output->set_env('token', $_SESSION['user']['token']);

        $data = $this->get_input('data', 'POST');
        $form = $this->user_form($data);

        // add captcha
        $publickey = $this->config_get('recaptcha_public_key');
        // TODO find a less dirty way to add captcha into form
        $form = preg_replace('/<div class="formbuttons">/', '<div id="recaptcha_div"></div><div class="formbuttons">', $form);

        // load captcha
        $form .= '
            <script type="text/javascript">
                Recaptcha.create("'.$publickey.'", "recaptcha_div", {theme: "red"});
            </script>';

        $this->output->assign('form', $form);
        $this->output->set_object('taskcontent', $form);
    }
    
    // switching to proper domain is necessary before calling users.list for that domain
    public function action_switch_domain($data = array()) {
        if(count($data) == 0) $data = $this->get_input('data', 'POST');

        // Login in user-chosen domain
        // TODO perform security check on value of $data['domain']
        $result = $this->api->get('system.select_domain', array('domain' => $data['domain']));
    }

    public function action_add_user() {
        $data = $this->get_input('data', 'POST');

        // Check for valid CAPTCHA
        $resp = recaptcha_check_answer(
                    $this->config_get('recaptcha_private_key'),
                    $_SERVER['REMOTE_ADDR'],
                    $data['recaptcha_challenge_field'],
                    $data['recaptcha_response_field']
        );

        if (!$resp->is_valid) {
            // What happens when the CAPTCHA was entered incorrectly
            $this->output->command('display_message', "The reCAPTCHA wasn't entered correctly. Please reload and try it again.", 'error');
            return;
        }

        // Log in to proper domain
        $this->action_switch_domain($data);

        // Assemble mail attribute and throw away submitted attribute
        $mail = $data['uid'].'@'.$data['domain'];
        $data['mail'] = $mail;

        // Check again for user availability before adding user
        // TODO perform security check on value of $data['uid'] and $data['domain']
        $post = array('search' => array('mail' => array('value' => $mail) ) );
        $result = $this->api->post('users.list', null, $post);

        if($result->get('count') > 0) {
            // TODO make this message translatable
            $this->output->command('display_message', 'A user with that username already exists. Please choose another one.', 'error');
            return false;
        }

        // Remove domain from $data before adding user
        unset($data['domain']);

        // Add user
        $result = $this->api->post('user.add', null, $data);

        if (array_key_exists('error_code', $result)) {
            // TODO make this message translatable
            $this->output->command('display_message', 'An Error occured. You could not be signed up. Please try again.', 'error');
            return;
        } else {
            // TODO make this message translatable
            $this->output->set_object('taskcontent', '<h3>Your account has been successfully added!</h3>Congratulations, you now have your own Kolab account.');
        }
    }

    private function user_form($data = array()) {
        $attribs['id'] = 'signup-form';

        $fields_map = array(
            'type_id'                   => 'other',
            'givenname'                 => 'other',
            'sn'                        => 'other',
            'cn'                        => 'other',
            'mailalternateaddress'      => 'other',
            'uid'                       => 'other',
            'domain'                    => 'other',
            'userpassword'              => 'other',
            'userpassword2'             => 'other',
            'mail'                      => 'other',
            'alias'                     => 'other',
        );

        // Prepare fields
        list($fields, $types, $type) = $this->form_prepare('user', $data, array('userpassword2')); 
        
        // Show only required fields
        foreach ($fields as $field_name => $field_attrs) {
            if(!array_key_exists('required', $field_attrs) or $field_attrs['required'] != 'true') {
                unset($fields[$field_name]);
            }
        }

        // Add user type id selector
        $accttypes = array();
        foreach ($types as $idx => $elem) {
            if($elem['used_for'] == 'hosted') {
                $accttypes[$idx] = array('value' => $idx, 'content' => $elem['name']);
            }
        }

        $fields['type_id'] = array(
            'section'  => 'personal',
            'type'     => kolab_form::INPUT_SELECT,
            'options'  => $accttypes,
            'onchange' => "kadm.change_user_type()",
        );
        
        // Add object type field
        $fields['object_type'] = array(
            'type'     => kolab_form::INPUT_HIDDEN,
            'value'    => 'user',
        );
 
        // Add available domains
        $fields['domain'] = array(
            'type'     => kolab_form::INPUT_SELECT,
            'options'  => $this->get_domains(),
            'onchange' => 'kadm.check_user_availability()',
        );

        // Check for user availability
        $fields['uid']['onchange'] = 'kadm.check_user_availability()';

        // Hide cn field
        if (isset($fields['cn'])) {
            $fields['cn']['type'] = kolab_form::INPUT_HIDDEN;
        }

        // Add password confirmation
        if (isset($fields['userpassword'])) {
            $fields['userpassword2'] = $fields['userpassword'];
        }
        
        // Change field labels for hosted case
        // TODO make translatable
        $fields['uid']['label'] = "Username";
        $fields['mail']['label'] = "Your Future Email Address";
        if(isset($fields['mailalternateaddress'])) $fields['mailalternateaddress']['label'] = "Your Current Email Address";
        $fields['domain']['label'] = "Domain";

        // Create form object and populate with fields
        $form = $this->form_create('user', $attribs, array('other'), $fields, $fields_map, $data, true);

        $form->set_title(kolab_html::escape('Sign up'));

        $this->output->add_translation('user.password.mismatch', 'user.add.success');

        return $form->output();
    }

    private function get_domains() {
        // Get a list of domains ($domains again is a kolab_client_api_result instance)
        $domains_list = $this->api->get('domains.list')->get('list');

        if (empty($domains_list)) {
            return array();
        }

        // The domain name attribute (the name of the LDAP attribute that holds the actual domain name space)
        // is configurable as well. Provide a fallback.
        $domain_name_attribute = $this->config->get('ldap','domain_name_attribute');
        if (empty($domain_name_attribute)) {
            $domain_name_attribute = 'associateddomain';
        }

        // Placeholder for the domain names in this deployment
        $domain_names = array();

        foreach ($domains_list as $domain_dn => $domain_attrs) {
            // If $domain_attrs[$domain_name_attribute] is an array, the primary domain name space
            // is the first value in the array.
            if (is_array($domain_attrs[$domain_name_attribute])) {
                $_domain_names = $domain_attrs[$domain_name_attribute];
                $domain_name = array_shift($domain_attrs[$domain_name_attribute]);
            } else {
                $_domain_names = (array)($domain_attrs[$domain_name_attribute]);
                $domain_name = $domain_attrs[$domain_name_attribute];
            }

            $domain_names = array_merge($domain_names, $_domain_names);
        }

        // prepare array with proper key ids for form building
        foreach ($domain_names as $domain) {
            $domain_form_names[$domain] = $domain;
        }

        return $domain_form_names;
    }

    /**
     * Overrides config_get() from kolab_client_task
     * Returns configuration option value for hosting.
     *
     * @param string $name      Option name
     * @param mixed  $fallback  Default value
     *
     * @return mixed Option value
     */
    public function config_get($name, $fallback = null)
    {
        $value = $this->config->get('kolab_hosting', $name);
        return $value !== null ? $value : $fallback;
    }
}
