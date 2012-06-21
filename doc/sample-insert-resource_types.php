#!/usr/bin/php
<?php

    if (isset($_SERVER["REQUEST_METHOD"]) && !empty($SERVER["REQUEST_METHOD"])) {
        die("Not intended for execution through the webserver, sorry!");
    }

    require_once("lib/functions.php");

    $db   = SQL::get_instance();

    $result = $db->query("TRUNCATE `resource_types`");

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

    $result = $db->query("INSERT INTO `resource_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('collection','Resource Collection', 'A collection or pool of resources'," .
                "'" . json_encode($attributes) . "')");

    $attributes = Array(
            "auto_form_fields" => Array(
                    "cn" => Array(
                            "data" => Array(
                                    "cn",
                                ),
                        ),
                    "kolabtargetfolder" => Array(
                            "data" => Array(
                                    "cn",
                                ),
                        ),
                    "mail" => Array(
                            "data" => Array(
                                    "cn",
                                ),
                        ),
                ),
            "fields" => Array(
                    "objectclass" => Array(
                            "top",
                            "kolabsharedfolder",
                            "mailrecipient",
                        ),
                    "kolabfoldertype" => Array(
                            "event",
                        ),
                ),
            "form_fields" => Array(
                    "cn" => Array(),
                ),
        );

    $result = $db->query("INSERT INTO `resource_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('car','Car', 'A car'," .
                "'" . json_encode($attributes) . "')");

    $result = $db->query("INSERT INTO `resource_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('confroom','Conference Room', 'A conference room'," .
                "'" . json_encode($attributes) . "')");

    $result = $db->query("INSERT INTO `resource_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('beamer','Beamer', 'A portable beamer'," .
                "'" . json_encode($attributes) . "')");

    $result = $db->query("INSERT INTO `resource_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('footballtickets','Football Season Tickets', 'Season tickets to the game (pretty good seats too!)'," .
                "'" . json_encode($attributes) . "')");

?>
