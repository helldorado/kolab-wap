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
 +--------------------------------------------------------------------------+
*/

function kolab_admin()
{
  var ref = this;

  this.env = {};
  this.translations = {};
  this.request_timeout = 300;
  this.message_time = 3000;
  this.events = {};

  // set jQuery ajax options
  $.ajaxSetup({
    cache: false,
    error: function(request, status, err) { ref.http_error(request, status, err); },
    beforeSend: function(xmlhttp) { xmlhttp.setRequestHeader('X-Session-Token', ref.env.token); }
  });


  /*********************************************************/
  /*********          basic utilities              *********/
  /*********************************************************/

  // set environment variable(s)
  this.set_env = function(p, value)
  {
    if (p != null && typeof p === 'object' && !value)
      for (var n in p)
        this.env[n] = p[n];
    else
      this.env[p] = value;
  };

  // add a localized label(s) to the client environment
  this.tdef = function(p, value)
  {
    if (typeof p == 'string')
      this.translations[p] = value;
    else if (typeof p == 'object')
      $.extend(this.translations, p);
  };

  // return a localized string
  this.t = function(label)
  {
    if (this.translations[label])
      return this.translations[label];
    else
      return label;
  };

  // print a message into browser console
  this.log = function(msg)
  {
    if (window.console && console.log)
      console.log(msg);
  };

  // execute a specific command on the web client
  this.command = function(command, props, obj)
  {
    if (obj && obj.blur)
      obj.blur();

    if (this.busy)
      return false;

    this.set_busy(true, 'loading');

    var ret = undefined,
      func = command.replace(/[^a-z]/g, '_'),
      task = command.replace(/\.[a-z-_]+$/g, '');

    if (this[func] && typeof this[func] === 'function') {
      ret = this[func](props);
    }
    else {
      this.http_post(command, props);
    }

    // update menu state
    $('li', $('#navigation')).removeClass('active');
    $('li.'+task, ('#navigation')).addClass('active');

    return ret === false ? false : obj ? false : true;
  };

  this.set_busy = function(a, message)
  {
    if (a && message) {
      var msg = this.t(message);
      if (msg == message)
        msg = 'Loading...';

      this.display_message(msg, 'loading');
    }
    else if (!a) {
      this.hide_message('loading');
    }

    this.busy = a;

//    if (this.gui_objects.editform)
  //    this.lock_form(this.gui_objects.editform, a);

    // clear pending timer
    if (this.request_timer)
      clearTimeout(this.request_timer);

    // set timer for requests
    if (a && this.env.request_timeout)
      this.request_timer = window.setTimeout(function() { ref.request_timed_out(); }, this.request_timeout * 1000);
  };

  // called when a request timed out
  this.request_timed_out = function()
  {
    this.set_busy(false);
    this.display_message('Request timed out!', 'error');
  };

  // Add variable to GET string, replace old value if exists
  this.add_url = function(url, name, value)
  {
    value = urlencode(value);

    if (/(\?.*)$/.test(url)) {
      var urldata = RegExp.$1,
        datax = RegExp('((\\?|&)'+RegExp.escape(name)+'=[^&]*)');

      if (datax.test(urldata))
        urldata = urldata.replace(datax, RegExp.$2 + name + '=' + value);
      else
        urldata += '&' + name + '=' + value

      return url.replace(/(\?.*)$/, urldata);
    }
    else
      return url + '?' + name + '=' + value;
  };

  this.trigger_event = function(event, data)
  {
    if (this.events[event])
      for (var i in this.events[event])
        this.events[event][i](data);
  };

  this.add_event_listener = function(event, func)
  {
    if (!this.events[event])
      this.events[event] = [];

    this.events[event].push(func);
  };


  /*********************************************************/
  /*********           GUI functionality           *********/
  /*********************************************************/

  // write to the document/window title
  this.set_pagetitle = function(title)
  {
    if (title && document.title)
      document.title = title;
  };

  // display a system message (types: loading, notice, error)
  this.display_message = function(msg, type, timeout)
  {
    var obj, ref = this;

    if (!type)
      type = 'notice';
    if (msg)
      msg = this.t(msg);

    if (type == 'loading') {
      timeout = this.request_timeout * 1000;
      if (!msg)
        msg = this.t('loading');
    }
    else if (!timeout)
      timeout = this.message_time * (type == 'error' || type == 'warning' ? 2 : 1);

    obj = $('<div>');

    if (type != 'loading') {
      msg = '<div><span>' + msg + '</span></div>';
      obj.addClass(type).click(function() { return ref.hide_message(); });
    }

    if (timeout > 0)
      window.setTimeout(function() { ref.hide_message(type, type != 'loading'); }, timeout);

    obj.attr('id', type == 'loading' ? 'loading' : 'message')
      .appendTo('body').html(msg).show();
  };

  // make a message to disapear
  this.hide_message = function(type, fade)
  {
    if (type == 'loading')
      $('#loading').remove();
    else
      $('#message').fadeOut('normal', function() { $(this).remove(); });
  };

  this.set_watermark = function(id)
  {
    if (this.env.watermark)
      $('#'+id).html(this.env.watermark);
  }


  /********************************************************/
  /*********        Remote request methods        *********/
  /********************************************************/

  // compose a valid url with the given parameters
  this.url = function(action, query)
  {
    var k, param = {},
      querystring = typeof query === 'string' ? '&' + query : '';

    if (typeof action !== 'string')
      query = action;
    else if (!query || typeof query !== 'object')
      query = {};

    // overwrite task name
    if (action) {
      if (action.match(/^([a-z]+)/i))
        query.task = RegExp.$1;
      if (action.match(/[^a-z0-9-_]([a-z0-9-_]+)$/i))
        query.action = RegExp.$1;
    }

    // remove undefined values
    for (k in query) {
      if (query[k] !== undefined && query[k] !== null)
        param[k] = query[k];
    }

    return '?' + $.param(param) + querystring;
  };

  // send a http POST request to the server
  this.http_post = function(action, postdata)
  {
    var url = this.url(action);

    if (postdata && typeof postdata === 'object')
      postdata.remote = 1;
    else {
      if (!postdata)
        postdata = '';
      postdata += '&remote=1';
    }

    this.set_request_time();

    return $.ajax({
      type: 'POST', url: url, data: postdata, dataType: 'json',
      success: function(data) { kadm.http_response(data); },
      error: function(o, status, err) { kadm.http_error(o, status, err); }
    });
  };

  // send a http POST request to the API service
  this.api_post = function(action, postdata, func)
  {
    var url = 'api/' + action;

    if (!func) func = 'api_response';

    this.set_request_time();

    return $.ajax({
      type: 'POST', url: url, data: JSON.stringify(postdata), dataType: 'json',
      success: function(data) { kadm[func](data); },
      error: function(o, status, err) { kadm.http_error(o, status, err); }
    });
  };

  // handle HTTP response
  this.http_response = function(response)
  {
    var i;

    if (!response)
      return;

    // set env vars
    if (response.env)
      this.set_env(response.env);

    // HTML page elements
    if (response.objects)
      for (i in response.objects)
        $('#'+i).html(response.objects[i]);

    // we have translation labels to add
    if (typeof response.labels === 'object')
      this.tdef(response.labels);

    this.update_request_time();
    this.set_busy(false);

    // if we get javascript code from server -> execute it
    if (response.exec)
      eval(response.exec);
  };

  // handle HTTP request errors
  this.http_error = function(request, status, err)
  {
    var errmsg = request.statusText;

    this.set_busy(false);
    request.abort();

    if (request.status && errmsg)
      this.display_message(this.t('servererror') + ' (' + errmsg + ')', 'error');
  };

  this.api_response = function(response)
  {
    this.update_request_time();
    this.set_busy(false);

    if (!response || response.status != 'OK') {
      var msg = response && response.reason ? response.reason : this.t('servererror');
      this.display_message(msg, 'error');

      // Logout on invalid-session error
      if (response.code == 403)
        this.main_logout();

      return false;
    }

    return true;
  };


  /********************************************************/
  /*********            Helper methods            *********/
  /********************************************************/

  // disable/enable all fields of a form
  this.lock_form = function(form, lock)
  {
    if (!form || !form.elements)
      return;

    var n, len, elm;

    if (lock)
      this.disabled_form_elements = [];

    for (n=0, len=form.elements.length; n<len; n++) {
      elm = form.elements[n];

      if (elm.type == 'hidden')
        continue;
      // remember which elem was disabled before lock
      if (lock && elm.disabled)
        this.disabled_form_elements.push(elm);
      // check this.disabled_form_elements before inArray() as a workaround for FF5 bug
      // http://bugs.jquery.com/ticket/9873
      else if (lock || (this.disabled_form_elements && $.inArray(elm, this.disabled_form_elements)<0))
        elm.disabled = lock;
    }
  };

  this.set_request_time = function()
  {
    this.env.request_time = (new Date()).getTime();
  };

  // Update request time element
  this.update_request_time = function()
  {
    if (this.env.request_time) {
      var t = ((new Date()).getTime() - this.env.request_time)/1000,
        el = $('#reqtime');
      el.text(el.text().replace(/[0-9.,]+/, t));
    }
  };

  /*********************************************************/
  /*********                 Forms                 *********/
  /*********************************************************/

  this.serialize_form = function(id)
  {
    var i, v, json = {},
      form = $(id),
      query = form.serializeArray(),
      extra = this.env.extra_fields;

    for (i in query)
      json[query[i].name] = query[i].value;

    // read extra (disabled) fields
    for (i=0; i<extra.length; i++)
      if (v = $('[name="'+extra[i]+'"]', form).val())
        json[extra[i]] = v;

    this.trigger_event('form-serialize', {id: id, json: json});

    // convert values of list elements to array type
    $('textarea[data-type="list"]', form).each(function() {
      var name = this.name;
      // maybe already converted by skin engine
      if (json[name] && !$.isArray(json[name]))
        json[name] = $(this).val().split("\n");
    });

    return json;
  };

  this.form_value_change = function(events)
  {
    var i, j, e, elem, name, elem_name,
      form = $('#'+this.env.form_id),
      type_id = $('[name="user_type_id"]', form).val(),
      data = {user_type_id: type_id, attributes: []};

    this.set_busy(true, 'loading');

    for (i=0; i<events.length; i++) {
      name = events[i];
      e = this.env.auto_fields[name];

      if (!e)
        continue;

      data.attributes.push(name);
      for (j=0; j<e.data.length; j++) {
        elem_name = e.data[j];
        if (!data[elem_name] && (elem = $('[name="'+elem_name+'"]', form)))
          data[elem_name] = elem.val();
      }
    }

    this.api_post('form_value.generate', data, 'form_value_response');
    this.set_busy(false);
  };

  this.form_value_response = function(response)
  {
    var i, val;
    if (!this.api_response(response))
      return;

    for (i in response.result) {
      val = response.result[i];
      if ($.isArray(val))
        val = val.join("\n");
      $('[name="'+i+'"]').val(val);

      this.trigger_event('form-element-update', {name: i});
    }
  };

  this.form_value_error = function(name)
  {
    $('[name="'+name+'"]', $('#'+this.env.form_id)).addClass('error');
  }

  this.form_error_clear = function()
  {
    $('input,textarea', $('#'+this.env.form_id)).removeClass('error');

  }

  /*********************************************************/
  /*********          Client commands              *********/
  /*********************************************************/

  this.main_logout = function()
  {
    location.href = '?task=main&action=logout';
    return false;
  };

  this.user_info = function(id)
  {
    this.http_post('user.info', {id: id});
  };

  this.user_list = function(props)
  {
    if (!props)
      props = {};

    if (props.search === undefined && this.env.search_request)
      props.search_request = this.env.search_request;

    this.http_post('user.list', props);
  };

  this.user_delete = function(userid)
  {
    this.set_busy(true, 'deleting');
    this.api_post('user.delete', {user: userid}, 'user_delete_response');
  };

  this.user_delete_response = function(response)
  {
    if (!this.api_response(response))
      return;

    var page = this.env.list_page;

    // goto previous page if last user on the current page has been deleted
    if (this.env.list_count)
      page -= 1;

    this.display_message('user.delete.success');
    this.command('user.list', {page: page});
  };

  this.user_save = function(reload, section)
  {
    var data = this.serialize_form('#'+this.env.form_id);

    if (reload) {
      data.section = section;
      this.http_post('user.add', {data: data});
      return;
    }

    this.form_error_clear();

    // check password
    if (data.userpassword != data.userpassword2) {
      this.display_message('user.password.mismatch', 'error');
      this.form_value_error('userpassword2');
      return;
    }

    this.set_busy(true, 'saving');
    this.api_post('user.add', data, 'user_save_response');
  };

  this.user_save_response = function(response)
  {
    if (!this.api_response(response))
      return;

    this.display_message('user.add.success');
    this.command('user.list', {page: this.env.list_page});
  };

  this.group_info = function(id)
  {
    this.http_post('group.info', {id: id});
  };

  this.group_list = function(props)
  {
    if (!props)
      props = {};

    if (props.search === undefined && this.env.search_request)
      props.search_request = this.env.search_request;

    this.http_post('group.list', props);
  };

  this.group_delete = function(groupid)
  {
    this.set_busy(true, 'deleting');
    this.api_post('group.delete', {group: userid}, 'group_delete_response');
  };

  this.group_delete_response = function(response)
  {
    if (!this.api_response(response))
      return;

    var page = this.env.list_page;

    // goto previous page if last user on the current page has been deleted
    if (this.env.list_count)
      page -= 1;

    this.display_message('group.delete.success');
    this.command('group.list', {page: page});
  };

};

var kadm = new kolab_admin();
