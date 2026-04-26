<?php
namespace Ufw\Db;

/**
 * MySQLi access layer (singleton): loads connections from app config, runs queries, and exposes {@see quote()}
 * for safe literals. Optional shutdown query logging is gated only by the **`DBLOG_ENV`** constant (dev bootstrap —
 * not application config). Magic properties reflect the last result / connection (`insert_id`, `num_rows`, `rows_affected`).
 *
 * @property mixed $insert_id
 * @property int $num_rows
 * @property int $rows_affected
 */
class Db
{
    protected $db_connections = array();

    protected $last_used_conn = null;
    protected $sw;
    protected $sw_total;

    protected static $instance;

    public $result;
    public $errno;
    public $last_query;

    public $query_log = false;
    public $query_log_data = array();

    protected $logfile = null;

    const QT_SELECT = 1;

    const QT_INSERT = 2;

    const QT_UPDATE = 3;

    const DB_ASSOC = MYSQLI_ASSOC;

    const DB_NUM = MYSQLI_NUM;

    const DB_BOTH = MYSQLI_BOTH;

    protected $mysqliDriverReportMode = MYSQLI_REPORT_OFF;

    protected function __construct()
    {
        $this->db_connections = include \Ufw\Paths::application() . '/config/dbconns.php';
        // Shutdown query log: dev-only. Set `define('DBLOG_ENV', true)` in a local/bootstrap entry point - not via app config (never enable in production).
        if (\defined('DBLOG_ENV') && (bool) \constant('DBLOG_ENV')) {
            register_shutdown_function(array(
                $this,
                'query_log_table'
            ));
            $this->query_log = true;
            $this->sw = new \Ufw\Utils\Stopwatch();
            $this->sw_total = new \Ufw\Utils\Stopwatch();
        }
    }

    /**
     *
     * Magic method __get(), specially supported values:
     * - insert_id - returns last inserted id
     * - rows_affected - result of mysqli_affected_rows();
     *
     * @param string $name
     */
    public function __get($name)
    {
        switch ($name) {
            case 'insert_id':
                list($value) = mysqli_fetch_array(mysqli_query($this->last_used_conn['resource'], "SELECT LAST_INSERT_ID()"), self::DB_NUM);
                break;
            case 'num_rows':
                if (($this->result instanceof \mysqli_result) || is_resource($this->result)) {
                    $value = mysqli_num_rows($this->result);
                } else {
                    $value = null;
                }
                break;
            case 'rows_affected':
                $value = mysqli_affected_rows($this->last_used_conn['resource']);
                break;
            default:
                $value = null;
        }
        return $value;
    }

    /**
     *
     * @return Db
     */
    public static function instance()
    {
        if (!isset(static::$instance)) {
            $c = get_called_class();
            self::$instance = new $c();
            // set log file path
            self::$instance->logfile = \Ufw\Paths::application() . "/log/error_dblog.tmp";
        }
        return self::$instance;
    }

    public function set_connection($conn_ident, $host, $user, $pass, $autoconnect = false, $dbname = false, $newlink = false)
    {
        $this->db_connections[$conn_ident] = array(
            'ident' => $conn_ident,
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'dbname' => $dbname,
            'newlink' => $newlink
        );
        if ($autoconnect) {
            $this->connect($conn_ident);
        }
    }

    /**
     * Connects to the database if not already connected, or when $reconnect forces a new connection.
     *
     * @param mixed $conn_ident
     *            resource or string (connection identifier from config)
     * @param boolean $reconnect
     * @return \mysqli
     */
    public function connect($conn_ident, $reconnect = false)
    {
        if ((($conn_ident instanceof \mysqli) || is_resource($conn_ident)) && !$reconnect) {
            mysqli_ping($conn_ident);
            return $conn_ident;
        } else {
            $cn = & $this->db_connections[$conn_ident];
            if ($cn && (!isset($cn['resource']) || !$cn['resource'] || $reconnect)) {
                $port = isset($cn['port']) && $cn['port'] !== '' && $cn['port'] !== null
                    ? (int) $cn['port']
                    : 3306;
                $socket = isset($cn['socket']) ? (string) $cn['socket'] : '';
                $cn['resource'] = mysqli_connect(
                    $cn['host'],
                    $cn['user'],
                    $cn['pass'],
                    isset($cn['dbname']) ? $cn['dbname'] : '',
                    $port,
                    $socket
                );
                $this->last_used_conn = & $this->db_connections[$conn_ident];
                if (isset($cn['collation']) && $cn['collation']) {
                    mysqli_query($cn['resource'], "SET NAMES {$cn['collation']}");
                }
                if (isset($cn['time_zone']) && $cn['time_zone']) {
                    mysqli_query($cn['resource'], "SET time_zone = '{$cn['time_zone']}'");
                }
            }
            $this->setMysqliDriverReportMode($this->getMysqliDriverReportMode());
            return $cn['resource'];
        }
    }

