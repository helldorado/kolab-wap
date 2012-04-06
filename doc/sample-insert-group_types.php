#!/usr/bin/php
<?php

    if (isset($_SERVER["REQUEST_METHOD"]) && !empty($SERVER["REQUEST_METHOD"])) {
        die("Not intended for execution through the webserver, sorry!");
    }

    require_once("lib/functions.php");

    $db   = SQL::get_instance();

    $result = $db->query("TRUNCATE `group_types`");

    $attributes = Array(
            "auto_form_fields" => Array(
                    "mail" => Array(
                            "data" => Array(
                                    "cn",
                                ),
                        ),
                ),
            "fields" => Array(
                    "objectclass" => Array(
                            "top",
                            "groupofuniquenames",
                            "kolabgroupofuniquenames",
                        ),
                ),
            "form_fields" => Array(
                    "cn" => Array(),
                    "uniquemember" => Array(
                            "type" => "list",
                            "autocomplete" => true,
                            "optional" => true,
                        ),
                ),
        );

    $result = $db->query("INSERT INTO `group_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('kolab','Kolab Distribution Group', 'A Kolab Distribution Group (with mail address)'," .
                "'" . json_encode($attributes) . "')");

    $attributes = Array(
            "auto_form_fields" => Array(
                    "gidnumber" => Array(),
                ),
            "fields" => Array(
                    "objectclass" => Array(
                            "top",
                            "groupofuniquenames",
                            "posixgroup",
                        ),
                ),
            "form_fields" => Array(
                    "cn" => Array(),
                    "uniquemember" => Array(
                            "type" => "list",
                            "autocomplete" => true,
                            "optional" => true,
                        ),
                ),
        );

    $result = $db->query("INSERT INTO `group_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('posix','(Pure) POSIX Group', 'A pure UNIX POSIX Group'," .
                "'" . json_encode($attributes) . "')");

    $attributes = Array(
            "auto_form_fields" => Array(
                    "gidnumber" => Array(),
                    "mail" => Array(
                            "data" => Array(
                                    "cn",
                                ),
                        ),
                ),
            "fields" => Array(
                    "objectclass" => Array(
                            "top",
                            "groupofuniquenames",
                            "kolabgroupofuniquenames",
                            "posixgroup",
                        ),
                ),
            "form_fields" => Array(
                    "cn" => Array(),
                    "mail" => Array(
                            "optional" => true
                        ),
                    "uniquemember" => Array(
                            "type" => "list",
                            "autocomplete" => true,
                            "optional" => true,
                        ),
                ),
        );

    $result = $db->query("INSERT INTO `group_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('posix_mail','Mail-enabled POSIX Group', 'A Kolab and also UNIX POSIX Group'," .
                "'" . json_encode($attributes) . "')");

?>
