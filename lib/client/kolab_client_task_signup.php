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

require_once('recaptchalib.php');

class kolab_client_task_signup extends kolab_client_task
{
    protected $ajax_only = true;

    /**
     * Overwrite Main execution.
     */
    public function run()
    {
        // don't set any cookies
        ini_set('session.use_cookies', '0');

        // Initialize locales
        $this->locale_init();

        // Assign self to template variable
        $this->output->assign('engine', $this);

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

    private function login($domain=NULL)
    {
        if(is_null($domain)) {
            $this->domain = $this->config_get('primary_domain');
        } else {
            $this->domain = $domain;
        }

        // Login ($result is a kolab_client_api_result instance)
        $result = $this->api->login($this->config_get('bind_dn'), $this->config_get('bind_pw'), $this->domain);

        // Set the session token we got in the API client instance, so subsequent
        // API calls are made in the same session.
        $this->token = $result->get('session_token');
        $this->api->set_session_token($this->token);
    }

    public function action_default()
    {
        $this->login();

        $data = $this->get_input('data', 'POST');
        $form = $this->user_form($data);

        // add captcha
        $publickey = $this->config_get('recaptcha_public_key');

        if (!empty($publickey)) {
            // TODO find a less dirty way to add captcha into form
            $form = preg_replace('/<\/tbody>/', '<tr><td class="label">'.$this->translate('signup.captcha').'</td><td class="value"><div id="recaptcha_div"></div></td></tr></tbody>', $form);

            // load captcha
            $form .= '
                <script type="text/javascript">
                    Recaptcha.create("'.$publickey.'", "recaptcha_div", {theme: "red"});
                </script>';
        }

        $this->output->assign('form', $form);
        $this->output->set_env('token', $this->token);
        $this->output->set_object('taskcontent', $form);
        $this->output->command('check_user_availability');
    }

    // check if user already exists
    public function action_check_user($data = array()) {
        if(count($data) == 0) $data = $this->get_input('data', 'POST');

        $this->login($data['domain']);

        // Assemble mail attribute
        $mail = $data['uid'].'@'.$data['domain'];

        $post = array('search' => array('mail' => array('value' => $mail) ) );
        $result = $this->api->post('users.list', null, $post);

        if($result->get('count') > 0) {
            $this->output->command('update_user_info', 'signup.userexists', 'uid');
            return false;
        }

        $this->output->command('update_user_info', '', 'uid');
        return true;
    }

    public function action_add_user() {
        $data = $this->get_input('data', 'POST');

        $private_key = $this->config_get('recaptcha_private_key');

        if (!empty($private_key)) {
            // Check for valid CAPTCHA
            $resp = recaptcha_check_answer(
                        $private_key,
                        $_SERVER['REMOTE_ADDR'],
                        $data['recaptcha_challenge_field'],
                        $data['recaptcha_response_field']
            );

            if (!$resp->is_valid) {
                // What happens when the CAPTCHA was entered incorrectly
                $this->output->command('reload_captcha');
                // TODO localise this error message
                $this->output->command('display_message', "The reCAPTCHA wasn't entered correctly. Please try again.", 'error');
                return;
            }

        }

        // Check again for user availability before adding user
        // this also logs into the API
        // TODO perform security check on value of $data['uid'] and $data['domain']
        if(!$this->action_check_user($data)) {
            $this->output->command('form_value_error', 'uid');
            return;
        }

        $this->api->get('system.select_domain', array('domain', $data['domain']));

        // Remove domain from $data before adding user
        unset($data['domain']);

        // Add user
        $result = $this->api->post('user.add', null, $data);

        if (array_key_exists('error_code', $result)) {
            $this->output->command('display_message', 'internalerror', 'error');
            return;
        } else {
            $this->output->set_object('taskcontent', $this->translate('signup.usercreated'));
            // TODO catch errors
            $this->send_mail($data);
            $this->add_to_openerp($data);
        }
    }

    private function user_form($data = array()) {
        $attribs['id'] = 'signup-form';

        $fields_map = array(
            'type_id'                   => 'other',
            'givenname'                 => 'other',
            'sn'                        => 'other',
            'cn'                        => 'other',
            'org'                       => 'other',
            'mailalternateaddress'      => 'other',
            'uid'                       => 'other',
            'domain'                    => 'other',
            'userpassword'              => 'other',
            'userpassword2'             => 'other',
            'mail'                      => 'other',
            'alias'                     => 'other',
        );

        // Prepare fields
        list($fields, $types, $type) = $this->form_prepare('user', $data, array('userpassword2'), 'hosted');

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
        // Hide user types selector if only one is available
        if(count($accttypes) <= 1) {
            $accctype = array_shift(array_values($accttypes));
            $fields['type_id']['type'] = kolab_form::INPUT_HIDDEN;
            $fields['type_id']['value'] = $accttype['value'];
        }
        
        // Add object type field
        $fields['object_type'] = array(
            'type'     => kolab_form::INPUT_HIDDEN,
            'value'    => 'user',
        );
 
        // Add available domains
        $domains = $this->get_domains();
        $fields['domain'] = array(
            'type'     => kolab_form::INPUT_SELECT,
            'options'  => $domains,
            'onchange' => 'kadm.check_user_availability()',
        );
        // Hide domains if only one is available
        if(count($domains) <= 1) {
            $fields['domain']['type'] = kolab_form::INPUT_HIDDEN;
            $fields['domain']['value'] = array_shift(array_values($domains));
        }

        // Check for user availability
        $fields['uid']['onchange'] = 'kadm.check_user_availability()';

        // Hide cn field
        if (isset($fields['cn'])) {
            // TODO add "hidden":true to user_types attributes and use it
            $fields['cn']['type'] = kolab_form::INPUT_HIDDEN;
        }

        // Add password confirmation
        if (isset($fields['userpassword'])) {
            $fields['userpassword2'] = $fields['userpassword'];
            $fields['userpassword2']['onchange'] = 'password_match()';
        }
        
        // Change field labels for hosted case
        $fields['uid']['label'] = 'signup.username';
        $fields['mail']['label'] = 'signup.futuremail';
        if(isset($fields['mailalternateaddress'])) $fields['mailalternateaddress']['label'] = 'signup.mailalternateaddress';
//        if(isset($fields['org'])) $fields['org']['label'] = 'signup.company';
        $fields['domain']['label'] = 'signup.domain';

        // Create form object and populate with fields
        $form = $this->form_create('user', $attribs, array('other'), $fields, $fields_map, $data, true);

        $form->set_title($this->translate('signup.formtitle'));

        $this->output->add_translation('user.password.mismatch', 'signup.wronguid', 'signup.userexists', 'signup.wrongmailalternateaddress');

        return $form->output();
    }

    protected function get_domains() {
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

            $parent_domain_only = $this->config->get($domain_name, 'hosted_parent_domain_only');

            if (!empty($parent_domain_only) && in_array(strtolower($parent_domain_only), array('1', 'yes', 'true'))) {
                $domain_names = array_merge($domain_names, array($domain_name));
            } else {
                $domain_names = array_merge($domain_names, $_domain_names);
            }
        }

        // prepare array with proper key ids for form building
        foreach ($domain_names as $domain) {
            $domain_form_names[$domain] = $domain;
        }

        return $domain_form_names;
    }

