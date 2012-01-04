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
        // @TODO: search request
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

  // @TODO: reseting search
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

  current = current ? current : 0;

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

function form_loaded(data)
{
  if (data != 'search-form')
    init_tabs(data);
}

/**
 * UI Initialization
 */
kadm.add_event_listener('form-loaded', form_loaded);
