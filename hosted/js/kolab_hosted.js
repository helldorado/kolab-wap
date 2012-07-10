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
 | along with kadm program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Torsten Grote <grote@kolabsys.com>                               |
 +--------------------------------------------------------------------------+
*/

// overwrite user_save() function
kadm.user_save = function(reload, section)
{
    var data = kadm.serialize_form('#'+this.env.form_id);

    // check email address
    if(!isValidEmailAddress(data.mailalternateaddress)) {
        // TODO use translatable error message
        kadm.display_message('Please provide a valid email adress as this is where your password will be sent to.', 'error');
        kadm.form_value_error('mailalternateaddress');
        return;
    }

    if (!kadm.check_required_fields(data)) {
      kadm.display_message('form.required.empty', 'error');
      return;
    }

    // check password
    if (data.userpassword != data.userpassword2) {
      kadm.display_message('user.password.mismatch', 'error');
      kadm.form_value_error('userpassword2');
      return;
    }
    delete data['userpassword2'];

    kadm.http_post('signup.add_user', {data: data});
};

kadm.change_user_type = function()
{
    var data = kadm.serialize_form('#'+this.env.form_id);

    kadm.http_post('signup.default', {data: data});
};

kadm.check_user_availability = function()
{
    // get form data and build new email address
    var data = kadm.serialize_form('#signup-form');
    var mail = data['uid'] + '@' + data['domain'];
    
    if(isValidEmailAddress(mail)) {
        // update future mail form field
        $('input[name="mail"]').val(mail);
        
        // check if user with that email address already exists
        kadm.api_post('users.list', {'search': {'mail': {'value': mail} } }, 'check_user_availability_response');
    } else {
        update_user_info('This will not produce a valid email address!');
    }
};

kadm.check_user_availability_response = function(response)
{
    if (!kadm.api_response(response))
      return;
  
    // setup up user message
    var msg = '';
    if(response['result']['count'] > 0) msg = 'User does already exist!';
    
    update_user_info(msg);
};


function update_user_info(msg)
{
    // display message next to form field
    if($('span[id="availability"]').length) {
        // update existing span area
        $('span[id="availability"]').html(msg);
    }
    else {
        // add span area and inform about non-availability
        $('input[name="uid"]').after(' <span id="availability" style="font-weight:bold;margin-left:1em;color:red;">' + msg + '</span>');
    }
    
    // enable/disable button
    if(msg == '') {
        $('input[type="button"]').removeAttr("disabled");
    } else {
        $('input[type="button"]').attr("disabled", "disabled");
    }
};

function isValidEmailAddress(emailAddress) {
    var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
    return pattern.test(emailAddress);
};
