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
            if (!empty($groupdata['preferredlanguage'])) {
                $locale = $groupdata['preferredlanguage'];
            } else {
                $conf = Conf::get_instance();
                $locale = $conf->get('default_locale');
            }

            if (!empty($locale)) {
                setlocale(LC_ALL, $locale.'utf8', $locale.'UTF-8', $locale);
            }

            if (!is_array($groupdata[$key])) {
                $orig_value = $groupdata[$key];

                $result = iconv('UTF-8', 'ASCII//TRANSLIT', $groupdata[$key]);

                if (strpos($result, '?')) {
                    $result = self::transliterate($groupdata[$key], $locale);
                }

                $groupdata[$key] = preg_replace('/[^a-z0-9-_]/i', '', $result);
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

        $zero_canon = array('ou', 'cn', 'displayname', 'mailhost');

        foreach ($userdata as $key => $value) {
            if (in_array($key, $zero_canon)) {
                continue;
            }

            if (isset($keymap[$key])) {
                $_key = $keymap[$key];
            } else {
                $_key = $key;
            }

            if (!empty($userdata['preferredlanguage'])) {
                $locale = $userdata['preferredlanguage'];
            } else {
                $conf = Conf::get_instance();
                $locale = $conf->get('default_locale');
            }

            if (!empty($locale)) {
                setlocale(LC_ALL, $locale.'utf8', $locale.'UTF-8', $locale);
            }

            if (!is_array($userdata[$_key])) {
                $orig_value = $userdata[$key];

                $result = iconv('UTF-8', 'ASCII//TRANSLIT', $userdata[$key]);

                if (strstr($result, '?')) {
                    $result = self::transliterate($userdata[$key], $locale);
                }

                $userdata[$_key] = preg_replace('/[^a-z0-9-_]/i', '', $result);
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
                Log::error("Recipient policy finds that key " . $substrings[1][$x] . " does not exist in \$userdata (primary_mail)");
            }
        }

        $parsed_email = self::parse_email($primary_mail);
        return $parsed_email;
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

    static public function transliterate($mystring, $locale) {
        $locale_translit_map = Array(
                'ru_RU' => 'cyrillic'
            );

        $translit_map = Array(
                'cyrillic' => Array(
                        'А' => 'A',
                        'а' => 'a',
                        'Б' => 'B',
                        'б' => 'b',
                        'В' => 'V',
                        'в' => 'v',
                        'Г' => 'G',
                        'г' => 'g',
                        'Д' => 'D',
                        'д' => 'd',
                        'Е' => 'E',
                        'е' => 'e',
                        'Ё' => 'Yo',
                        'ё' => 'e',
                        'Ж' => 'Zh',
                        'ж' => 'zh',
                        'З' => 'Z',
                        'з' => 'z',
                        'И' => 'I',
                        'и' => 'i',
                        'Й' => 'J',
                        'й' => 'j',
                        'К' => 'K',
                        'к' => 'k',
                        'Л' => 'L',
                        'л' => 'l',
                        'М' => 'M',
                        'м' => 'm',
                        'Н' => 'N',
                        'н' => 'n',
                        'О' => 'O',
                        'о' => 'o',
                        'П' => 'P',
                        'п' => 'p',
                        'Р' => 'R',
                        'р' => 'r',
                        'С' => 'S',
                        'с' => 's',
                        'Т' => 'T',
                        'т' => 't',
                        'У' => 'U',
                        'у' => 'u',
                        'Ф' => 'F',
                        'ф' => 'f',
                        'Х' => 'Kh',
                        'х' => 'kh',
                        'Ц' => 'Tc',
                        'ц' => 'tc',
                        'Ч' => 'Ch',
                        'ч' => 'ch',
                        'Ш' => 'Sh',
                        'ш' => 'sh',
                        'Щ' => 'Shch',
                        'щ' => 'shch',
                        'Ъ' => '',
                        'ъ' => '',
                        'Ы' => 'Y',
                        'ы' => 'y',
                        'Ь' => '',
                        'ь' => '',
                        'Э' => 'E',
                        'э' => 'e',
                        'Ю' => 'Yu',
                        'ю' => 'yu',
                        'Я' => 'Ya',
                        'я' => 'ya',
                    ),
            );

        if ($translit = $translit_map[$locale_translit_map[$locale]]) {
            $mystring = strtr($mystring, $translit);
        }

        return $mystring;
    }

    static public function uid($userdata) {
        $conf = Conf::get_instance();

        if (isset($userdata['domain'])) {
            $policy_uid = $conf->get_raw($userdata['domain'], 'policy_uid');
        } else {
            $policy_uid = $conf->get_raw($_SESSION['user']->get_domain(), 'policy_uid');
            $userdata['domain'] = $_SESSION['user']->get_domain();
        }

        if (empty($policy_uid)) {
            $policy_uid = "%(surname)s.lower()";
        }

        $functions = array(
                '\'*(\w+)\'*\.capitalize\(\)' => 'strtoupper(substr("${1}", 0, 1)) . strtolower(substr("${1}", 1))',
                '\'*(.*)\'*\.lower\(\)'      => 'strtolower("${1}")',
                '\'*(\w+)\'*\.upper\(\)'      => 'strtoupper("${1}")',
            );

        $policy_uid = preg_replace('/(\{\d+\})/', '%s', $policy_uid);

        preg_match_all('/%\((\w+)\)s/', $policy_uid, $substrings);

        // Update userdata array
        for ($x = 0; $x < count($substrings[0]); $x++) {
            if (array_key_exists($substrings[1][$x], $userdata)) {
                if (!empty($substrings[2][$x])) {
                    if (!empty($substrings[3][$x])) {
                        $policy_uid = preg_replace(
                                '/%\(' . $substrings[1][$x]. '\)s/',
                                substr(
                                        $userdata[$substrings[1][$x]],
                                        $substrings[2][$x],
                                        $substrings[3][$x]
                                    ),
                                $policy_uid
                            );

                    } else {
                        $policy_uid = preg_replace(
                                '/%\(' . $substrings[1][$x]. '\)s/',
                                substr(
                                        $userdata[$substrings[1][$x]],
                                        $substrings[2][$x]
                                    ),
                                $policy_uid
                            );

                    }
                } elseif (!empty($substrings[3][$x])) {
                    $policy_uid = preg_replace(
                            '/%\(' . $substrings[1][$x]. '\)s/',
                            substr(
                                    $userdata[$substrings[1][$x]],
                                    0,
                                    $substrings[3][$x]
                                ),
                            $policy_uid
                        );

                } else {
                    $policy_uid = preg_replace(
                            '/%\(' . $substrings[1][$x]. '\)s/',
                            $userdata[$substrings[1][$x]],
                            $policy_uid
                        );

                }
            }
        }

        preg_match_all('/.*\'(.*)\'\[(\d+):(\d+)\].*/', $policy_uid, $substrings);

        for ($x = 0; $x < count($substrings[0]); $x++) {
            if (!empty($substrings[2][$x])) {
                $start = $substrings[2][$x];
            } else {
                $start = 0;
            }

            if (!empty($substrings[3][$x])) {
                $end = $substrings[3][$x];
            } else {
                $end = 0;
            }

            $policy_uid = preg_replace('/\'' . $substrings[1][$x] . '\'\['.$substrings[2][$x].':'.$substrings[3][$x].'\]/', "'".substr($substrings[1][$x], $start, $end)."'", $policy_uid);
        }

        foreach ($functions as $match => $replace) {
            if (preg_match('/' . $match . '/', $policy_uid, $strings)) {
                $policy_uid = preg_replace('/' . $match . '/', $replace, $policy_uid);
            }
        }

        $formatted_policy_uid = explode(":", $policy_uid);
        if (is_array($formatted_policy_uid) && count($formatted_policy_uid) == 2) {
            eval("\$policy_uid = sprintf(" . $formatted_policy_uid[0] . ", " . $formatted_policy_uid[1] . ");");
        } else {
            eval("\$policy_uid = '" . $policy_uid . "';");
        }

        return $policy_uid;

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