    public function getMysqliDriverReportMode() {
        return $this->mysqliDriverReportMode;
    }

    public function setMysqliDriverReportMode($mode) {
        $this->mysqliDriverReportMode = $mode;
        mysqli_report($mode);
        return $this;
    }

    public function fetch_all($query, $class_name = null, $conn_ident = null, $index_field = null, $one_property_only = '', $file_invoker = null, $line_invoker = null)
    {
        $this->result = $this->query($query, $conn_ident, $file_invoker, $line_invoker);
        if (!$class_name || !class_exists($class_name) || !is_subclass_of($class_name, \Ufw\Model\Record\Base::class)) {
            $class_name = 'stdClass';
        }
        $buf = [];
        if ($one_property_only) {
            if ($index_field) {
                while ($row = $this->fetch_object($this->result, $class_name)) {
                    $buf[$row->$index_field] = $row->$one_property_only;
                }
            } else {
                while ($row = $this->fetch_object($this->result, $class_name)) {
                    $buf[] = $row->$one_property_only;
                }
            }
        } else {
            if ($index_field) {
                while ($row = $this->fetch_object($this->result, $class_name)) {
                    $buf[$row->$index_field] = $row;
                }
            } else {
                while ($row = $this->fetch_object($this->result, $class_name)) {
                    $buf[] = $row;
                }
            }
        }
        return $buf;
    }

    public function fetch_array($result, $mode = self::DB_ASSOC)
    {
        return mysqli_fetch_array($result, $mode);
    }

    public function fetch_object($result, $class_name = "stdClass")
    {
        if (is_subclass_of($class_name, \Ufw\Model\Record\Base::class)) {
            $data = mysqli_fetch_array($result, self::DB_ASSOC);
            return $data ? $class_name::dbfactory($data)->setIsNewRecord(false) : null;
        } else {
            return mysqli_fetch_object($result);
        }
    }

    public function free($result = null)
    {
        if ($result || $this->result) {
            mysqli_free_result($result ?? $this->result);
        }
    }

    public function simple_insert($table_name, $params, $conn_ident = null, $file_invoker = null, $line_invoker = null, $query_execute = true)
    {
        if (class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
            $class_name = $table_name;
            $table_name = $class_name::getTableName();
        }
        $query = "INSERT INTO $table_name SET " . self::subquery($params);
        if ($query_execute) {
            return $this->query($query, $conn_ident, $file_invoker, $line_invoker);
        } else {
            return $query;
        }
    }

    public function simple_insert_update($table_name, $params, $conn_ident = null, $file_invoker = null, $line_invoker = null, $query_execute = true)
    {
        if (class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
            $class_name = $table_name;
            $table_name = $class_name::getTableName();
        }
        $subq = self::subquery($params);
        $query = "INSERT INTO $table_name SET $subq ON DUPLICATE KEY UPDATE $subq";
        if ($query_execute) {
            return $this->query($query, $conn_ident, $file_invoker, $line_invoker);
        } else {
            return $query;
        }
    }

    public function simple_select($table_name, $params, $info_hashclass = \Ufw\InfoHash::class, $id_field_name = 'id', $hash_field_name = 'name', $conn_ident = null, $file_invoker = null, $line_invoker = null)
    {
        $class_name = "stdClass";
        if (class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
            $class_name = $table_name;
            $table_name = $class_name::getTableName();
        }
        $query = $this->simple_select_query($table_name, $params);
        // echo $query, "<br>";

        /**
         *
         * @var \Ufw\InfoHash|\Ufw\InfoHashArray $data
         */
        $data = new $info_hashclass($id_field_name, $hash_field_name);
        $data->set_data($query, $class_name, $conn_ident);
        return $data;
    }

