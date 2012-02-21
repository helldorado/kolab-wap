<?php

class SQL
{
    static private $instance = array();

    private $sql_uri = "mysql://username:password@hostname/database";

    /* Placeholder for the existing MySQL connection */
    private $conn = FALSE;

    private $sql_stats = array(
        'queries' => 0,
        'query_time' => 0,
        'connections' => 0
    );

    /**
     * This implements the 'singleton' design pattern
     *
     * @return SQL The one and only instance associated with $_conn
     */
    static function get_instance($_conn = 'kolab_wap')
    {
        if (!isset(self::$instance[$_conn])) {
            self::$instance[$_conn] = new SQL($_conn);
        }

        return self::$instance[$_conn];
    }

    public function __construct($_conn = 'kolab_wap')
    {
        $this->name = $_conn;
        $conf = Conf::get_instance();
        $this->sql_uri = $conf->get($_conn, 'sql_uri');
    }

    public function query()
    {
        if (!$this->conn) {
            $this->_connect();
        }

        $i     = 0;
        $start = 0;
        $query = func_get_arg(0);

        while (strlen($query) > $start && ($pos = strpos($query, '?', $start)) !== false) {
            $i++;
            $param = $this->escape(func_get_arg($i));

            $query = substr_replace($query, $param, $pos, 1);
            $start = $pos + strlen($param) + 1;
        }

        $result = mysql_query($query);

        if (!$result) {
            write_log('errors', 'SQL Error: ' . mysql_error($this->conn));
        }

        return $result;
    }

    public function fetch_assoc($result)
    {
        if (!$result) {
            return array();
        }

        return mysql_fetch_assoc($result);
    }

    public function escape($str)
    {
        if ($str === null || is_array($str)) {
            return 'NULL';
        }

        return "'" . mysql_real_escape_string($str, $this->conn) . "'";
    }

    private function _connect()
    {
        if (!$this->conn) {
            $_uri = parse_url($this->sql_uri);
            $this->_username = $_uri['user'];
            $this->_password = $_uri['pass'];
            $this->_hostname = $_uri['host'];
            $this->_database = str_replace('/','',$_uri['path']);

            $this->conn = mysql_connect($this->_hostname, $this->_username, $this->_password);
            mysql_select_db($this->_database, $this->conn);
        }
    }

}
