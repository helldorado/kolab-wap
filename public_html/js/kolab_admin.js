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
      if (response && response.code == 403)
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
  /*********     keyboard autocomplete methods     *********/
  /*********************************************************/

  this.ac_init = function(obj, props)
  {
    obj.keydown(function(e) { return kadm.ac_keydown(e, props); })
      .attr('autocomplete', 'off');
  };

  // handler for keyboard events on autocomplete-fields
  this.ac_keydown = function(e, props)
  {
    if (this.ac_timer)
      clearTimeout(this.ac_timer);

    var highlight, key = e.which;

    switch (key) {
      case 38:  // arrow up
      case 40:  // arrow down
        if (!this.ac_visible())
          break;

        var dir = key == 38 ? 1 : 0;

        highlight = $('.selected', this.ac_pane).get(0);

        if (!highlight)
          highlight = this.ac_pane.__ul.firstChild;

        if (highlight)
          this.ac_select(dir ? highlight.previousSibling : highlight.nextSibling);

        return e.stopPropagation();

      case 9:   // tab
        if (e.shiftKey || !this.ac_visible()) {
          this.ac_stop();
          return;
        }

      case 13:  // enter
        if (!this.ac_visible())
          return false;

        // insert selected item and hide selection pane
        this.ac_insert(this.ac_selected);
        this.ac_stop();

        return e.stopPropagation();

      case 27:  // escape
        this.ac_stop();
        return;

      case 37:  // left
      case 39:  // right
        if (!e.shiftKey)
	      return;
    }

    // start timer
    this.ac_timer = window.setTimeout(function() { kadm.ac_start(props); }, 200);
    this.ac_input = e.target;

    return true;
  };

  this.ac_visible = function()
  {
    return (this.ac_selected !== null && this.ac_selected !== undefined && this.ac_value);
  };

  this.ac_select = function(node)
  {
    if (!node)
      return;

    var current = $('.selected', this.ac_pane);

    if (current.length)
      current.removeClass('selected');

    $(node).addClass('selected');
    this.ac_selected = node._id;
  };

  // autocomplete search processor
  this.ac_start = function(props)
  {
    var q = this.ac_input ? this.ac_input.value : null,
      min = this.env.autocomplete_min_length,
      old_value = this.ac_value,
      ac = this.ac_data;

    if (q === null)
      return;

    // trim query string
    q = $.trim(q);

    // Don't (re-)search if the last results are still active
    if (q == old_value)
      return;

    // Stop and destroy last search
    this.ac_stop();

    if (q.length && q.length < min) {
      if (!this.ac_info) {
        this.ac_info = this.display_message(
          this.t('search.acchars').replace('$min', min));
      }
      return;
    }

    this.ac_value = q;

    // ...string is empty
    if (!q.length)
      return;

    // ...new search value contains old one, but the old result was empty
    if (old_value && old_value.length && q.indexOf(old_value) == 0 && this.ac_result && !this.ac_result.length)
      return;

    var i, xhr, data = props,
      action = props && props.action ? props.action : 'form_value.list_options';

    this.ac_oninsert = props.oninsert;
    data.search = q;
    delete data['action'];
    delete data['insert_func'];

    this.display_message(this.t('search.loading'), 'loading');
    xhr = this.api_post(action, data, 'ac_result');
    this.ac_data = xhr;
  };

  this.ac_result = function(response)
  {
    // search stopped in meantime?
    if (!this.ac_value)
      return;

    if (!this.api_response(response))
      return;

    // ignore this outdated search response
    if (this.ac_input && response.result.search != this.ac_value)
      return;

    // display search results
    var i, ul, li, text,
      result = response.result.list,
      pos = $(this.ac_input).offset(),
      value = this.ac_value,
      rx = new RegExp('(' + RegExp.escape(value) + ')', 'ig');

    // create results pane if not present
    if (!this.ac_pane) {
      ul = $('<ul>');
      this.ac_pane = $('<div>').attr('id', 'autocompletepane')
        .css({ position:'absolute', 'z-index':30000 }).append(ul).appendTo(document.body);
      this.ac_pane.__ul = ul[0];
    }

    ul = this.ac_pane.__ul;

    // reset content
    ul.innerHTML = '';
    // move the results pane right under the input box
    this.ac_pane.css({left: (pos.left - 1)+'px', top: (pos.top + this.ac_input.offsetHeight - 1)+'px', display: 'none'});

    // add each result line to the list
    for (i in result) {
      text = result[i];
      li = document.createElement('LI');
      li.innerHTML = text.replace(rx, '##$1%%').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/##([^%]+)%%/g, '<b>$1</b>');
      li.onmouseover = function() { kadm.ac_select(this); };
      li.onmouseup = function() { kadm.ac_click(this) };
      li._id = i;
      ul.appendChild(li);
    }

    if (ul.childNodes.length) {
      this.ac_pane.show();

      // select the first
      li = $('li:first', ul);
      li.addClass('selected');
      this.ac_selected = li.get(0)._id;
    }

    this.env.ac_result = result;
  };

  this.ac_click = function(node)
  {
    if (this.ac_input)
      this.ac_input.focus();

    this.ac_insert(node._id);
    this.ac_stop();
  };

  this.ac_insert = function(id)
  {
    var val = this.env.ac_result[id];

    if (typeof this.ac_oninsert == 'function')
      this.ac_oninsert(id, val);
    else
      $(this.ac_input).val(val);
  };

  this.ac_blur = function()
  {
    if (this.ac_timer)
      clearTimeout(this.ac_timer);

    this.ac_input = null;
    this.ac_stop();
  };

  this.ac_stop = function()
  {
    this.ac_selected = null;
    this.ac_value = '';

    if (this.ac_pane)
      this.ac_pane.hide();

    this.ac_destroy();
  };

  // Clears autocomplete data/requests
  this.ac_destroy = function()
  {
    if (this.ac_data)
      this.ac_data.abort();

    if (this.ac_info)
      this.hide_message(this.ac_info);

    if (this.ac_msg)
      this.hide_message(this.ac_msg);

    this.ac_data = null;
    this.ac_info = null;
    this.ac_msg = null;
  };


  /*********************************************************/
  /*********            Forms widgets              *********/
  /*********************************************************/

  // Form initialization
  this.form_init = function(id)
  {
    var form = $('#'+id);

    this.trigger_event('form-load', id);

    // replace some textarea fields with pretty/smart input lists
    $('textarea[data-type="list"]', form)
      .each(function() { kadm.form_element_wrapper(this); });
  };

  // Form serialization
  this.form_serialize = function(data)
  {
    var form = $(data.id);

    // replace some textarea fields with pretty/smart input lists
    $('textarea[data-type="list"]', form).not('disabled').each(function() {
      var i, v, value = [],
        re = RegExp('^' + RegExp.escape(this.name) + '\[[0-9-]+\]$');

      for (i in data.json) {
        if (i.match(re)) {
          if (v = $('input[name="'+i+'"]', form).val())
            value.push(v);
          delete data.json[i];
        }
      }

      // autocompletion lists data is stored in env variable
      if (kadm.env.assoc_fields[this.name]) {
        value = [];
        for (i in kadm.env.assoc_fields[this.name])
          value.push(i);
      }

      data.json[this.name] = value;
    });

    return data;
  };

  // Form element update handler
  this.form_element_update = function(data)
  {
    var elem = $('[name="'+data.name+'"]');

    if (!elem.length)
      return;

    if (elem.attr('data-type') == 'list') {
      // remove old wrapper
      $('span[class="listarea"]', elem.parent()).remove();
      // insert new list element
      this.form_element_wrapper(elem.get(0));
    }
  };

  // Replaces form element with smart element
  this.form_element_wrapper = function(form_element)
  {
    var i, j = 0, len, elem, e = $(form_element),
      list = this.env.assoc_fields[form_element.name],
      disabled = e.attr('disabled') || e.attr('readonly'),
      autocomplete = e.attr('data-autocomplete'),
      maxlength = e.attr('data-maxlength'),
      area = $('<span class="listarea"></span>');

    e.hide();

    // add autocompletion input
    if (!disabled && autocomplete) {
      elem = this.form_list_element(form_element.form, {
        maxlength: maxlength,
        autocomplete: autocomplete,
        element: e
      }, -1);

      elem.appendTo(area);
      this.ac_init(elem, {attribute: form_element.name, oninsert: this.form_element_oninsert});
    }

    if (!list) {
      if (form_element.value)
        list = $.extend({}, form_element.value.split("\n"));
      else if (!autocomplete)
        list = {0: ''};
    }

    // add input rows
    for (i in list) {
      elem = this.form_list_element(form_element.form, {
        value: list[i],
        key: i,
        disabled: disabled,
        maxlength: maxlength,
        autocomplete: autocomplete,
        element: e
      }, j++);

      elem.appendTo(area);
    }

    if (disabled)
      area.addClass('readonly');
    if (autocomplete)
      area.addClass('autocomplete');

    area.appendTo(form_element.parentNode);
  };

  // Creates smart list element
  this.form_list_element = function(form, data, idx)
  {
    var content, elem, input,
      key = data.key,
      orig = data.element
      ac = data.autocomplete;

    data.name = data.name || orig.attr('name') + '[' + idx + ']';
    data.disabled = data.disabled || (ac && idx >= 0);
    data.readonly = data.readonly || (ac && idx >= 0);

    // remove internal attributes
    delete data['element'];
    delete data['autocomplete'];
    delete data['key'];

    // build element content
    content = '<span class="listelement"><span class="actions">'
      + (!ac ? '<span title="" class="add"></span>' : ac && idx == -1 ? '<span title="" class="search"></span>' : '')
      + (!ac || idx >= 0 ? '<span title="" class="reset"></span>' : '')
      + '</span><input></span>';

    elem = $(content);
    input = $('input', elem);

    // Set INPUT attributes
    input.attr(data);

    if (data.readonly)
      input.addClass('readonly');

    if (ac && idx == -1)
      input.addClass('autocomplete');

    if (data.disabled && !ac)
      return elem;

    // attach element creation event
    if (!ac)
      $('span[class="add"]', elem).click(function() {
        var dt = (new Date()).getTime(),
          span = $(this.parentNode.parentNode),
          name = data.name.replace(/\[[0-9]+\]$/, ''),
          elem = kadm.form_list_element(form, {name: name}, dt);

        span.after(elem);
        $('input', elem).focus();
        kadm.ac_stop();
      });

    // attach element deletion event
    if (!ac || idx >= 0)
      $('span[class="reset"]', elem).click(function() {
        var span = $(this.parentNode.parentNode),
          name = data.name.replace(/\[[0-9]+\]$/, ''),
          l = $('input[name^="' + name + '"]', form),
          key = $(this).data('key');

        if (ac || l.length > 1)
          span.remove();
        else
          $('input', span).val('').focus();

        // delete key from internal field representation
        if (key !== undefined && kadm.env.assoc_fields[name])
          delete kadm.env.assoc_fields[name][key];

        kadm.ac_stop();
      }).data('key', key);

    return elem;
  };

  this.form_element_oninsert = function(key, val)
  {
    var elem, input = $(this.ac_input).get(0),
      dt = (new Date()).getTime(),
      span = $(input.parentNode),
      name = input.name.replace(/\[-1\]$/, ''),
      af = kadm.env.assoc_fields;

    // reset autocomplete input
    input.value = '';

    // check if element doesn't exist on the list already
    if (!af[name])
      af[name] = {};
    if (af[name][key])
      return;

    // add element
    elem = kadm.form_list_element(input.form, {
      name: name,
      autocomplete: true,
      value: val
      }, dt);
    span.after(elem);

    // update field variable
    af[name][key] = val;
  };


  /*********************************************************/
  /*********                 Forms                 *********/
  /*********************************************************/

  this.serialize_form = function(id)
  {
    var i, v, json = {},
      form = $(id),
      query = form.serializeArray(),
      extra = this.env.extra_fields || [];

    for (i in query)
      json[query[i].name] = query[i].value;

    // read extra (disabled) fields
    for (i=0; i<extra.length; i++)
      if (v = $('[name="'+extra[i]+'"]', form).val())
        json[extra[i]] = v;

    // serializeArray() doesn't work properly for multi-select
    $('select[multiple="multiple"]', form).each(function() {
      var name = this.name;
      json[name] = [];
      $(':selected', this).each(function() {
        json[name].push(this.value);
      });
    });

    this.form_serialize({id: id, json: json});

    return json;
  };

  this.form_value_change = function(events)
  {
    var i, j, e, elem, name, elem_name,
      form = $('#'+this.env.form_id),
      type_id = $('[name="type_id"]', form).val(),
      object_type = $('[name="object_type"]', form).val(),
      data = {type_id: type_id, object_type: object_type, attributes: []};

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
      // @TODO: indexed list support
      if ($.isArray(val))
        val = val.join("\n");
      $('[name="'+i+'"]').val(val);

      this.form_element_update({name: i});
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

  this.group_save = function(reload, section)
  {
    var data = this.serialize_form('#'+this.env.form_id);

    if (reload) {
      data.section = section;
      this.http_post('group.add', {data: data});
      return;
    }

    this.form_error_clear();

    this.set_busy(true, 'saving');
    this.api_post('group.add', data, 'group_save_response');
  };

  this.group_save_response = function(response)
  {
    if (!this.api_response(response))
      return;

    this.display_message('group.add.success');
    this.command('group.list', {page: this.env.list_page});
  };

};

// Add escape() method to RegExp object
// http://dev.rubyonrails.org/changeset/7271
RegExp.escape = function(str)
{
  return String(str).replace(/([.*+?^=!:${}()|[\]\/\\])/g, '\\$1');
};

// Initialize application object (don't change var name!)
var kadm = new kolab_admin();

// general click handler
$(document).click(function() {
  // destroy autocompletion
  kadm.ac_stop();
});
