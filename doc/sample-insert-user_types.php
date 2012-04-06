#!/usr/bin/php
<?php

    if (isset($_SERVER["REQUEST_METHOD"]) && !empty($SERVER["REQUEST_METHOD"])) {
        die("Not intended for execution through the webserver, sorry!");
    }

    require_once("lib/functions.php");

    $db   = SQL::get_instance();

    $result = $db->query("TRUNCATE `user_types`");

    $attributes = Array(
            "auto_form_fields" => Array(
                    "cn" => Array(
                            "data" => Array(
                                    "givenname",
                                    "sn",
                                ),
                        ),
                    "displayname" => Array(
                            "data" => Array(
                                    "givenname",
                                    "sn",
                                ),
                        ),
                    "mail" => Array(
                            "data" => Array(
                                    "givenname",
                                    "preferredlanguage",
                                    "sn",
                                ),
                        ),
                    "mailalternateaddress" => Array(
                            "data" => Array(
                                    "givenname",
                                    "preferredlanguage",
                                    "sn",
                                ),
                            "optional" => true,
                        ),
                    "mailhost" => Array(
                            "optional" => true,
                        ),
                    "uid" => Array(
                            "data" => Array(
                                    "givenname",
                                    "preferredlanguage",
                                    "sn",
                                ),
                        ),
                    "userpassword" => Array(
                            "optional" => true,
                        ),
                ),
            "form_fields" => Array(
/*
                    "c" => Array(
                            "type" => "select",
                            "optional" => true,
                        ),
*/
                    "givenname" => Array(),
                    "initials" => Array(
                            "optional" => true,
                        ),
                    "kolabdelegate" => Array(
                            "type" => "list",
                            "autocomplete" => true,
                            "optional" => true,
                        ),
                    "kolabinvitationpolicy" => Array(
                            "type" => "select",
                            "values" => Array(
                                    "",
                                    "ACT_MANUAL",
                                    "ACT_REJECT",
                                ),
                            "optional" => true,
                        ),
                    "kolaballowsmtprecipient" => Array(
                            "type" => "list",
                            "optional" => true,
                        ),
                    "kolaballowsmtpsender" => Array(
                            "type" => "list",
                            "optional" => true,
                        ),
                    "l" => Array(
                            "optional" => true,
                        ),
                    "mailalternateaddress" => Array(
                            "type" => "list",
                            "optional" => true,
                        ),
                    "mailquota" => Array(
                            "optional" => true,
                        ),
                    "mobile" => Array(
                            "optional" => true,
                        ),
                    "nsroledn" => Array(
                            "type" => "list",
                            "autocomplete" => true,
                            "optional" => true
                        ),
                    "o" => Array(
                            "optional" => true,
                        ),
                    "ou" => Array(
                            "type" => "select",
                        ),
                    "pager" => Array(
                            "optional" => true,
                        ),
                    "postalcode" => Array(
                            "optional" => true,
                        ),
                    "preferredlanguage" => Array(
                            "type" => "select",
                        ),
                    "sn" => Array(),
                    "street" => Array(
                            "optional" => true,
                        ),
                    "telephonenumber" => Array(
                            "optional" => true,
                        ),
                    "title" => Array(
                            "optional" => true,
                        ),
                    "userpassword" => Array(
                            "optional" => true,
                        ),
                ),
            "fields" => Array(
                    "objectclass" => Array(
                            "top",
                            "inetorgperson",
                            "kolabinetorgperson",
                            "mailrecipient",
                            "organizationalperson",
                            "person",
                        ),
                ),
        );

    $result = $db->query("INSERT INTO `user_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('kolab','Kolab User', 'A Kolab User'," .
                "'" . json_encode($attributes) . "')");

    $attributes = Array(
            "auto_form_fields" => Array(
                    "cn" => Array(
                            "data" => Array(
                                    "givenname",
                                    "sn",
                                ),
                        ),
                    "displayname" => Array(
                            "data" => Array(
                                    "givenname",
                                    "sn",
                                ),
                        ),
                    "gidnumber" => Array(),
                    "homedirectory" => Array(
                            "data" => Array(
                                    "givenname",
                                    "sn",
                                ),
                        ),
                    "uid" => Array(
                            "data" => Array(
                                    "givenname",
                                    "sn",
                                ),
                        ),
                    "uidnumber" => Array(),
                    "userpassword" => Array(
                            "optional" => true,
                        ),
                ),
            "form_fields" => Array(
                    "givenname" => Array(),
                    "initials" => Array(
                            "optional" => true,
                        ),
                    "preferredlanguage" => Array(
                            "type" => "select",
                            "values" => Array(
                                    "en_US",
                                    "de_DE",
                                    "de_CH",
                                    "en_GB",
                                    "fi_FI",
                                    "fr_FR",
                                    "hu_HU",
                                ),
                        ),
                    "loginshell" => Array(
                            "type" => "select",
                            "values" => Array(
                                    "/bin/bash",
                                    "/usr/bin/git-shell",
                                    "/sbin/nologin",
                                ),
                        ),
                    "ou" => Array(
                            "type" => "select",
                        ),
                    "sn" => Array(),
                    "title" => Array(
                            "optional" => true,
                        ),
                    "userpassword" => Array(
                            "optional" => true,
                        ),
                ),
            "fields" => Array(
                    "objectclass" => Array(
                            "top",
                            "inetorgperson",
                            "organizationalperson",
                            "person",
                            "posixaccount",
                        ),
                ),
        );

    $result = $db->query("INSERT INTO `user_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('posix','POSIX User', 'A POSIX user (with a home directory and shell access)'," .
                "'" . json_encode($attributes) . "')");

    $attributes = Array(
            "auto_form_fields" => Array(
                    "cn" => Array(
                            "data" => Array(
                                    "givenname",
                                    "preferredlanguage",
                                    "sn",
                                ),
                        ),
                    "displayname" => Array(
                            "data" => Array(
                                    "givenname",
                                    "preferredlanguage",
                                    "sn",
                                ),
                        ),
                    "gidnumber" => Array(),
                    "homedirectory" => Array(
                            "data" => Array(
                                    "givenname",
                                    "preferredlanguage",
                                    "sn",
                                ),
                        ),
                    "mail" => Array(
                            "data" => Array(
                                    "givenname",
                                    "preferredlanguage",
                                    "sn",
                                ),
                        ),
                    "mailalternateaddress" => Array(
                            "data" => Array(
                                    "givenname",
                                    "preferredlanguage",
                                    "sn",
                                ),
                            "optional" => true,
                        ),
                    "mailhost" => Array(
                            "optional" => true,
                        ),
                    "uid" => Array(
                            "data" => Array(
                                    "givenname",
                                    "preferredlanguage",
                                    "sn",
                                ),
                        ),
                    "uidnumber" => Array(),
                    "userpassword" => Array(
                            "optional" => true,
                        ),
                ),
            "form_fields" => Array(
/*
                    "c" => Array(
                            "type" => "select",
                            "optional" => true,
                        ),
*/
                    "givenname" => Array(),
                    "initials" => Array(
                            "optional" => true,
                        ),
                    "kolabdelegate" => Array(
                            "type" => "list",
                            "autocomplete" => true,
                            "optional" => true,
                        ),
                    "kolabinvitationpolicy" => Array(
                            "type" => "select",
                            "values" => Array(
                                    "",
                                    "ACT_MANUAL",
                                    "ACT_REJECT",
                                ),
                            "optional" => true,
                        ),
                    "kolaballowsmtprecipient" => Array(
                            "type" => "list",
                            "optional" => true,
                        ),
                    "kolaballowsmtpsender" => Array(
                            "type" => "list",
                            "optional" => true,
                        ),
                    "l" => Array(
                            "optional" => true,
                        ),
                    "loginshell" => Array(
                            "type" => "select",
                            "values" => Array(
                                    "/bin/bash",
                                    "/usr/bin/git-shell",
                                    "/sbin/nologin",
                                ),
                        ),
                    "mailalternateaddress" => Array(
                            "type" => "list",
                            "optional" => true,
                        ),
                    "mailquota" => Array(
                            "optional" => true,
                        ),
                    "mobile" => Array(
                            "optional" => true,
                        ),
                    "nsroledn" => Array(
                            "type" => "list",
                            "autocomplete" => true,
                            "optional" => true,
                        ),
                    "o" => Array(
                            "optional" => true,
                        ),
                    "ou" => Array(
                            "type" => "select",
                        ),
                    "pager" => Array(
                            "optional" => true,
                        ),
                    "postalcode" => Array(
                            "optional" => true,
                        ),
                    "preferredlanguage" => Array(
                            "type" => "select",
                        ),
                    "sn" => Array(),
                    "street" => Array(
                            "optional" => true,
                        ),
                    "telephonenumber" => Array(
                            "optional" => true,
                        ),
                    "title" => Array(
                            "optional" => true,
                        ),
                    "userpassword" => Array(
                            "optional" => true,
                        ),
                ),
            "fields" => Array(
                    "objectclass" => Array(
                            "top",
                            "inetorgperson",
                            "kolabinetorgperson",
                            "mailrecipient",
                            "organizationalperson",
                            "person",
                            "posixaccount",
                        ),
                ),
        );

    $result = $db->query("INSERT INTO `user_types` (`key`, `name`, `description`, `attributes`) " .
                "VALUES ('kolab_posix','Mail-enabled POSIX User', 'A mail-enabled POSIX User'," .
                "'" . json_encode($attributes) . "')");

?>
