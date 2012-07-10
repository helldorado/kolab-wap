#!/usr/bin/php
<?php

    if (isset($_SERVER["REQUEST_METHOD"]) && !empty($SERVER["REQUEST_METHOD"])) {
        die("Not intended for execution through the webserver, sorry!");
    }

    require_once("lib/functions.php");

    $db   = SQL::get_instance();

    $result = $db->query("TRUNCATE `user_types`");

    $attributes = Array(

            /*
             * The form fields for which the values can be
             * generated automatically, using the existing
             * values of form_fields
             */
            "auto_form_fields" => Array(
                    /*
                     * The 'cn' attribute is required for
                     * the LDAP objectclasses we use, but
                     * can be composed from a 'givenname'
                     * and 'sn' attribute form_field (of
                     * which 'sn' is also a required
                     * attribute.
                     */
                    "cn" => Array(
                            "data" => Array(
                                    "givenname",
                                    "sn",
                                ),
                        ),
                    /*
                     * The 'mail' attribute is supposed to 
                     * contain the email address this user
                     * will use for this environment, and
                     * is (supposed?) to match the 'uid'
                     * for the user account.
                     *
                     * Disable this auto_form_field if
                     * the API is not capable of making
                     * a 'uid' become a 'uid'@'domain',
                     * where 'domain' is not a valid
                     * LDAP attribute for a user entry.
                     */
                    "mail" => Array(
                            "data" => Array(
                                    "uid",
                                ),
                        ),
                ),
            "form_fields" => Array(
                    "givenname" => Array(),
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
                    /*
                     *
                     * The 'mailalternateaddress' is supposed
                     * to contain the original email address
                     * for the user.
                     */
                    "mailalternateaddress" => Array(
                        ),
                    "sn" => Array(),
                    "uid" => Array(),
                    "userpassword" => Array(
                            "optional" => true,
                        ),
                ),
            "fields" => Array(
                    "mailquota" => "131072",
                    "nsroledn" => "cn=personal-user,dc=notifytest,dc=tld",
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
                "VALUES ('personal','Personal', 'A user with a personal hosted plan'," .
                "'" . json_encode($attributes) . "')");

    $attributes = Array(
            /*
             * The form fields for which the values can be
             * generated automatically, using the existing
             * values of form_fields
             */
            "auto_form_fields" => Array(
                    /*
                     * The 'cn' attribute is required for
                     * the LDAP objectclasses we use, but
                     * can be composed from a 'givenname'
                     * and 'sn' attribute form_field (of
                     * which 'sn' is also a required
                     * attribute.
                     */
                    "cn" => Array(
                            "data" => Array(
                                    "givenname",
                                    "sn",
                                ),
                        ),
                    /*
                     * The 'mail' attribute is supposed to 
                     * contain the email address this user
                     * will use for this environment, and
                     * is (supposed?) to match the 'uid'
                     * for the user account.
                     *
                     * Disable this auto_form_field if
                     * the API is not capable of making
                     * a 'uid' become a 'uid'@'domain',
                     * where 'domain' is not a valid
                     * LDAP attribute for a user entry.
                     */
                    "mail" => Array(
                            "data" => Array(
                                    "uid",
                                ),
                        ),
                ),
            "form_fields" => Array(
                    "alias" => Array(
                            "type" => "list",
                            "optional" => true,
                            "max_count" => 2,
                        ),
                    "givenname" => Array(),
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
                    /*
                     * The 'mailalternateaddress' is supposed
                     * to contain the original email address
                     * for the user.
                     */
                    "mailalternateaddress" => Array(
                            "optional" => true,
                        ),
                    "sn" => Array(),
                    "uid" => Array(),
                    "userpassword" => Array(
                            "optional" => true,
                        ),
                ),
            "fields" => Array(
                    "mailquota" => "1048576",
                    "nsroledn" => "cn=professional-user,dc=notifytest,dc=tld",
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
                "VALUES ('professional','Professional', 'A user with a professional hosted plan'," .
                "'" . json_encode($attributes) . "')");

?>