    /**
     * Build a SELECT ... FROM ... WHERE ... SQL string.
     *
     * @param string $table_name Physical table name, or a {@see \Ufw\Model\Record\Base} subclass (resolved via {@see \Ufw\Model\Record\Base::getTableName()}, same rule as {@see simple_select()}).
     * @param array<string, mixed> $params
     */
    public function simple_select_query($table_name, $params)
    {
        if (class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
            $table_name = $table_name::getTableName();
        }
        if (!isset($params['where'])) {
            $params['where'] = $params;
        }
        if (!isset($params['fields'])) {
            $params['fields'] = '*';
        }
        if (is_array($params['fields'])) {
            $params['fields'] = join(', ', $params['fields']);
        }
        if (!($subq = self::subquery($params['where'], ' AND ', true))) {
            $subq = '1=1';
        }
        return "SELECT {$params['fields']} FROM $table_name WHERE $subq " . (isset($params['orderby']) ? 'ORDER BY ' . join(', ', (array) $params['orderby']) . ' ' : '') . (isset($params['limit']) ? 'LIMIT ' . $params['limit'] : '');
    }

    public function simple_update($table_name, $params, $conn_ident = null, $file_invoker = null, $line_invoker = null)
    {
        return $this->simple_update_pk($table_name, $params, array(
            'id' => $params['id']
        ), $conn_ident, $file_invoker, $line_invoker);
    }

    public function simple_update_pk($table_name, $params, $params_pk, $conn_ident = null, $file_invoker = null, $line_invoker = null)
    {
        if (class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
            $class_name = $table_name;
            $table_name = $class_name::getTableName();
        }
        $query = "UPDATE $table_name SET " . self::subquery($params) . " WHERE " . self::subquery($params_pk, ' AND ', true);
        return $this->query($query, $conn_ident, $file_invoker, $line_invoker);
    }

    /**
     *
     * @param string $table_name
     * @param array $params_pk
     * @param string $conn_ident
     * @param string $file_invoker
     * @param string $line_invoker
     */
    public function simple_delete($table_name, $params_pk, $conn_ident = null, $file_invoker = null, $line_invoker = null)
    {
        if (class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
            $class_name = $table_name;
            $table_name = $class_name::getTableName();
        }
        $query = "DELETE FROM $table_name WHERE " . self::subquery($params_pk, ' AND ', true);
        return $this->query($query, $conn_ident, $file_invoker, $line_invoker);
    }

