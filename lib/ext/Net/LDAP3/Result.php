<?php

/*
 +-----------------------------------------------------------------------+
 | Net/LDAP3/Result.php                                                  |
 |                                                                       |
 | Based on rcube_ldap_result.php created by the Roundcube Webmail       |
 | client development team.                                              |
 |                                                                       |
 | Copyright (C) 2006-2012, The Roundcube Dev Team                       |
 | Copyright (C) 2012, Kolab Systems AG                                  |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for plugins.                        |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide advanced functionality for accessing LDAP directories       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Authors: Thomas Bruederli <roundcube@gmail.com>                       |
 |          Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                 |
 +-----------------------------------------------------------------------+
*/

/**
 * Model class representing an LDAP search result
 *
 * @package LDAP
 */
class Net_LDAP3_Result implements Iterator
{
    protected $conn;
    protected $base_dn;
    protected $filter;
    protected $scope;

    private $count = null;
    private $current = null;
    private $iteratorkey = 0;

    /**
     *
     */
    function __construct($conn, $base_dn, $filter, $scope, $result)
    {
        $this->conn = $conn;
        $this->base_dn = $base_dn;
        $this->filter = $filter;
        $this->scope = $scope;
        $this->result = $result;
    }

    public function get($property, $default = null)
    {
        if (isset($this->$property)) {
            return $this->$property;
        } else {
            return $default;
        }
    }

    public function set($property, $value)
    {
        $this->$property = $value;
    }

    /**
     *
     */
    public function sort($attr)
    {
        return ldap_sort($this->conn, $this->result, $attr);
    }

    /**
     *
     */
    public function count()
    {
        if (!isset($this->count))
            $this->count = ldap_count_entries($this->conn, $this->result);

        return $this->count;
    }

    /**
     *
     */
    public function entries($normalize = false)
    {
        $entries = ldap_get_entries($this->conn, $this->result);

        if ($normalize) {
            return Net_LDAP3::normalize_result($entries);
        }

        return $entries;
    }


    /***  Implement PHP 5 Iterator interface to make foreach work  ***/

    function current()
    {
        return ldap_get_attributes($this->conn, $this->current);
    }

    function key()
    {
        return $this->iteratorkey;
    }

    function rewind()
    {
        $this->iteratorkey = 0;
        $this->current = ldap_first_entry($this->conn, $this->result);
    }

    function next()
    {
        $this->iteratorkey++;
        $this->current = ldap_next_entry($this->conn, $this->current);
    }

    function valid()
    {
        return (bool)$this->current;
    }

}

