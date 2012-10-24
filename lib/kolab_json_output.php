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
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 +--------------------------------------------------------------------------+
*/

/**
 * View class generating JSON output
 */
class kolab_json_output
{

    /**
     * Send success response
     *
     * @param mixed $data Data
     */
    public function success($data)
    {
        if (!is_array($data)) {
            $data = array();
        }

        $this->send(array('status' => 'OK', 'result' => $data));
    }


    /**
     * Send error response
     *
     * @param mixed $data Data
     */
    public function error($errdata, $code = 400)
    {
        if (is_string($errdata)) {
            $errdata = array('reason' => $errdata);
        }

        $this->send(array('status' => 'ERROR', 'code' => $code) + $errdata);
    }


    /**
     * Send response
     *
     * @param mixed $data Data
     */
    protected function send($data)
    {
        // Encode response
        self::encode($data);

        // Send response
        header("Content-Type: application/json");
        echo json_encode($data);
        exit;
    }


    /**
     * Parse response and base64-encode non-UTF8/binary data
     *
     * @param mixed $data Data
     *
     * @return bool True if data was encoded
     */
    public static function encode(&$data)
    {
        if (is_array($data)) {
            $encoded = array();
            foreach (array_keys($data) as $key) {
                if (self::encode($data[$key])) {
                    $encoded[] = $key;
                }
            }
            if (!empty($encoded)) {
                $data['__encoded'] = $encoded;
            }
        }
        else if (is_string($data) && $data !== '') {
            $result = @json_encode($data);
            // In case of invalid characters json_encode returns "null"
            if (($result === 'null' && $data != 'null') || $result === false) {
                $data = base64_encode($data);
                return true;
            }
        }

        return false;
    }


    /**
     * Parse response and base64-decode encoded data
     *
     * @param mixed $data Data
     */
    public static function decode(&$data)
    {
        if (is_array($data)) {
            $encoded = $data['__encoded'];
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    self::decode($data[$key]);
                }
                else if (is_string($value) && $encoded && in_array($key, $encoded)) {
                    $data[$key] = base64_decode($value);
                }
            }
            unset($data['__encoded']);
        }
    }

}
