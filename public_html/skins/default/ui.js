
function search_reset()
{
  var input = $('#searchinput');

  input.val(kadm.t('search'));
}

function search_details()
{
  var div = $('div.searchdetails', $('#search'));

  if (!div.is(':visible'))
    div.slideDown(200);
  else
    div.slideUp(200);
}

function search_click()
{

}

function search_key()
{

}
