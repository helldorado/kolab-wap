<?php
    $type = 'kolab';

    $user_type = mysql_fetch_assoc(query("SELECT attributes FROM user_types WHERE id = '" . $_GET['user_type_id'] ."'"));

    $user_type_attributes = json_decode(base64_decode($user_type['attributes']));

#    echo "<pre>";
#    print_r($_POST);
#    echo "</pre>";

    $user = Array();

    #chars = ['Ä', 'Ü', 'Ö', 'ä', 'ü', 'ö', 'ß']
    #simple = ['Ae', 'Ue', 'Oe', 'ae', 'ue', 'oe', 'ss']

    $needles = Array(
            ' ',
            'ö',
            'ü',
        );

    $replaces = Array(
            '',
            'oe',
            'ue',
        );

    foreach ($user_type_attributes as $level => $attributes) {
        echo "<li>$level:<pre>";
        print_r($attributes);
        echo "</pre>";
        if ($level == "mandatory") {
            foreach ($attributes as $key => $value) {
                if (isset($_POST[strtolower($key)])) {
#                    print $level . " key " . $key . " found\n";
                    $user[$key] = $_POST[strtolower($key)];
                } elseif (isset($_POST[strtolower($value)])) {
#                    print $level . " value for " . $value . " found\n";
                    $user[$value] = $_POST[strtolower($value)];
                } else {
                    $user[$key] = $value;
                }
            }
        }
        if ($level == "auto_generated") {
            // Only contains a list of attribute names...
            foreach ($attributes as $num => $attribute) {
                if ($attribute == "alias") {
                    // Apply secondary mail routine
                }

                if ($attribute == "uid") {
                    // Apply normalization
                    $user['uid'] = strtolower(str_replace($needles, $replaces, $user['sn']));
                }

                if ($attribute == "cn") {
                    $user['cn'] = $user['givenName'] . " " . $user['sn'];
                }

                if ($attribute == "displayName") {
                    $user['displayName'] = $user['sn'] . ", " . $user['givenName'];
                }
            }
        }
    }

    print_r($user);

?>
