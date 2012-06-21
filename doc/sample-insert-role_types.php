#!/usr/bin/php
<?php

    if (isset($_SERVER["REQUEST_METHOD"]) && !empty($SERVER["REQUEST_METHOD"])) {
        die("Not intended for execution through the webserver, sorry!");
    }

    require_once("lib/functions.php");

    $db   = SQL::get_instance();

    $result = $db->query("TRUNCATE `role_types`");

    $attributes = Array(
            "auto_form_fields" => Array(
                ),
            "fields" => Array(
                    "objectclass" => Array(
                            "top",
                            "ldapsubentry",
                            "nsroledefinition",
                            "nssimpleroledefinition",
                            "nsmanagedroledefinition"
                        ),
                ),
            "form_fields" => Array(
                    "cn" => Array(),
                    "description" => Array(),
                ),
        );

    $result = $db->query("INSERT INTO `role_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('simple_managed','Standard Role', 'A standard role definition'," .
                "'" . json_encode($attributes) . "')");

?>