    private function send_mail($data)
    {
        if($this->config_get('send_signup_mail', 'false', Conf::BOOL) && $this->config_get('mail_address')) {
            $recipients = $this->config_get('mail_address');

            $headers['From']    = 'Kolab Web Admin Signup <noreply@' . $this->config_get('primary_domain') . '>';
            $headers['To']      = $this->config_get('mail_address');
            $headers['Subject'] = 'New Kolab Signup from ' . $data['mailalternateaddress'];

            // TODO localize this
            $body = "{$data['cn']} <{$data['mailalternateaddress']}> from {$data['org']} just signed up for a Kolab account ({$data['mail']}).";

            Log::Debug("Send Mail to $recipients ...");
            Log::Trace($body);

            $mail =& Mail::factory('sendmail');
            $result = $mail->send($recipients, $headers, $body);

            if(PEAR::isError($result)) {
                Log::Error($result->toString());
            }
        }
    }

    private function add_to_openerp($data)
    {
        $HOST = $this->config_get('openerp_host');
        $PORT = $this->config_get('openerp_port');
        $DB   = $this->config_get('openerp_db');
        $USER = $this->config_get('openerp_user');
        $PASS = $this->config_get('openerp_pass');

        if($HOST && $PORT && $DB && $USER && $PASS) {
            include_once('xmlrpc/xmlrpc.inc');

            $sock = new xmlrpc_client("http://$HOST:$PORT/xmlrpc/common");
            $msg = new xmlrpcmsg('login');
            $msg->addParam(new xmlrpcval($DB,   "string"));
            $msg->addParam(new xmlrpcval($USER, "string"));
            $msg->addParam(new xmlrpcval($PASS, "string"));
            $resp =  $sock->send($msg);
            $val = $resp->value();
            $uid = $val->scalarval();

            Log::Trace("Logged in to OpenERP as $USER (uid:$uid)");

            // Create a new lead
            $arrayVal = array(
                // TODO localize name string
                'name' => new xmlrpcval("New Evaluation Signup by {$data['mailalternateaddress']}", "string"),
                'contact_name' => new xmlrpcval($data['cn'], "string"),
                'email_from' => new xmlrpcval($data['mailalternateaddress'], "string"),
                'partner_name' => new xmlrpcval($data['org'], "string"),
                'inventor_id' => new xmlrpcval($uid, "int"),
            );

            $client = new xmlrpc_client("http://$HOST:$PORT/xmlrpc/object");

            $msg = new xmlrpcmsg('execute');
            $msg->addParam(new xmlrpcval($DB, "string"));
            $msg->addParam(new xmlrpcval($uid, "int"));
            $msg->addParam(new xmlrpcval($PASS, "string"));
            $msg->addParam(new xmlrpcval("crm.lead", "string"));
            $msg->addParam(new xmlrpcval("create", "string"));
            $msg->addParam(new xmlrpcval($arrayVal, "struct"));
            $resp = $client->send($msg);

            if ($resp->faultCode()) {
                Log::Error($resp->faultString());
            } else {
                Log::Debug('Lead '.$resp->value()->scalarval().' created in OpenERP!');
            }
        } else {
            Log::Debug('OpenERP settings in kolab.conf are incomplete.');
        }
    }

    /**
     * Overrides config_get() from kolab_client_task
     * Returns configuration option value for hosting.
     *
     * @param string $name      Option name
     * @param mixed  $fallback  Default value
     * @param int    $type      Value type (one of Conf class constants)
     *
     * @return mixed Option value
     */
    public function config_get($name, $fallback = null, $type = null)
    {
        $value = $this->config->get('kolab_hosting', $name, $type);
        if($value === null) {
            $value = parent::config_get($name, $fallback, $type);
        }
        return $value !== null ? $value : $fallback;
    }
}
