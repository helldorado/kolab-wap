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

kadm.reload_captcha = function()
{
    Recaptcha.reload();
};

kadm.change_user_type = function()
{
    var data = kadm.serialize_form('#'+this.env.form_id);

    this.set_busy(true, 'loading');
    kadm.http_post('signup.default', {data: data});
};

kadm.check_user_availability = function()
{
    // get form data and build new email address
    var data = kadm.serialize_form('#signup-form');
    var mail = data['uid'] + '@' + data['domain'];

    if(data['uid'] != '') {
        if(isValidEmailAddress(mail)) {
            // update future mail form field
            $('input[name="mail"]').val(mail);

            // check if user with that email address already exists
            kadm.http_post('signup.check_user', {data: data});
        } else {
            kadm.update_user_info('signup.wronguid', 'uid');
        }
    }
};

kadm.update_user_info = function(msg, part)
{
    var span_id = 'availability';
    if(!part.localeCompare('userpassword')) {
        span_id = 'pass_match';
    }

    if (msg) {
        msg = kadm.t(msg);
    }

    // display message next to form field
    if($('span[id="'+span_id+'"]').length) {
        // update existing span area
        $('span[id="'+span_id+'"]').html(msg);
    }
    else {
        // add span area and add message
        $('input[name="'+part+'"]').after(' <span id="'+span_id+'" class="form_error">' + msg + '</span>');
    }

    // enable/disable button
    if(msg == '') {
        $('input[type="button"]').removeAttr("disabled");
    } else {
        $('input[type="button"]').attr("disabled", "disabled");
    }
};


function password_match()
{
    if($('input[name="userpassword"]').val().localeCompare($('input[name="userpassword2"]').val())) {
        kadm.update_user_info('user.password.mismatch', 'userpassword');
    }
    else {
        kadm.update_user_info('', 'userpassword');
    }
}

// this is only used to update GUI only when it makes sense, for real validation we rely on form_value.validate
function isValidEmailAddress(emailAddress) {
    var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
    return pattern.test(emailAddress);
};
