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

/**
 * Search form events
 */
function search_init()
{
  $('#searchinput').addClass('inactive')
    .blur(function() {
      if (this.value == '')
        $(this).val(kadm.t('search')).addClass('inactive');
    })
    .keypress(function(e) {
      if (this.value && e.which == 13) { // ENTER key
        var props = kadm.serialize_form('#search-form');
        props.search = this.value;

        kadm.command('user.list', props);
      }
    })
    .focus(function() {
      if (this.value == kadm.t('search'))
        $(this).val('').removeClass('inactive');
    });
}

function search_reset()
{
  var input = $('#searchinput');

  input.val(kadm.t('search')).addClass('inactive');

  kadm.command('user.list', {search: ''});
}

function search_details()
{
  var div = $('div.searchdetails', $('#search'));

  if (!div.is(':visible'))
    div.slideDown(200);
  else
    div.slideUp(200);
}

/**
 * Fieldsets-to-tabs converter
 * Warning: don't place "caller" <script> inside page element (id)
 */
function init_tabs(id, current)
{
  var content = $('#'+id),
    fs = content.children('fieldset');

  if (!fs.length)
    return;

  // find active fieldset
  if (!current) {
    current = 0;
    fs.each(function(idx) { if ($(this).hasClass('active')) { current = idx; return false; } });
  }

  // first hide not selected tabs
  fs.each(function(idx) { if (idx != current) $(this).hide(); });

  // create tabs container
  var tabs = $('<div>').addClass('tabsbar').prependTo(content);

  // convert fildsets into tabs
  fs.each(function(idx) {
    var tab, a, elm = $(this), legend = elm.children('legend');

    // create a tab
    a   = $('<a>').text(legend.text()).attr('href', '#');
    tab = $('<span>').attr({'id': 'tab'+idx, 'class': 'tablink'})
        .click(function() { show_tab(id, idx); return false; })

    // remove legend
    legend.remove();
    // style fieldset
    elm.addClass('tabbed');
    // style selected tab
    if (idx == current)
      tab.addClass('tablink-selected');

    // add the tab to container
    tab.append(a).appendTo(tabs);
  });
}

function show_tab(id, index)
{
  var fs = $('#'+id).children('fieldset');

  fs.each(function(idx) {
    // Show/hide fieldset (tab content)
    $(this)[index == idx ? 'show' : 'hide']();
    // Select/unselect tab
    $('#tab'+idx).toggleClass('tablink-selected', idx == index);
  });
}

/**
 * HTML form events handlers
 */

// Form "onload" handler
function form_load(id)
{
  if (id != 'search-form')
    init_tabs(id);

  form_init(id);
}

// Form "onserialize" handler
function form_serialize(data)
{
  var form = $(data.id);

  // replace some textarea fields with pretty/smart input lists
  $('textarea[data-type="list"]', form).not('disabled')
    .each(function() {
    var i, value = [], re = RegExp('^' + this.name + '\[[0-9]+\]$');

    for (i in data.json) {
      if (i.match(re)) {
        if (i.value)
          value.push(i.value);
        delete data.json[i];
      }
    }

    data.json[this.name] = value.join("\n");
  });

  return data;
}

// Form initialization
function form_init(id)
{
  var form = $('#'+id), separator = /[,\s\r\n]+/;

  // replace some textarea fields with pretty/smart input lists
  $('textarea[data-type="list"]', form)
    .each(function() {
    var i, len, elem, e = $(this),
      list = this.value.split(separator),
      area = $('<span class="listarea">'),
      disabled = e.attr('disabled') || e.attr('readonly');

    e.hide();

    for (i=0, len=list.length; i<len; i++) {
      elem = form_list_element(form, {
        name: this.name+'['+i+']',
        value: list[i],
        disabled: disabled
      });
      elem.appendTo(area);
    }

    if (disabled)
      area.addClass('readonly');

    area.appendTo(this.parentNode);
  });
}

// Creates smart list element
function form_list_element(form, data)
{
  var elem = $('<span class="listelement"><span class="actions">'
    + '<span title="" class="add"></span><span title="" class="reset"></span>'
    + '</span><input></span>');

  $('input', elem).attr({name: data.name, value: data.value, disabled: data.disabled});

  if (data.disabled)
    return elem;

  // attach element creation event
  $('span[class="add"]', elem).click(function() {
    var dt = (new Date()).getTime(),
      span = $(this.parentNode.parentNode),
      name = data.name.replace(/\[[0-9]+\]$/, ''),
      elem = form_list_element(form, {name: name+'['+dt+']'});

    span.after(elem);
    $('input', elem).focus();
  });

  // attach element deletion event
  $('span[class="reset"]', elem).click(function() {
    var l, span = $(this.parentNode.parentNode),
      name = data.name.replace(/\[[0-9]+\]$/, ''),
      l = $('input[name^="' + name + '"]', form);

    if (l.length > 1)
      span.remove();
    else
      $('input', span).val('').focus();
  });

  return elem;
}

/**
 * UI Initialization
 */
kadm.add_event_listener('form-load', form_load);
kadm.add_event_listener('form-serialize', form_serialize);