    /**
     * Static function
     *
     * @param array $array
     * @param string $glue
     */
    public static function subquery($array, $glue = ",\n", $cmpQuery = false)
    {
        $query = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $query[] = "$key IN (" . join(', ', static::quote($value)) . ")";
            } elseif ($value instanceof \Ufw\Db\Condition) {
                $query[] = (string) $value;
            } elseif ($value instanceof \Ufw\Db\Expression) {
                $query[] = "$key = " . (string) $value;
            } else {
                if ($cmpQuery && is_null($value)) {
                    $query[] = "$key IS " . static::quote($value);
                } else {
                    $query[] = "$key = " . static::quote($value);
                }
            }
        }
        return join($glue, $query);
    }

    /**
     *
     * @param string $query
     * @param mixed $conn_ident
     *            string or resource - connection identifier, or an existing mysqli handle
     * @param string $file_invoker
     * @param string $line_invoker
     * @param integer $retries
     */
    public function query($query, $conn_ident = null, $file_invoker = null, $line_invoker = null, $retries = 0)
    {
        if ($this->query_log) {
            $this->sw->reset();
        }

        // echo $query, "\n\n";
        if (($conn_ident instanceof \mysqli) || is_resource($conn_ident)) {
            $dbhandle = $conn_ident;
        } else {
            if (!$conn_ident && !$this->last_used_conn) {
                $conn_ident = 'default';
            }
            if (isset($conn_ident) && $conn_ident && strcmp($conn_ident, '__last_used')) {
                $this->last_used_conn = & $this->db_connections[$conn_ident];
            }
            $dbhandle = $this->connect($this->last_used_conn['ident']);
        }
        $this->last_query = $query;
        // echo $query, "\n\n";
        // debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 11);
        // echo "\n---\n";

        $this->result = mysqli_query($dbhandle, $query);
        $this->errno = mysqli_errno($dbhandle);
        if ($this->errno && ($this->errno != 1062)) { // 1062 - duplicate entry
            if ((($this->errno == 2006) || ($this->errno == 2013)) && !$retries) { // Mysql server gone away (2006), Lost connection to Mysql during query (2013)
                $this->connect($conn_ident, true);
                $this->query($query, $conn_ident, $file_invoker, $line_invoker, ++$retries);
            } else {
                // ob_start();
                // debug_print_backtrace();
                // $backtrace = ob_get_clean();
                $str = "Date and time: " . date("Y-m-d H:i:s") . "\n" .
                    "Conn: {$this->last_used_conn['ident']}\n" .
                    "Query: " . $query . "\n" .
                    "Error: " . mysqli_error($dbhandle) . " ($this->errno)\n" .
                    ($file_invoker && $line_invoker ? "File: Line $line_invoker in $file_invoker\n" : "") .
                    (empty($_SERVER['SERVER_NAME']) ? '' : "http://{$_SERVER['SERVER_NAME']}") .
                        (empty($_SERVER['PHP_SELF']) ? '' : $_SERVER['PHP_SELF']) . 
                        (empty($_SERVER['QUERY_STRING']) ? '' : $_SERVER['QUERY_STRING']) . "\n" .
                    (@$_SERVER['HTTP_REFERER'] ? "Referer: {$_SERVER['HTTP_REFERER']}\n" : '') .
                    (@$_SERVER['HTTP_USER_AGENT'] ? "Browser: {$_SERVER['HTTP_USER_AGENT'] }\n" : '');
                if ($_POST) {
                    $str .= "POST: ";
                    foreach ($_POST as $key => $value) {
                        $str .= "$key=$value&";
                    }
                    $str .= "\n";
                }
                ob_start();
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 11);
                $str .= ob_get_clean();
                error_log("$str\n", 3, $this->logfile);
                // \Ufw\mydie();
            }
        }

        if ($this->query_log) {
            $query_type = self::QT_SELECT;
            if (preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)) {
                $query_type = self::QT_UPDATE;
                if (preg_match('/^\s*(insert|replace)\s/i', $query)) {
                    $query_type = self::QT_INSERT;
                }
            }

            ob_start();
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 11);
            $trace = ob_get_clean();

            $this->query_log_data[] = array(
                'query' => $query,
                'rows' => $this->errno ? 'err: ' . $this->errno : ($query_type == self::QT_SELECT ? $this->num_rows : $this->rows_affected),
                'file' => nl2br($trace),
                'line' => $line_invoker,
                'time' => $this->sw->elapsed_hr(),
                'total' => $this->sw_total->elapsed_hr()
            );
        }

        return $this->result;
    }

    /**
     * Static helper: returns $value escaped for safe use in SQL statements.
     *
     * @param mixed $value
     * @return string | array
     */
    public static function quote($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_float($value)) {
            return sprintf('%F', $value);
        } elseif (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = static::quote($val);
            }
            return $value;
        } elseif (is_bool($value)) {
            return $value + 0;
        } elseif (is_null($value) || !strcmp($value, 'nil')) {
            return 'NULL';
        } elseif ($value instanceof \Ufw\Db\Expression) {
            return (string) $value;
        }
        if ((@self::instance()->last_used_conn['resource'] instanceof \mysqli) || @is_resource(self::instance()->last_used_conn['resource'])) {
            $ret = mysqli_real_escape_string(self::instance()->last_used_conn['resource'], $value);
            if (!$ret && $value) {
                // stale connection; reopen
                $resource = self::instance()->connect(self::instance()->last_used_conn['ident'], true);
                if ($resource) {
                    $ret = mysqli_real_escape_string($resource, $value);
                } else {
                    $ret = addslashes($value);
                }
            }
            return "'$ret'";
        } else {
            return "'" . addslashes($value) . "'";
        }
    }

    public static function query_log_table()
    {
        if ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            return;
        }
        $dbc = static::instance();
        echo '<style type="text/css"> .query-log-table TH, .query-log-table TD { padding: 4px; } </style>';
        echo '<table class="query-log-table" border="1" style="border-collapse: collapse; width: 990px;">';
        foreach ($dbc->query_log_data as $key => $row) {
            if ($key == 0) {
                echo '<tr><th>No.</th><th>' . join('</th><th>', array_keys($row)) . '</th></tr>';
            }
            echo '<tr><td>', ($key + 1), '</td><td>', join('</td><td>', $row), '</td></tr>';
        }
        echo '</table>';
        \Ufw\mydie();
    }
}
