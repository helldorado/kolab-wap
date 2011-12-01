<?php
    require_once( dirname(__FILE__) . '/../lib/functions.php');

    if (!valid_login())
        need_login();

    #echo "<pre>"; print_r($_SERVER); echo "</pre>";

    if (array_key_exists('HTTPS', $_SERVER) && !empty($_SERVER['HTTPS'])) {
        $proto = 'https://';
    } else {
        $proto = 'http://';
    }

    define('API_ROOT', $proto . $_SERVER['HTTP_HOST'] . dirname($_SERVER["REQUEST_URI"]) . "/api/");

    #$user_types = json_decode(base64_decode(implode('',file( API_ROOT . 'user_types/list'))));
    $user_types = (array)(json_decode(implode('',file( API_ROOT . 'user_types/list'))));
    #echo "<pre>"; print_r($user_types); echo "</pre>";

    print '<form method="post">';
    print '<table>';
    print '<tr><td>User Type:</td><td><select name="user_type_id" onchange="javascript:update_user_type_form_elements(this.value);">';
    foreach ($user_types as $id => $attrs) {
        $attrs = (array)($attrs);
        print '<option value="' . $id . '">' . $attrs['name'] . '</option>';
    }
    print '</select></td></tr>';
    foreach ($user_types as $id => $attrs) {
        $attrs = (array)($attrs);
        $attrs['attributes'] = (array)($attrs['attributes']);
        foreach ($attrs['attributes']['mandatory'] as $attribute_name => $attribute) {
            if (!is_array($attribute)) {
                print '<tr><td>' . $attribute . '</td><td><input type="text" name="' . $attribute . '" /></td></tr>';
            } else {
                print '<tr><td>' . $attribute_name . '</td><td>' . implode('<br />', $attribute) . '</td></tr>';
            }
        }
    }

    print '<tr><td colspan="2" align="center"><input type="submit" name="submit" value="Whatever is the localized equivalent of \'Add User\'" /></td></tr>';
    print '</table>';
    print '</form>';
?>
