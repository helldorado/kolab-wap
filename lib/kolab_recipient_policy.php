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

        $args = array();

        for ($i = 0; $i < func_num_args(); $i++) {
            //$args[$i] = preg_replace('/\./', '\.', $_args[$i]);
            $args[$i] = $_args[$i];
        }

        return $args;
    }

    static function normalize_groupdata($groupdata)
    {
        //console("IN", $groupdata);
        foreach ($groupdata as $key => $value) {
            if (isset($groupdata['preferredlanguage'])) {
                setlocale(LC_ALL, $groupdata['preferredlanguage']);
            } else {
                $conf = Conf::get_instance();
                $locale = $conf->get('default_locale');
                if (!empty($locale)) {
                    setlocale(LC_ALL, $locale);
                }
            }

            if (!is_array($groupdata[$key])) {
                $orig_value = $groupdata[$key];

                $groupdata[$key] = iconv('UTF-8', 'ASCII//TRANSLIT', $groupdata[$key]);
                $groupdata[$key] = preg_replace('/[^a-z0-9-_]/i', '', $groupdata[$key]);
            }
        }

        //console("OUT", $groupdata);
        return $groupdata;
    }

    static function normalize_userdata($userdata)
    {
        $keymap = array(
                'sn' => 'surname',
            );

        foreach ($userdata as $key => $value) {
            if (isset($keymap[$key])) {
                $_key = $keymap[$key];
            } else {
                $_key = $key;
            }

            if (isset($userdata['preferredlanguage'])) {
                setlocale(LC_ALL, $userdata['preferredlanguage']);
            } else {
                $conf = Conf::get_instance();
                $locale = $conf->get('default_locale');
                if (!empty($locale)) {
                    setlocale(LC_ALL, $locale);
                }
            }

            if (!is_array($userdata[$_key])) {
                $orig_value = $userdata[$key];

                $userdata[$_key] = iconv('UTF-8', 'ASCII//TRANSLIT', $userdata[$key]);
                $userdata[$_key] = preg_replace('/[^a-z0-9-_]/i', '', $userdata[$_key]);
            }
        }

        return $userdata;
    }

    static function primary_mail_group($groupdata)
    {
        // Expect only a cn@domain.tld, really
        $groupdata = self::normalize_groupdata($groupdata);
        $email     = '';

        if (!empty($groupdata['cn'])) {
            $email = $groupdata['cn'] . '@' . $_SESSION['user']->get_domain();
        }

        return self::parse_email($email);
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
                //console("Key " . $substrings[1][$x] . " does not exist in \$userdata");
            }
        }

        return self::parse_email($primary_mail);
    }

    static function secondary_mail($userdata)
    {
        $secondary_mail_addresses = array();

        $functions = array(
                '\'%\((\w+)\)s\'\.capitalize\(\)' => 'strtoupper(substr("%(${1})s", 0, 1)) . strtolower(substr("%(${1})s", 1))',
                '\'%\((\w+)\)s\'\.lower\(\)'      => 'strtolower("%(${1})s")',
                '\'%\((\w+)\)s\'\.upper\(\)'      => 'strtoupper("%(${1})s")',
            );

        $userdata = self::normalize_userdata($userdata);
        if (!array_key_exists('mail', $userdata)) {
            $userdata['mail'] = self::primary_mail($userdata);
        }

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
                        //console("Key " . $substrings[1][$x] . " does not exist in \$userdata");
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

                if ($result = self::parse_email($result)) {
                    // See if the equivalent is already in the 'mail' attribute value(s)
                    if (!empty($userdata['mail'])) {
                        if (strtolower($userdata['mail']) == strtolower($result)) {
                            continue;
                        }
                    }

                    $secondary_mail_addresses[] = $result;
                }
            }

        }

        return $secondary_mail_addresses;
    }

    /**
     * Make sure email address is valid, if not return empty string
     */
    static private function parse_email($email)
    {
        $email = strtolower($email);

        $email_parts = explode('@', $email);
        $email_parts = array_filter($email_parts);

        // do some simple checks here
        if (count($email_parts) < 2) {
            return '';
        }

        // trim dots, it's most likely case
        $email_parts[0] = trim($email_parts[0], '.');

        // from PEAR::Validate
        $regexp = '&^(?:
            ("\s*(?:[^"\f\n\r\t\v\b\s]+\s*)+")|                             #1 quoted name
            ([-\w!\#\$%\&\'*+~/^`|{}=]+(?:\.[-\w!\#\$%\&\'*+~/^`|{}=]+)*))  #2 OR dot-atom (RFC5322)
            $&xi';

        if (!preg_match($regexp, $email_parts[0])) {
            return '';
        }

        return $email_parts[0] . '@' . $email_parts[1];
    }

}
