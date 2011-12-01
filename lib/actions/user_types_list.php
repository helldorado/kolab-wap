<?php
    $result = query("SELECT * FROM user_types");

    $user_types = Array();

    while ($row = mysql_fetch_assoc($result)) {
        $user_types[$row['id']] = Array();

        foreach ($row as $key => $value) {
            if ($key != "id") {
                if ($key == "attributes") {
                    $user_types[$row['id']][$key] = json_decode(base64_decode($value));
                } else {
                    $user_types[$row['id']][$key] = $value;
                }
            }
        }
    }

    #print base64_encode(json_encode($user_types));
    print json_encode($user_types);

?>
