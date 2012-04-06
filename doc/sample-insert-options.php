#!/usr/bin/php
<?php
    if (isset($_SERVER["REQUEST_METHOD"]) || !empty($_SERVER["REQUEST_METHOD"])) {
        die("Not for execution through webserver");
    }

    require_once('lib/functions.php');

    $db = SQL::get_instance();

    $db->query("TRUNCATE `options`");

    exec("locale -a | cut -d'.' -f 1 | cut -d'@' -f1 | grep -E \"^([a-zA-Z_]*)\$\" | sort -u", $output);

//    var_dump($output);

    $json = json_encode($output);

//    var_dump($json);

    unset($output);

    $result = $db->query('INSERT INTO `options` (`attribute`, `option_values`) VALUES ('.
            '\'preferredlanguage\', \'' . $json . '\')');

    var_dump($result);
/*
    exec('cat ./iso3166-countrycodes.txt | sed -r -e \'s/(.*)\s+([A-Z]{2})\s+([A-Z]{3})\s+([0-9]{3})\s*$/\2/g\' -e \'/^[A-Z]{2}$/!d\'', $output);

    var_dump($output);

    sort($output);

    $json = json_encode($output);

//    var_dump($json);

    $result = $db->query('INSERT INTO `options` (`attribute`, `option_values`) VALUES ('.
            '\'c\', \'' . $json . '\')');

    var_dump($result);
*/
?>
