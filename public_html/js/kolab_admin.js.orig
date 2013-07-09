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
      success: function(response) { kadm.http_response(response); },
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
      contentType: 'application/json; charset=utf-8',
      success: function(response) { kadm[func](response); },
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

    // we have translation labels to add
    if (typeof response.labels === 'object')
      this.tdef(response.labels);

    // HTML page elements
    if (response.objects)
      for (i in response.objects)
        $('#'+i).html(response.objects[i]);

    this.update_request_time();
    this.set_busy(false);

    // if we get javascript code from server -> execute it
    if (response.exec)
      eval(response.exec);

    this.trigger_event('http-response', response);
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
      // Logout on invalid-session error
      if (response && response.code == 403)
        this.main_logout();
      else
        this.display_message(response && response.reason ? response.reason : this.t('servererror'), 'error');

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

  // position and display popup
  this.popup_show = function(e, popup)
  {
    var popup = $(popup),
      pos = this.mouse_pos(e),
      win = $(window),
      w = popup.width(),
      h = popup.height(),
      left = pos.left - w,
      top = pos.top;

    if (top + h > win.height())
      top -= h;
    if (left + w > win.width())
      left -= w;

    popup.css({left: left + 'px', top: top + 'px'}).show();
    e.stopPropagation();
  };

  // Return absolute mouse position of an event
  this.mouse_pos = function(e)
  {
    if (!e) e = window.event;

    var mX = (e.pageX) ? e.pageX : e.clientX,
      mY = (e.pageY) ? e.pageY : e.clientY;

    if (document.body && document.all) {
      mX += document.body.scrollLeft;
      mY += document.body.scrollTop;
    }

    if (e._offset) {
      mX += e._offset.left;
      mY += e._offset.top;
    }

    return { left:mX, top:mY };
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
    this.ac_timer = window.setTimeout(function() { kadm.ac_start(props); }, 500);
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
      this.display_message(this.t('search.acchars').replace('$min', min), 'notice', 2000);
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

    this.ac_data = null;
    this.ac_info = null;
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
      .each(function() { kadm.form_list_element_wrapper(this); });
    // create smart select fields
    $('input[data-type="select"]', form)
      .each(function() { kadm.form_select_element_wrapper(this); });
    // create LDAP URL fields
    $('input[data-type="ldap_url"]:not(:disabled):not([readonly])', form)
      .each(function() { kadm.form_url_element_wrapper(this); });
  };

  // Form serialization
  this.form_serialize = function(data)
  {
    var form = $(data.id);

    // smart list fields
    $('textarea[data-type="list"]:not(:disabled)', form).each(function() {
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

    // smart selects
    $('input[data-type="select"]', form).each(function() {
      delete data.json[this.name];
    });

    // LDAP URL fields
    $('input[data-type="ldap_url"]:not(:disabled):not([readonly])', form).each(function() {
      data.json = kadm.form_url_element_submit(this.name, data.json, form);
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
      this.form_list_element_wrapper(elem.get(0));
    }
  };

  // Replaces form element with smart list element
  this.form_list_element_wrapper = function(form_element)
  {
    var i = 0, j = 0, list = [], elem, e = $(form_element),
      form = form_element.form,
      disabled = e.attr('disabled'),
      readonly = e.attr('readonly'),
      autocomplete = e.attr('data-autocomplete'),
      maxlength = e.attr('data-maxlength'),
      area = $('<span class="listarea"></span>');

    e.hide();

    if (autocomplete)
      list = this.env.assoc_fields ? this.env.assoc_fields[form_element.name] : [];
    else if (form_element.value)
      list = form_element.value.split("\n");

    // Need at least one element
    if (!autocomplete || disabled || readonly) {
      $.each(list, function() { i++; });
      if (!i)
        list = [''];
    }

    // Create simple list for readonly/disabled
    if (disabled || readonly) {
      area.addClass('readonly');

      // add simple input rows
      $.each(list, function(i, v) {
        var elem = $('<input>');
        elem.attr({
          value: v,
          disabled: disabled,
          readonly: readonly,
          name: form_element.name + '[' + (j++) + ']'
          })
        elem = $('<span class="listelement">').append(elem);
        elem.appendTo(area);
      });
    }
    // extended widget with add/remove buttons and/or autocompletion
    else {
      // add autocompletion input
      if (autocomplete) {
        elem = this.form_list_element(form, {
          maxlength: maxlength,
          autocomplete: autocomplete,
          element: e
        }, -1);

        // Initialize autocompletion
        var props = {attribute: form_element.name, oninsert: this.form_element_oninsert};
        if (i = $('[name="type_id"]', form).val())
          props.type_id = i;
        if (i = $('[name="object_type"]', form).val())
          props.object_type = i;
        if (i = $('[name="id"]', form).val())
          props.id = i;
        this.ac_init(elem, props);

        elem.appendTo(area);
        area.addClass('autocomplete');
      }

      // add input rows
      $.each(list, function(i, v) {
        var elem = kadm.form_list_element(form, {
          value: v,
          key: i,
          maxlength: maxlength,
          autocomplete: autocomplete,
          element: e
        }, j++);

        elem.appendTo(area);
      });
    }

    area.appendTo(form_element.parentNode);
  };

  // Creates smart list element
  this.form_list_element = function(form, data, idx)
  {
    var content, elem, input,
      key = data.key,
      orig = data.element
      ac = data.autocomplete;

    data.name = (orig ? orig.attr('name') : data.name) + '[' + idx + ']';
    data.readonly = (ac && idx >= 0);

    // remove internal attributes
    delete data['element'];
    delete data['autocomplete'];
    delete data['key'];

    // build element content
    content = '<span class="listelement"><span class="actions">'
      + (!ac ? '<span title="" class="add"></span>' : ac && idx == -1 ? '<span title="" class="search"></span>' : '')
      + (!ac || idx >= 0 ? '<span title="" class="reset"></span>' : '')
      + '</span><input type="text" autocomplete="off"></span>';

    elem = $(content);
    input = $('input', elem);

    // Set INPUT attributes
    input.attr(data);

    if (data.readonly)
      input.addClass('readonly');
    if (ac)
      input.addClass('autocomplete');

    // attach element creation event
    if (!ac)
      $('span[class="add"]', elem).click(function() {
        var name = data.name.replace(/\[[0-9]+\]$/, ''),
          span = $(this.parentNode.parentNode),
          maxcount = $('textarea[name="'+name+'"]').attr('data-maxcount');

        // check element count limit
        if (maxcount && maxcount <= span.parent().children().length) {
          alert(kadm.t('form.maxcount.exceeded'));
          return;
        }

        var dt = (new Date()).getTime(),
          elem = kadm.form_list_element(form, {name: name}, dt);

        kadm.ac_stop();
        span.after(elem);
        $('input', elem).focus();
      });

    // attach element deletion event
    if (!ac || idx >= 0)
      $('span[class="reset"]', elem).click(function() {
        var span = $(this.parentNode.parentNode),
          name = data.name.replace(/\[[0-9]+\]$/, ''),
          l = $('input[name^="' + name + '"]', form),
          key = $(this).data('key');

        if (l.length > 1 || $('input[name="' + name + '"]', form).attr('data-autocomplete'))
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
      af = kadm.env.assoc_fields,
      maxcount = $('textarea[name="'+name+'"]').attr('data-maxcount');

    // reset autocomplete input
    input.value = '';

    // check element count limit
    if (maxcount && maxcount <= span.parent().children().length - 1) {
      alert(kadm.t('form.maxcount.exceeded'));
      return;
    }

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

  // Replaces form element with smart select element
  this.form_select_element_wrapper = function(form_element)
  {
    var e = $(form_element),
      form = form_element.form,
      elem = $('<span class="link"></span>'),
      area = $('<span class="listarea autocomplete select popup"></span>'),
      content = $('<span class="listcontent"></span>'),
      list = this.env.assoc_fields ? this.env.assoc_fields[form_element.name] : [];

    elem.text(e.val()).css({cursor: 'pointer'})
      .click(function(e) {
        var popup = $('span.listarea', this.parentNode);
        kadm.popup_show(e, popup);
        $('input', popup).val('').focus();
        $('span.listcontent > span.listelement', popup).removeClass('selected').show();
      })
      .appendTo(form_element.parentNode);

    if (list.length <= 1)
      return;

    if (form_element.type != 'hidden') e.hide();
    area.hide();

    elem = this.form_list_element(form, {
      autocomplete: true,
      element: e
    }, -1);

    elem.appendTo(area);
    content.appendTo(area);

    // popup events
    $('input', area)
      .click(function(e) {
        // stop click on the popup
        e.stopPropagation();
      })
      .keypress(function(e) {
        // prevent form submission with Enter key
        if (e.which == 13)
          e.preventDefault();
      })
      .keyup(function(e) {
        // filtering
        var s = this.value,
          options = $('span.listcontent > span.listelement', area);

        // Enter key
        if (e.which == 13) {
          options.filter('.selected').click()
          return;
        }
        // Escape
        else if (e.which == 27) {
          area.hide();
          this.value = s = '';
        }
        // UP/Down arrows
        else if (e.which == 38 || e.which == 40) {
          options = options.not(':hidden');
          var selected = options.filter('.selected');

          if (!selected.length) {
            if (e.which == 40) // Down key
              options.first().addClass('selected').parent().get(0).scrollTop = 0;
          }
          else {
            var focused = selected[e.which == 40 ? 'next' : 'prev']();

            while (focused.length && focused.is(':hidden'))
              focused = selected[e.which == 40 ? 'next' : 'prev']();

            if (!focused.length)
              focused = options[e.which == 40 ? 'first' : 'last']();

            if (focused.length) {
              selected.removeClass('selected');
              focused.addClass('selected');

              var parent = focused.parent(),
                parent_height = parent.height(),
                parent_top = parent.get(0).scrollTop,
                top = focused.offset().top - parent.offset().top,
                height = focused.height();

              if (top < 0)
                parent.get(0).scrollTop = 0;
              else if (top >= parent_height)
                parent.get(0).scrollTop = top - parent_height + height + parent_top;
            }
          }

          return;
        }

        if (!s) {
          options.show().removeClass('selected');
          return;
        }

        options.each(function() {
          var o = $(this), v = o.data('value');
          o[v.indexOf(s) != -1 ? 'show' : 'hide']().removeClass('selected');
        });

        options = options.not(':hidden');
        if (options.length == 1)
          options.addClass('selected');
      });

    // add option rows
    $.each(list, function(i, v) {
      var elem = kadm.form_select_option_element(form, {value: v, key: v, element: e});
      elem.appendTo(content);
    });

    area.appendTo(form_element.parentNode);
  };

  // Creates option element for smart select
  this.form_select_option_element = function(form, data)
  {
    // build element content
    var elem = $('<span class="listelement"></span>')
      .data('value', data.key).text(data.value)
      .click(function(e) {
        var val = $(this).data('value'),
          elem = $(data.element),
          old_val = elem.val();

        $('span.link', elem.parent()).text(val);
        elem.val(val);
        if (val != old_val)
          elem.change();
      });

    return elem;
  };

  // Replaces form element with LDAP URL element
  this.form_url_element_wrapper = function(form_element)
  {
    var i, e = $(form_element),
      form = form_element.form,
      name = form_element.name,
      ldap = this.parse_ldap_url(e.val()) || {},
      options = ['sub', 'one', 'base'],
      div = $('<div class="ldap_url"><table></table></div>'),
      host = $('<input type="text">').attr({name: 'ldap_host_'+name, size: 30, value: ldap.host, 'class': 'ldap_host'}),
      port = $('<input type="text">').attr({name: 'ldap_port_'+name, size: 5, value: ldap.port || '389'}),
      base = $('<input type="text">').attr({name: 'ldap_base_'+name, value: ldap.base}),
      scope = $('<select>').attr({name: 'ldap_scope_'+name}),
      filter = $('<ul>'),
      row_host = $('<tr class="ldap_host"><td></td><td></td></tr>'),
      row_base = $('<tr class="ldap_base"><td></td><td></td></tr>'),
      row_scope = $('<tr class="ldap_scope"><td></td><td></td></tr>'),
      row_filter = $('<tr class="ldap_filter"><td></td><td></td></tr>');

    for (i in options)
      $('<option>').val(options[i]).text(this.t('ldap.'+options[i])).appendTo(scope);
    scope.val(ldap.scope);

    for (i in ldap.filter)
      filter.append(this.form_url_filter_element(name, i, ldap.filter[i]));
    if (!$('li', filter).length)
      filter.append(this.form_url_filter_element(name, 0, {}));

    e.hide();

    $('td:first', row_host).text(this.t('ldap.host'));
    $('td:last', row_host).append(host).append($('<span>').text(':')).append(port);
    $('td:first', row_base).text(this.t('ldap.basedn'));
    $('td:last', row_base).append(base);
    $('td:first', row_scope).text(this.t('ldap.scope'));
    $('td:last', row_scope).append(scope);
    $('td:first', row_filter).text(this.t('ldap.conditions'));
    $('td:last', row_filter).append(filter);
    $('table', div).append(row_host).append(row_base).append(row_scope).append(row_filter);
    $(form_element).parent().append(div);
  };

  this.form_url_filter_element = function(name, idx, filter)
  {
    var options = ['any', 'both', 'prefix', 'suffix', 'exact'],
      filter_type = $('<select>').attr({name: 'ldap_filter_type_'+name+'['+idx+']'}),
      filter_name = $('<input type="text">').attr({name: 'ldap_filter_name_'+name+'['+idx+']'}),
      filter_value = $('<input type="text">').attr({name: 'ldap_filter_value_'+name+'['+idx+']'}),
      a_add = $('<a class="button add" href="#add"></a>').click(function() {
        var dt = new Date().getTime();
        $(this.parentNode.parentNode).append(kadm.form_url_filter_element(name, dt, {}));
      }).attr({title: this.t('add')}),
      a_del = $('<a class="button delete" href="#delete"></a>').click(function() {
        if ($('li', this.parentNode.parentNode).length > 1)
          $(this.parentNode).remove();
        else {
          $('input', this.parentNode).val('');
          $('select', this.parentNode).val('any').change();
        }
      }).attr({title: this.t('delete')}),
      li = $('<li>');

    for (i in options)
      $('<option>').val(options[i]).text(this.t('ldap.filter_'+options[i])).appendTo(filter_type);

    if (filter.type)
      filter_type.val(filter.type);
    if (filter.name)
      filter_name.val(filter.name);
    if (filter.value)
      filter_value.val(filter.value);

    filter_type.change(function() {
      filter_value.css({display: $(this).val() == 'any' ? 'none' : 'inline'});
    }).change();

    return li.append(filter_name).append(filter_type).append(filter_value)
      .append(a_del).append(a_add);
  };

  // updates form data with LDAP URL (on form submit)
  this.form_url_element_submit = function(name, data, form)
  {
    var i, rx = new RegExp('^ldap_(host|port|base|scope|filter_name|filter_type|filter_value)_'+name+'(\\[|$)');

    for (i in data)
      if (rx.test(i))
        delete data[i];

    data[name] = this.form_url_element_save(name, form);

    return data;
  };

  // updates LDAP URL field
  this.form_url_element_save = function(name, form)
  {
    var url, form = $(form), params = {
      host: $('input[name="ldap_host_'+name+'"]', form).val(),
      port: $('input[name="ldap_port_'+name+'"]', form).val(),
      base: $('input[name="ldap_base_'+name+'"]', form).val(),
      scope: $('select[name="ldap_scope_'+name+'"]', form).val(),
      filter: []};

    $('input[name^="ldap_filter_name_'+name+'"]', form).each(function() {
      if (this.value && /\[([^\]]+)\]/.test(this.name)) {
        var suffix = name + '[' + RegExp.$1 + ']',
          type = $('select[name="ldap_filter_type_'+suffix+'"]', form).val(),
          value = type == 'any' ? '' : $('input[name="ldap_filter_value_'+suffix+'"]', form).val();

        params.filter.push({name: this.value, type: type, value: value, join: 'AND', level: 0});
      }
    });

    url = this.build_ldap_url(params);
    $('input[name="'+name+'"]').val(url);

    return url;
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
      id = $('[name="id"]', form).val(),
      type_id = $('[name="type_id"]', form).val(),
      object_type = $('[name="object_type"]', form).val(),
      data = {type_id: type_id, object_type: object_type, attributes: []};

    if (id)
      data.id = id;

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

  this.check_required_fields = function(data)
  {
    var i, n, is_empty, ret = true,
      req_fields = this.env.required_fields;

    for (i=0; i<req_fields.length; i++) {
      n = req_fields[i];
      is_empty = 0;

      if ($.isArray(data[n]))
        is_empty = (data[n].length == 0) ? 1 : 0;
      else
        is_empty = !data[n];

      if (is_empty) {
        this.form_value_error(n);
        ret = false;
      }
    }

    return ret;
  };

  /*********************************************************/
  /*********          Client commands              *********/
  /*********************************************************/

  this.main_logout = function(params)
  {
    location.href = '?task=main&action=logout' + (params ? '&' + $.param(params) : '');
    return false;
  };

  this.domain_info = function(id)
  {
    this.http_post('domain.info', {id: id});
  };

  this.domain_list = function(props)
  {
    this.list_handler('domain', props);
  };

  this.domain_delete = function(domainid)
  {
    this.set_busy(true, 'deleting');
    this.api_post('domain.delete', {domain: domainid}, 'domain_delete_response');
  };

  this.domain_save = function(reload, section)
  {
    var data = this.serialize_form('#'+this.env.form_id),
      action = data.id ? 'edit' : 'add';

    if (reload) {
      data.section = section;
      this.http_post('domain.' + action, {data: data});
      return;
    }

    this.form_error_clear();

    if (!this.check_required_fields(data)) {
      this.display_message('form.required.empty', 'error');
      return;
    }

    this.set_busy(true, 'saving');
    this.api_post('domain.' + action, data, 'domain_' + action + '_response');
  };

  this.domain_delete_response = function(response)
  {
    this.response_handler(response, 'domain.delete', 'domain.list');
  };

  this.domain_add_response = function(response)
  {
    this.response_handler(response, 'domain.add', 'domain.list');
  };

  this.domain_edit_response = function(response)
  {
    this.response_handler(response, 'domain.edit', 'domain.list');
  };

  this.user_info = function(id)
  {
    this.http_post('user.info', {id: id});
  };

  this.user_list = function(props)
  {
    this.list_handler('user', props);
  };

  this.user_delete = function(userid)
  {
    this.set_busy(true, 'deleting');
    this.api_post('user.delete', {user: userid}, 'user_delete_response');
  };

  this.user_save = function(reload, section)
  {
    var data = this.serialize_form('#'+this.env.form_id),
      action = data.id ? 'edit' : 'add';

    if (reload) {
      data.section = section;
      this.http_post('user.' + action, {data: data});
      return;
    }

    this.form_error_clear();

    // check password
    if (data.userpassword != data.userpassword2) {
      this.display_message('user.password.mismatch', 'error');
      this.form_value_error('userpassword2');
      return;
    }
    delete data['userpassword2'];

    if (!this.check_required_fields(data)) {
      this.display_message('form.required.empty', 'error');
      return;
    }

    this.set_busy(true, 'saving');
    this.api_post('user.' + action, data, 'user_' + action + '_response');
  };

  this.user_delete_response = function(response)
  {
    this.response_handler(response, 'user.delete', 'user.list');
  };

  this.user_add_response = function(response)
  {
    this.response_handler(response, 'user.add', 'user.list');
  };

  this.user_edit_response = function(response)
  {
    this.response_handler(response, 'user.edit', 'user.list');
  };

  this.group_info = function(id)
  {
    this.http_post('group.info', {id: id});
  };

  this.group_list = function(props)
  {
    this.list_handler('group', props);
  };

  this.group_delete = function(groupid)
  {
    this.set_busy(true, 'deleting');
    this.api_post('group.delete', {group: groupid}, 'group_delete_response');
  };

  this.group_save = function(reload, section)
  {
    var data = this.serialize_form('#'+this.env.form_id),
      action = data.id ? 'edit' : 'add';

    if (reload) {
      data.section = section;
      this.http_post('group.' + action, {data: data});
      return;
    }

    this.form_error_clear();

    if (!this.check_required_fields(data)) {
      this.display_message('form.required.empty', 'error');
      return;
    }

    this.set_busy(true, 'saving');
    this.api_post('group.' + action, data, 'group_' + action + '_response');
  };

  this.group_delete_response = function(response)
  {
    this.response_handler(response, 'group.delete', 'group.list');
  };

  this.group_add_response = function(response)
  {
    this.response_handler(response, 'group.add', 'group.list');
  };

  this.group_edit_response = function(response)
  {
    this.response_handler(response, 'group.edit', 'group.list');
  };

  this.resource_info = function(id)
  {
    this.http_post('resource.info', {id: id});
  };

  this.resource_list = function(props)
  {
    this.list_handler('resource', props);
  };

  this.resource_delete = function(resourceid)
  {
    this.set_busy(true, 'deleting');
    this.api_post('resource.delete', {resource: resourceid}, 'resource_delete_response');
  };

  this.resource_save = function(reload, section)
  {
    var data = this.serialize_form('#'+this.env.form_id),
      action = data.id ? 'edit' : 'add';

    if (reload) {
      data.section = section;
      this.http_post('resource.' + action, {data: data});
      return;
    }

    this.form_error_clear();

    if (!this.check_required_fields(data)) {
      this.display_message('form.required.empty', 'error');
      return;
    }

    this.set_busy(true, 'saving');
    this.api_post('resource.' + action, data, 'resource_' + action + '_response');
  };

  this.resource_delete_response = function(response)
  {
    this.response_handler(response, 'resource.delete', 'resource.list');
  };

  this.resource_add_response = function(response)
  {
    this.response_handler(response, 'resource.add', 'resource.list');
  };

  this.resource_edit_response = function(response)
  {
    this.response_handler(response, 'resource.edit', 'resource.list');
  };

  this.role_info = function(id)
  {
    this.http_post('role.info', {id: id});
  };

  this.role_list = function(props)
  {
    this.list_handler('role', props);
  };

  this.role_delete = function(roleid)
  {
    this.set_busy(true, 'deleting');
    this.api_post('role.delete', {role: roleid}, 'role_delete_response');
  };

  this.role_save = function(reload, section)
  {
    var data = this.serialize_form('#'+this.env.form_id),
      action = data.id ? 'edit' : 'add';

    if (reload) {
      data.section = section;
      this.http_post('role.' + action, {data: data});
      return;
    }

    this.form_error_clear();

    if (!this.check_required_fields(data)) {
      this.display_message('form.required.empty', 'error');
      return;
    }

    this.set_busy(true, 'saving');
    this.api_post('role.' + action, data, 'role_' + action + '_response');
  };

  this.role_delete_response = function(response)
  {
    this.response_handler(response, 'role.delete', 'role.list');
  };

  this.role_add_response = function(response)
  {
    this.response_handler(response, 'role.add', 'role.list');
  };

  this.role_edit_response = function(response)
  {
    this.response_handler(response, 'role.edit', 'role.list');
  };

  this.settings_type_info = function(id)
  {
    this.http_post('settings.type_info', {id: id});
  };

  this.settings_type_add = function()
  {
    this.http_post('settings.type_add', {type: $('#type_list_filter').val()});
  };

  this.settings_type_list = function(props)
  {
    if (!props)
      props = {};

    if (props.search === undefined && this.env.search_request)
      props.search_request = this.env.search_request;

    props.type = $('#type_list_filter').val();

    this.http_post('settings.type_list', props);
  };

  this.type_delete = function(id)
  {
    this.set_busy(true, 'deleting');
    this.api_post('type.delete', this.type_id_parse(id), 'type_delete_response');
  };

  this.type_save = function(reload, section)
  {
    var i, n, attr, request = {},
      data = this.serialize_form('#'+this.env.form_id),
      action = data.id ? 'edit' : 'add',
      required = this.env.attributes_required || [];

    if (reload) {
      data.section = section;
      this.http_post('type.' + action, {data: data});
      return;
    }

    this.form_error_clear();

    if (!this.check_required_fields(data)) {
      this.display_message('form.required.empty', 'error');
      return;
    }

    if (data.key.match(/[^a-z_-]/)) {
      this.display_message('attribute.key.invalid', 'error');
      return;
    }

    // remove objectClass from required attributes list
    required = $.map(required, function(a) { return a == 'objectClass' ? null : a; });

    request.id = data.id;
    request.key = data.key;
    request.name = data.name;
    request.type = data.type;
    request.description = data.description;
    request.used_for = data.used_for;
    request.attributes = {fields: {}, form_fields: {}, auto_form_fields: {}};
    request.attributes.fields.objectclass = data.objectclass;

    // Build attributes array compatible with the API format
    // @TODO: use attr_table format
    for (i in this.env.attr_table) {
      // attribute doesn't exist in specified object classes set
      if (!(n = this.env.attributes[i]))
        continue;

      // check required attributes
      if (required.length)
        required = $.map(required, function(a) { return a != n ? a : null; });

      attr = this.env.attr_table[i];
      data = {};

      if (attr.valtype == 'static') {
        request.attributes.fields[i] = attr.data;
        continue;
      }

      if (attr.type == 'list-autocomplete') {
        data.type = 'list';
        data.autocomplete = true;
      }
      else if (attr.type != 'text')
        data.type = attr.type;

      if ((attr.type == 'select' || attr.type == 'multiselect') && attr.values && attr.values.length)
        data.values = attr.values;

      if (attr.optional)
        data.optional = true;
      if (attr.maxcount)
        data.maxcount = attr.maxcount;

      if (attr.valtype == 'normal' || attr.valtype == 'auto')
        request.attributes.form_fields[i] = data;
      if (attr.valtype == 'auto' || attr.valtype == 'auto-readonly') {
        if (attr.data)
          data.data = attr.data.split(/,/);
        request.attributes.auto_form_fields[i] = data;
      }
    }

    if (required.length) {
      this.display_message(this.t('attribute.required.error').replace(/\$1/, required.join(',')), 'error');
      return;
    }

    this.set_busy(true, 'saving');
    this.api_post('type.' + action, request, 'type_' + action + '_response');
  };

  this.type_delete_response = function(response)
  {
    this.response_handler(response, 'type.delete', 'settings.type_list');
  };

  this.type_add_response = function(response)
  {
    this.response_handler(response, 'type.add', 'settings.type_list');
  };

  this.type_edit_response = function(response)
  {
    this.response_handler(response, 'type.edit', 'settings.type_list');
  };

  /*********************************************************/
  /*********       Various helper methods          *********/
  /*********************************************************/

  // universal API response handler
  this.response_handler = function(response, action, list)
  {
    if (!this.api_response(response))
      return;

    this.display_message(action + '.success');

    var page = this.env.list_page,
      list_id = list.replace(/[^a-z]/g, '');

    // if objects list exists
    if ($('#'+list_id).length) {
      // goto previous page if last record on the current page has been deleted
      if (this.env.list_page > 1 && this.env.list_size == 1 && action.match(/\.delete/))
        page -= 1;

      this.command(list, {page: page});
      this.set_watermark('taskcontent');
    }
  };

  // universal list request handler
  this.list_handler = function(type, props)
  {
    if (!props)
      props = {};

    if (props.search === undefined && this.env.search_request)
      props.search_request = this.env.search_request;

    this.http_post(type + '.list', props);
  };

  // Parses object type identifier
  this.type_id_parse = function(id)
  {
    var id = String(id).split(':');
    return {type: id[0], id: id[1]};
  };

  // Removes attribute row
  this.type_attr_delete = function(attr)
  {
    $('#attr_table_row_' + attr).remove();
    $('select[name="attr_name"] > option[value="'+attr+'"]').show();

    delete this.env.attr_table[attr];
    this.type_attr_cancel();
  };

  // Displays attribute edition form
  this.type_attr_edit = function(attr)
  {
    var form = $('#type_attr_form');

    form.detach();
    $('#attr_table_row_'+attr).after(form);
    this.type_attr_form_init(attr);
    form.slideDown(400);
  };

  // Displays attribute addition form
  this.type_attr_add = function()
  {
    var form = $('#type_attr_form');

    form.detach();
    $('#type_attr_table > tbody').append(form);
    this.type_attr_form_init();
    form.slideDown(400);
  };

  // Saves attribute form, create/update attribute row
  this.type_attr_save = function()
  {
    var attr, row, value = '', data = {},
      form_data = this.serialize_form('#'+this.env.form_id),
      name_select = $('select[name="attr_name"]');

    // read attribute form data
    data.type = form_data.attr_type;
    data.valtype = form_data.attr_value;
    data.optional = form_data.attr_optional;
    data.data = data.valtype != 'normal' ? form_data.attr_data : null;
    data.maxcount = data.type == 'list' || data.type == 'list-autocomplete' ? form_data.attr_maxcount : 0;
    data.values = data.type == 'select' || data.type == 'multiselect' ? form_data.attr_options : [];

    if (name_select.is(':visible')) {
      // new attribute
      attr = name_select.val();
      row = $('<tr><td class="name"></td><td class="type"></td><td class="readonly"></td>'
        +'<td class="optional"></td><td class="value"></td><td class="actions">'
        +'<a class="button delete" title="delete" onclick="kadm.type_attr_delete(\''+attr+'\')" href="#delete"></a>'
        +'<a class="button edit" title="edit" onclick="kadm.type_attr_edit(\''+attr+'\')" href="#edit"></a></td></tr>')
        .attr('id', 'attr_table_row_' + attr).appendTo('#type_attr_table > tbody');
    }
    else {
      // edited attribute
      attr = $('span', name_select.parent()).text().toLowerCase();
      row = $('#attr_table_row_' + attr);
    }

    if (data.valtype != 'normal') {
      value = this.t('attribute.value.' + (data.valtype == 'static' ? 'static' : 'auto')) + ': ' + data.data;
    }

    // Update table row
    $('td.name', row).text(this.env.attributes[attr]);
    $('td.type', row).text(data.type);
    $('td.readonly', row).text(data.valtype == 'auto-readonly' ? this.env.yes_label : this.env.no_label);
    $('td.optional', row).text(data.optional ? this.env.yes_label : this.env.no_label);
    $('td.value', row).text(value);

    // Update env data
    this.env.attr_table[attr] = data;

    this.type_attr_cancel();
  };

  // Hide attribute form
  this.type_attr_cancel = function()
  {
    $('#type_attr_form').hide();
  };

  this.type_attr_form_init = function(attr)
  {
    var name_select = $('select[name="attr_name"]'),
      data = attr ? this.env.attr_table[attr] : {},
      type = data.type || 'text';

    $('select[name="attr_type"]').val(type);
    $('select[name="attr_value"]').val(attr ? data.valtype : 'normal');
    $('input[name="attr_optional"]').attr('checked', attr ? data.optional : false);
    $('input[name="attr_data"]').val(attr ? data.data : '');
    $('input[name="attr_maxcount"]').val(data.maxcount ? data.maxcount : '');
    $('textarea[name="attr_options"]').val(data.values ? data.values.join("\n") : '');
    $('span', name_select.parent()).remove();

    if (attr) {
      name_select.hide().val(attr);
      $('<span></span>').text(this.env.attributes[attr] ? this.env.attributes[attr] : attr)
        .appendTo(name_select.parent());
    }
    else {
      this.type_attr_select_init();
      name_select.show();
    }

    this.form_element_update({name: 'attr_options'});
    this.type_attr_type_change('select[name="attr_type"]');
    this.type_attr_value_change('select[name="attr_value"]');
  };

  // Initialize attribute name selector
  this.type_attr_select_init = function()
  {
    var select = $('select[name="attr_name"]'),
      options = $('option', select);

    options.each(function() {
      if (kadm.env.attr_table[this.value])
        $(this).attr('disabled', true);
    });
    options.not(':disabled').first().attr('selected', true);
  };

  // Update attribute form on attribute name change
  this.type_attr_name_change = function(elem)
  {
    this.type_attr_value_change('select[name="attr_value"]');
  };

  // Update attribute form on value type change
  this.type_attr_value_change = function(elem)
  {
    var type = $(elem).val(),
      optional = $('#attr_form_row_optional'),
      select = $('select[name="attr_name"]').val(),
      attr_name = this.env.attributes[select],
      // only non-static and non-required attributes can be marked as optional
      opt = type != 'static' && $.inArray(attr_name, this.env.attributes_required) == -1;

    $('input[name="attr_data"]')[type != 'normal' ? 'show' : 'hide']();
    $('#attr_form_row_readonly')[type != 'static' ? 'show' : 'hide']();
    optional[opt ? 'show' : 'hide']();

    if (!opt)
      $('input', optional).attr('checked', false);
  };

  // Update attribute form on type change
  this.type_attr_type_change = function(elem)
  {
    var type = $(elem).val();
    $('#attr_form_row_maxcount')[type == 'list' || type == 'list-autocomplete' ? 'show' : 'hide']();
    $('#attr_form_row_options')[type == 'select' || type == 'multiselect' ? 'show' : 'hide']();
  };

  // Update attributes list on object classes change
  this.type_attr_class_change = function(field)
  {
    var data = {attributes: 'attribute', classes: this.type_object_classes(field)};
    this.api_post('form_value.select_options', data, 'type_attr_class_change_response');
    this.type_attr_cancel();
  };

  // Update attributes list on object classes change - API response handler
  this.type_attr_class_change_response = function(response)
  {
    if (!this.api_response(response))
      return;

    var i, lc, list = response.result.attribute.list || [],
      select = $('select[name="attr_name"]');

    this.env.attributes = {};
    this.env.attributes_required = response.result.attribute.required || [];
    select.empty();

    for (i in list) {
      if (i == 'objectClass')
        continue;
      lc = list[i].toLowerCase();
      this.env.attributes[lc] = list[i];
      $('<option>').text(list[i]).val(lc).appendTo(select);
    }
  };

  // Return selected objectclasses array
  this.type_object_classes = function(field)
  {
    var classes = [];
    $('option:selected', $(field)).each(function() {
      classes.push(this.value);
    });
    return classes;
  };

  // Password generation - request
  this.generate_password = function(fieldname)
  {
    this.env.password_field = fieldname;
    this.set_busy(true, 'loading');
    // we can send only 'attributes' here, because password generation doesn't require object type name/id
    this.api_post('form_value.generate', {attributes: [fieldname]}, 'generate_password_response');
  };

  // Password generation - response handler
  this.generate_password_response = function(response)
  {
    if (!this.api_response(response))
      return;

    var f = this.env.password_field, pass = response.result[f];

    $('input[name="' + f + '"]').val(pass);
    $('input[name="' + f + '2"]').val(pass);
  };

  // LDAP URL parser
  this.parse_ldap_url = function(url)
  {
    var result = {},
      url_parser = /^([^:]+):\/\/([^\/]*)\/([^?]*)\?([^?]*)\?([^?]*)\?(.*)$/;
      matches = url_parser.exec(url);

    if (matches && matches[1])
      return {
        scheme: matches[1],
        host: matches[2],
        base: matches[3],
        attrs: matches[4],
        scope: matches[5],
        filter: this.parse_ldap_filter(matches[6])
      };
  };

  // LDAP filter parser
  this.parse_ldap_filter = function(filter)
  {
    var chr, next, elem, pos = 0, join, level = -1, res = [],
      len = filter ? filter.length : 0;

    if (!filter.match(/^\(.*\)$/))
      filter = '(' + filter + ')';

    while (len > pos) {
      chr = filter.charAt(pos);
      if (chr == '&') {
          join = 'AND';
          level++;
      }
      else if (chr == '|') {
          join = 'OR';
          level++;
      }
      else if (chr == '(') {
        next = filter.charAt(pos+1);
        if (next != '&' && next != '|') {
          next = filter.indexOf(')', pos);
          if (next > 0) {
            if (elem = this.parse_ldap_filter_entry(filter.substr(pos + 1, next - pos - 1))) {
              elem.join = join;
              elem.level = level;
              res.push(elem);
            }
            pos = next;
          }
        }
      }
      else if (chr == ')')
        level--;

      pos++;
    }

    return res;
  };

  // LDAP filter-entry parser
  this.parse_ldap_filter_entry = function(entry)
  {
    var type = 'exact', name, value;

    if (entry.match(/^([a-zA-Z0-9_-]+)=(.*)$/)) {
      name = RegExp.$1;
      value = RegExp.$2;

      if (value == '*') {
        value = ''
        type = 'any';
      }
      else if (value.match(/^\*(.+)\*$/)) {
        value = RegExp.$1;
        type = 'both';
      }
      else if (value.match(/^\*(.+)$/)) {
        value = RegExp.$1;
        type = 'suffix';
      }
      else if (value.match(/^(.+)\*$/)) {
        value = RegExp.$1;
        type = 'prefix';
      }

      return {name: name, type: type, value: value};
    }
  };

  // Builds LDAP filter string from the defined structure
  this.build_ldap_filter = function(filter)
  {
    var i, elem, str = '', last = -1, join = {'AND': '&', 'OR': '|'};

    for (i=0; i<filter.length; i++) {
      elem = filter[i];
      if (elem.level > last)
        str += (elem.join && filter.length > 1 ? join[elem.join] : '');
      else if (elem.level < last)
        str += ')';

      str += '(' + elem.name + '=';

      if (elem.type == 'any')
        str += '*';
      else if (elem.type == 'both')
        str += '*' + elem.value + '*';
      else if (elem.type == 'prefix')
        str += elem.value + '*';
      else if (elem.type == 'suffix')
        str += '*' + elem.value;
      else
        str += elem.value;

      str += ')';
      last = elem.level;
    }

    if (filter.length > 1)
      str = '(' + str + ')';

    return str;
  };

  // Builds LDAP URL string from the defined structure
  this.build_ldap_url = function(params)
  {
    var url = '';

    if (!params.filter.length && !params.base)
      return url;

    url += (params.scheme ? params.scheme : 'ldap') + '://';
    url += (params.host ? params.host + (params.port && params.port != 389 ? ':'+params.port : '') : '') + '/';
    url += (params.base ? params.base : '') + '?';
    url += (params.attrs ? params.attrs : '') + '?';
    url += (params.scope ? params.scope : '') + '?';
    if (params.filter)
      url += this.build_ldap_filter(params.filter);

    return url;
  };

};

// Add escape() method to RegExp object
// http://dev.rubyonrails.org/changeset/7271
RegExp.escape = function(str)
{
  return String(str).replace(/([.*+?^=!:${}()|[\]\/\\])/g, '\\$1');
};

// make a string URL safe (and compatible with PHP's rawurlencode())
function urlencode(str)
{
  if (window.encodeURIComponent)
    return encodeURIComponent(str).replace('*', '%2A');

  return escape(str)
    .replace('+', '%2B')
    .replace('*', '%2A')
    .replace('/', '%2F')
    .replace('@', '%40');
};

// Initialize application object (don't change var name!)
var kadm = new kolab_admin();

// general click handler
$(document).click(function() {
  // destroy autocompletion
  kadm.ac_stop();
  $('.popup').hide();
});
