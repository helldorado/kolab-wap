<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab Web Admin Panel                           |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 +--------------------------------------------------------------------------+
*/

class kolab_recipient_policy {

    static function format() {
        $_args = func_get_args();

        $args = Array();

        for ($i = 0; $i < func_num_args(); $i++) {
            #$args[$i] = preg_replace('/\./', '\.', $_args[$i]);
            $args[$i] = $_args[$i];
        }

        return $args;
    }

    static function normalize_userdata($userdata)
    {
        $keymap = Array(
                'sn' => 'surname',
            );

        foreach ($userdata as $key => $value) {
            if (isset($keymap[$key])) {
                $_key = $keymap[$key];
            } else {
                $_key = $key;
            }

            $userdata[$_key] = str_replace(' ', '', $userdata[$key]);
        }

        return $userdata;
    }

    static function primary_mail($userdata)
    {
        $userdata = self::normalize_userdata($userdata);

        $conf = Conf::get_instance();

        if (isset($userdata['domain'])) {
            $primary_mail = $conf->get_raw($userdata['domain'], 'primary_mail');
        } else {
            $primary_mail = $conf->get_raw($_SESSION['user']->get_domain(), 'primary_mail');
            // Also append the domain to the userdata
            $userdata['domain'] = $_SESSION['user']->get_domain();
        }

        preg_match_all('/%\((\w+)\)s/', $primary_mail, $substrings);

        // Update userdata array
        for ($x = 0; $x < count($substrings[0]); $x++) {
            if (array_key_exists($substrings[1][$x], $userdata)) {
                if (!empty($substrings[2][$x])) {
                    if (!empty($substrings[3][$x])) {
                        $primary_mail = preg_replace(
                                '/%\(' . $substrings[1][$x]. '\)s/',
                                substr(
                                        $userdata[$substrings[1][$x]],
                                        $substrings[2][$x],
                                        $substrings[3][$x]
                                    ),
                                $primary_mail
                            );
                    } else {
                        $primary_mail = preg_replace(
                                '/%\(' . $substrings[1][$x]. '\)s/',
                                substr(
                                        $userdata[$substrings[1][$x]],
                                        $substrings[2][$x]
                                    ),
                                $primary_mail
                            );
                    }
                } elseif (!empty($substrings[3][$x])) {
                    $primary_mail = preg_replace(
                            '/%\(' . $substrings[1][$x]. '\)s/',
                            substr(
                                    $userdata[$substrings[1][$x]],
                                    0,
                                    $substrings[3][$x]
                                ),
                            $primary_mail
                        );
                } else {
                    $primary_mail = preg_replace(
                            '/%\(' . $substrings[1][$x]. '\)s/',
                            $userdata[$substrings[1][$x]],
                            $primary_mail
                        );
                }
            } else {
                console("Key " . $substrings[1][$x] . " does not exist in \$userdata");
            }
        }

        return $primary_mail;

    }

    static function secondary_mail($userdata)
    {
        $secondary_mail_addresses = Array();

        $functions = Array(
                '\'%\((\w+)\)s\'\.capitalize\(\)' => 'strtoupper(substr("%(${1})s", 0, 1)) . strtolower(substr("%(${1})s", 1))',
                '\'%\((\w+)\)s\'\.lower\(\)'      => 'strtolower("%(${1})s")',
                '\'%\((\w+)\)s\'\.upper\(\)'      => 'strtoupper("%(${1})s")',
            );

        $userdata = self::normalize_userdata($userdata);

        $conf = Conf::get_instance();

        if (isset($userdata['domain'])) {
            $secondary_mail = $conf->get_raw($userdata['domain'], 'secondary_mail');
        } else {
            $secondary_mail = $conf->get_raw($_SESSION['user']->get_domain(), 'secondary_mail');
            $userdata['domain'] = $_SESSION['user']->get_domain();
        }

        $secondary_mail = preg_replace('/^{\d:\s*/','',$secondary_mail);
        $secondary_mail = preg_replace('/\s*}$/','',$secondary_mail);
        $secondary_mail = preg_replace('/,\d+:\s*/',',',$secondary_mail);
        $secondary_mail = "[" . $secondary_mail . "]";
        $secondary_mail = json_decode($secondary_mail, true);

        $orig_userdata = $userdata;

        foreach ($secondary_mail as $policy_rule) {
            foreach ($policy_rule as $format => $rule) {
                $userdata = $orig_userdata;

                $format = preg_replace('/(\{\d+\})/', '%s', $format);

                preg_match_all('/\'%\((\w+)\)s\'\[(\d+):(\d+)\]/', $rule, $substrings);

                // Update userdata array
                for ($x = 0; $x < count($substrings[0]); $x++) {
                    if (array_key_exists($substrings[1][$x], $userdata)) {
                        $userdata[$substrings[1][$x]] = substr($userdata[$substrings[1][$x]], $substrings[2][$x], $substrings[3][$x]);
                    } else {
                        console("Key " . $substrings[1][$x] . " does not exist in \$userdata");
                    }

                    $rule = preg_replace(
                            '/\'%\(' . $substrings[1][$x] . '\)s\'\[' . $substrings[2][$x] . ':' . $substrings[3][$x] . '\]/',
                            '\'%(' . $substrings[1][$x] . ')s\'',
                            $rule
                        );

                }

                foreach ($functions as $match => $replace) {
                    if (preg_match('/' . $match . '/', $rule, $strings)) {

                        if (array_key_exists($strings[1], $userdata)) {
                            $rule = preg_replace('/' . $match . '/', $replace, $rule);
                        }

                    }
                }

                $expanded = $conf->expand($rule, $userdata);

                eval("\$result = self::" . $expanded . ";");

                eval("\$result = sprintf('" . $format . "', '" . implode("', '", array_values($result)) . "');");

                $secondary_mail_addresses[] = $result;

            }

        }

        return $secondary_mail_addresses;

    }
}
?>
