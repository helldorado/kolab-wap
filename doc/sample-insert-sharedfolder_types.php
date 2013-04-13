#!/usr/bin/php
<?php

    if (isset($_SERVER["REQUEST_METHOD"]) && !empty($SERVER["REQUEST_METHOD"])) {
        die("Not intended for execution through the webserver, sorry!");
    }

    require_once("lib/functions.php");

    $db   = SQL::get_instance();

    $result = $db->query("TRUNCATE `sharedfolder_types`");

    $attributes = Array(
            "auto_form_fields" => Array(
                ),
            "fields" => Array(
                    "kolabfoldertype" => Array(
                            "contact",
                        ),
                    "objectclass" => Array(
                            "top",
                            "kolabsharedfolder",
                        ),
                ),
            "form_fields" => Array(
/* TODO: Pending implementation of a folder acl list form widget - see #1752
                    "acl" => Array(
                            "type" => "folder_acl_list",
                            "optional" => true,
                        ),
*/
                    "cn" => Array(),
                ),
        );

    $result = $db->query("INSERT INTO `sharedfolder_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('addressbook','Shared Address Book', 'A shared address book'," .
                "'" . json_encode($attributes) . "')");

    $attributes["fields"]["kolabfoldertype"] = Array('event');
    $result = $db->query("INSERT INTO `sharedfolder_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('calendar','Shared Calendar', 'A shared calendar'," .
                "'" . json_encode($attributes) . "')");

    $attributes["fields"]["kolabfoldertype"] = Array('journal');
    $result = $db->query("INSERT INTO `sharedfolder_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('journal','Shared Journal', 'A shared journal'," .
                "'" . json_encode($attributes) . "')");

    $attributes["fields"]["kolabfoldertype"] = Array('task');
    $result = $db->query("INSERT INTO `sharedfolder_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('task','Shared Tasks', 'A shared tasks folder'," .
                "'" . json_encode($attributes) . "')");

    $attributes["fields"]["kolabfoldertype"] = Array('mail');
    $attributes["form_fields"]["alias"] = Array(
            "type" => "list",
            "optional" => true,
        );

    $attributes["form_fields"]["kolabdelegate"] = Array(
            "type" => "list",
            "autocomplete" => true,
            "optional" => true,
        );

    $attributes["form_fields"]["kolaballowsmtprecipient"] = Array(
            "type" => "list",
            "optional" => true,
        );

    $attributes["form_fields"]["kolaballowsmtpsender"] = Array(
            "type" => "list",
            "optional" => true,
        );

    $attributes["form_fields"]["kolabtargetfolder"] = Array();
    $attributes["form_fields"]["mail"] = Array();
    $attributes["fields"]["objectclass"][] = "mailrecipient";

    $result = $db->query("INSERT INTO `sharedfolder_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('mail','Shared Mail Folder', 'A shared mail folder'," .
                "'" . json_encode($attributes) . "')");

?>
