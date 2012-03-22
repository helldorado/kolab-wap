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
function search_init(task)
{
  kadm.env.search_task = task;

  $('#searchinput').addClass('inactive')
    .blur(function() {
      if (this.value == '')
        $(this).val(kadm.t('search')).addClass('inactive');
    })
    .keypress(function(e) {
      if (this.value && e.which == 13) { // ENTER key
        var props = kadm.serialize_form('#search-form');
        props.search = this.value;

        kadm.command(kadm.env.search_task + '.list', props);
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

  kadm.command(kadm.env.search_task + '.list', {search: ''});
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

// Form "onload" handler
function form_load(id)
{
  if (id != 'search-form')
    init_tabs(id);
}

/**
 * UI Initialization
 */
kadm.add_event_listener('form-load', form_load);
