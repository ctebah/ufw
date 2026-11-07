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
    /**
     * @var array<string, array<string, mixed>>
     */
    protected $db_connections = array();

    /**
     * @var array<string, mixed>|null
     */
    protected $last_used_conn = null;

    /**
     * mysqli handle that actually ran the last statement. rows_affected and insert_id read this,
     * not the connection-map entry, which can be a different array when aliases are copied.
     *
     * @var \mysqli|null
     */
    protected $lastQueryHandle = null;

    /**
     * @var \Ufw\Utils\Stopwatch|null
     */
    protected $sw;

    /**
     * @var \Ufw\Utils\Stopwatch|null
     */
    protected $sw_total;

    /**
     * @var self|null
     */
    protected static $instance;

    /**
     * @var \mysqli_result|bool|null
     */
    public $result;

    /**
     * @var int|null
     */
    public $errno;

    /**
     * @var string|null
     */
    public $last_query;

    /**
     * @var bool
     */
    public $query_log = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    public $query_log_data = array();

    /**
     * @var string|null
     */
    protected $logfile = null;

    /**
     * True when ___opts set logFile, including false or '' to turn the file off.
     *
     * @var boolean
     */
    protected $logFileConfigured = false;

    /**
     * When true (default), a failed query log includes $_POST.
     *
     * @var boolean
     */
    protected $logPost = true;

    const QT_SELECT = 1;

    const QT_INSERT = 2;

    const QT_UPDATE = 3;

    const DB_ASSOC = MYSQLI_ASSOC;

    const DB_NUM = MYSQLI_NUM;

    const DB_BOTH = MYSQLI_BOTH;

    /**
     * @var int
     */
    protected $mysqliDriverReportMode = MYSQLI_REPORT_OFF;

    /**
     * When false (default), query errors are logged and query() returns false.
     * When true, the same log line is written and {@see QueryException} is thrown.
     *
     * @var boolean
     */
    protected $throwOnError = false;

    protected function __construct()
    {
        $connections = include \Ufw\Paths::application() . '/config/dbconns.php';
        if (is_array($connections) && array_key_exists('___opts', $connections)) {
            $opts = $connections['___opts'];
            unset($connections['___opts']);
            if (is_array($opts) && array_key_exists('throwOnError', $opts)) {
                $throwOnError = $opts['throwOnError'];
                if (is_bool($throwOnError)) {
                    $this->setThrowOnError($throwOnError);
                }
            }
            if (is_array($opts) && array_key_exists('logFile', $opts)) {
                $this->setLogFile($opts['logFile']);
            }
            if (is_array($opts) && array_key_exists('logPost', $opts)) {
                $logPost = $opts['logPost'];
                if (is_bool($logPost)) {
                    $this->setLogPost($logPost);
                }
            }
        }
        $this->db_connections = is_array($connections) ? $this->normalizeDbConnections($connections) : array();
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
     * @param array<int|string, mixed> $connections
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeDbConnections(array $connections)
    {
        $out = array();
        foreach ($connections as $name => $cfg) {
            if (!is_string($name) || !is_array($cfg)) {
                continue;
            }
            $entry = array();
            foreach ($cfg as $key => $val) {
                if (is_string($key)) {
                    $entry[$key] = $val;
                }
            }
            $out[$name] = $entry;
        }

        return $out;
    }

    /**
     * @param array<int|string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function normalizeAssocRow(array $row)
    {
        $out = array();
        foreach ($row as $key => $val) {
            if (is_string($key)) {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    /**
     *
     * Magic method __get(), specially supported values:
     * - insert_id - returns last inserted id
     * - rows_affected - result of mysqli_affected_rows();
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        switch ($name) {
            case 'insert_id':
                $handle = $this->lastQueryMysqli();
                if (!($handle instanceof \mysqli)) {
                    $value = null;
                    break;
                }
                $insertResult = mysqli_query($handle, "SELECT LAST_INSERT_ID()");
                $insertRow = ($insertResult instanceof \mysqli_result) ? mysqli_fetch_array($insertResult, self::DB_NUM) : null;
                $value = is_array($insertRow) ? $insertRow[0] : null;
                break;
            case 'num_rows':
                if ($this->result instanceof \mysqli_result) {
                    $value = mysqli_num_rows($this->result);
                } else {
                    $value = null;
                }
                break;
            case 'rows_affected':
                $handle = $this->lastQueryMysqli();
                $value = ($handle instanceof \mysqli) ? mysqli_affected_rows($handle) : null;
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
            static::$instance = new $c();
            if (!static::$instance->logFileConfigured) {
                static::$instance->logfile = \Ufw\Paths::application() . "/log/error_dblog.tmp";
            }
        }
        return static::$instance;
    }

    /**
     * Instance of the class named by app config db.class, or \Ufw\Db\Db when that key is absent.
     * getDb() uses this. instance() itself always builds the class it was called on.
     *
     * @return Db
     */
    public static function forApp()
    {
        $class = self::configuredInstanceClass();
        if ($class === null || $class === self::class) {
            return self::instance();
        }
        $appInstance = $class::instance();
        if (!$appInstance instanceof self) {
            throw new \LogicException('Configured db.class instance must extend \\Ufw\\Db\\Db');
        }

        return $appInstance;
    }

    /**
     * Class named by app config db.class. Null when config is not loaded yet or the key is absent.
     *
     * @return string|null
     */
    protected static function configuredInstanceClass()
    {
        $config = \Ufw\Application::getConfig();
        if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
            return null;
        }
        if (!array_key_exists('class', $config['db'])) {
            return null;
        }
        $class = $config['db']['class'];
        if (!is_string($class) || $class === '' || !class_exists($class) || !is_a($class, self::class, true)) {
            throw new \LogicException('app config db.class must be a subclass of \\Ufw\\Db\\Db');
        }
        return $class;
    }

    /**
     * @param string $conn_ident
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param bool $autoconnect
     * @param string|false $dbname
     * @param bool $newlink
     * @return void
     */
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
     * @param \mysqli|resource|string|null $conn_ident
     * @param bool $reconnect
     * @return \mysqli|resource|false
     */
    public function connect($conn_ident, $reconnect = false)
    {
        // PHP 8.1+ defaults to MYSQLI_REPORT_STRICT, which throws before this layer can log or retry.
        $this->setMysqliDriverReportMode($this->getMysqliDriverReportMode());
        if ($conn_ident instanceof \mysqli && !$reconnect) {
            mysqli_ping($conn_ident);
            return $conn_ident;
        }
        if (is_resource($conn_ident) && !$reconnect) {
            return $conn_ident;
        }
        if (!is_string($conn_ident) || $conn_ident === '') {
            return false;
        }
        $cn = & $this->db_connections[$conn_ident];
        if ($cn && (!isset($cn['resource']) || !$cn['resource'] || $reconnect)) {
            $port = 3306;
            if (isset($cn['port']) && $cn['port'] !== '') {
                if (is_int($cn['port'])) {
                    $port = $cn['port'];
                } elseif (is_string($cn['port']) && is_numeric($cn['port'])) {
                    $port = (int) $cn['port'];
                }
            }
            $socket = (isset($cn['socket']) && is_string($cn['socket'])) ? $cn['socket'] : '';
            $host = (isset($cn['host']) && is_string($cn['host'])) ? $cn['host'] : '';
            $user = (isset($cn['user']) && is_string($cn['user'])) ? $cn['user'] : '';
            $pass = (isset($cn['pass']) && is_string($cn['pass'])) ? $cn['pass'] : '';
            $dbnameRaw = isset($cn['dbname']) ? $cn['dbname'] : '';
            $dbname = is_string($dbnameRaw) ? $dbnameRaw : '';
            $cn['resource'] = @mysqli_connect(
                $host,
                $user,
                $pass,
                $dbname,
                $port,
                $socket
            );
            if (!$cn['resource']) {
                $this->errno = (int) mysqli_connect_errno();
                $this->last_used_conn = & $this->db_connections[$conn_ident];
                $throw = $this->throwOnError;
                $this->throwOnError = false;
                $this->handleQueryError(false, '', null, null);
                $this->throwOnError = $throw;
                if ($throw) {
                    $ident = $conn_ident;
                    throw new QueryException(
                        'Connect failed: ' . mysqli_connect_error(),
                        (int) $this->errno,
                        '',
                        $ident
                    );
                }
                return false;
            }
            $this->last_used_conn = & $this->db_connections[$conn_ident];
            if (isset($cn['collation']) && is_string($cn['collation']) && $cn['collation'] !== '') {
                mysqli_query($cn['resource'], "SET NAMES {$cn['collation']}");
            }
            if (isset($cn['time_zone']) && is_string($cn['time_zone']) && $cn['time_zone'] !== '') {
                mysqli_query($cn['resource'], "SET time_zone = '{$cn['time_zone']}'");
            }
        }
        $resource = isset($cn['resource']) ? $cn['resource'] : false;
        if ($resource instanceof \mysqli || is_resource($resource)) {
            return $resource;
        }
        return false;
    }

    /**
     * @return boolean
     */
    public function getThrowOnError()
    {
        return $this->throwOnError;
    }

    /**
     * @param boolean $throwOnError
     * @return $this
     */
    public function setThrowOnError($throwOnError)
    {
        $this->throwOnError = (bool) $throwOnError;
        return $this;
    }

    /**
     * @param mixed $logFile Path, or false or '' to skip the file. A boolean true is rejected.
     * @return $this
     */
    public function setLogFile($logFile)
    {
        $this->logFileConfigured = true;
        if ($logFile === false || $logFile === '') {
            $this->logfile = null;
            return $this;
        }
        if (!is_string($logFile)) {
            throw new \LogicException('dbconns ___opts logFile must be a path, false, or an empty string');
        }
        $this->logfile = $logFile;
        return $this;
    }

    /**
     * @param boolean $logPost
     * @return $this
     */
    public function setLogPost($logPost)
    {
        $this->logPost = (bool) $logPost;
        return $this;
    }

    /**
     * @return int
     */
    public function getMysqliDriverReportMode() {
        return $this->mysqliDriverReportMode;
    }

    /**
     * @param int $mode
     * @return $this
     */
    public function setMysqliDriverReportMode($mode) {
        $this->mysqliDriverReportMode = $mode;
        mysqli_report($mode);
        return $this;
    }

    /**
     * @param string $query
     * @param string|null $class_name
     * @param \mysqli|resource|string|null $conn_ident
     * @param string|null $index_field
     * @param string $one_property_only
     * @param string|null $file_invoker
     * @param int|string|null $line_invoker
     * @return array<array-key, mixed>
     */
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
                    $buf[static::rowFieldArrayKey($row->$index_field)] = $row->$one_property_only;
                }
            } else {
                while ($row = $this->fetch_object($this->result, $class_name)) {
                    $buf[] = $row->$one_property_only;
                }
            }
        } else {
            if ($index_field) {
                while ($row = $this->fetch_object($this->result, $class_name)) {
                    $buf[static::rowFieldArrayKey($row->$index_field)] = $row;
                }
            } else {
                while ($row = $this->fetch_object($this->result, $class_name)) {
                    $buf[] = $row;
                }
            }
        }
        return $buf;
    }

    /**
     * @param \mysqli_result|bool|null $result
     * @param int $mode
     * @return array<int|string, mixed>|null|false
     */
    public function fetch_array($result, $mode = self::DB_ASSOC)
    {
        if (!($result instanceof \mysqli_result)) {
            return null;
        }
        return mysqli_fetch_array($result, $mode);
    }

    /**
     * @param \mysqli_result|bool|null $result
     * @param string $class_name
     * @return object|null|false
     */
    public function fetch_object($result, $class_name = "stdClass")
    {
        if (!($result instanceof \mysqli_result)) {
            return null;
        }
        if (is_subclass_of($class_name, \Ufw\Model\Record\Base::class)) {
            $data = mysqli_fetch_array($result, self::DB_ASSOC);
            if (!$data) {
                return null;
            }
            return $class_name::dbfactory(self::normalizeAssocRow($data))->setIsNewRecord(false)->captureReadState();
        } else {
            return mysqli_fetch_object($result);
        }
    }

    /**
     * @param \mysqli_result|bool|null $result
     * @return void
     */
    public function free($result = null)
    {
        $result = $result ?? $this->result;
        if ($result instanceof \mysqli_result) {
            mysqli_free_result($result);
        }
    }

    /**
     * @param string|null $table_name
     * @param array<string, mixed> $params
     * @param \mysqli|resource|string|null $conn_ident
     * @param string|null $file_invoker
     * @param int|string|null $line_invoker
     * @param bool $query_execute
     * @return string|\mysqli_result|bool
     */
    public function simple_insert($table_name, $params, $conn_ident = null, $file_invoker = null, $line_invoker = null, $query_execute = true)
    {
        if (is_string($table_name) && class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
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

    /**
     * @param string|null $table_name
     * @param array<string, mixed> $params
     * @param \mysqli|resource|string|null $conn_ident
     * @param string|null $file_invoker
     * @param int|string|null $line_invoker
     * @param bool $query_execute
     * @return string|\mysqli_result|bool
     */
    public function simple_insert_update($table_name, $params, $conn_ident = null, $file_invoker = null, $line_invoker = null, $query_execute = true)
    {
        if (is_string($table_name) && class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
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

    /**
     * @param string|null $table_name
     * @param array<string, mixed> $params
     * @param class-string $info_hashclass
     * @param string $id_field_name
     * @param string $hash_field_name
     * @param \mysqli|resource|string|null $conn_ident
     * @param string|null $file_invoker
     * @param int|string|null $line_invoker
     * @return \Ufw\InfoHash
     */
    public function simple_select($table_name, $params, $info_hashclass = \Ufw\InfoHash::class, $id_field_name = 'id', $hash_field_name = 'name', $conn_ident = null, $file_invoker = null, $line_invoker = null)
    {
        $class_name = "stdClass";
        if (is_string($table_name) && class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
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
     * @param string|null $table_name Physical table name, or a {@see \Ufw\Model\Record\Base} subclass (resolved via {@see \Ufw\Model\Record\Base::getTableName()}, same rule as {@see simple_select()}).
     * @param array<string, mixed> $params
     * @return string
     */
    public function simple_select_query($table_name, $params)
    {
        if (is_string($table_name) && class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
            $table_name = $table_name::getTableName();
        }
        if (!isset($params['where'])) {
            $params['where'] = $params;
        }
        $where = $params['where'];
        if (!is_array($where)) {
            $where = array();
        }
        if (!isset($params['fields'])) {
            $params['fields'] = '*';
        }
        $fields = $params['fields'];
        if (is_array($fields)) {
            $fieldParts = array();
            foreach ($fields as $fieldPart) {
                if (is_string($fieldPart)) {
                    $fieldParts[] = $fieldPart;
                }
            }
            $fields = join(', ', $fieldParts);
        } elseif (!is_string($fields)) {
            $fields = '*';
        }
        if (!($subq = self::subquery($where, ' AND ', true))) {
            $subq = '1=1';
        }
        $orderBy = '';
        if (isset($params['orderby'])) {
            $orderParts = array();
            foreach ((array) $params['orderby'] as $orderPart) {
                if (is_string($orderPart)) {
                    $orderParts[] = $orderPart;
                }
            }
            if ($orderParts !== array()) {
                $orderBy = 'ORDER BY ' . join(', ', $orderParts) . ' ';
            }
        }
        $limit = '';
        if (isset($params['limit'])) {
            $limitVal = $params['limit'];
            if (is_int($limitVal) || is_string($limitVal)) {
                $limit = 'LIMIT ' . $limitVal;
            }
        }
        return "SELECT {$fields} FROM $table_name WHERE $subq " . $orderBy . $limit;
    }

    /**
     * @param string|null $table_name
     * @param array<string, mixed> $params
     * @param \mysqli|resource|string|null $conn_ident
     * @param string|null $file_invoker
     * @param int|string|null $line_invoker
     * @return \mysqli_result|bool
     */
    public function simple_update($table_name, $params, $conn_ident = null, $file_invoker = null, $line_invoker = null)
    {
        return $this->simple_update_pk($table_name, $params, array(
            'id' => $params['id']
        ), $conn_ident, $file_invoker, $line_invoker);
    }

    /**
     * @param string|null $table_name
     * @param array<string, mixed> $params
     * @param array<string, mixed> $params_pk
     * @param \mysqli|resource|string|null $conn_ident
     * @param string|null $file_invoker
     * @param int|string|null $line_invoker
     * @return \mysqli_result|bool
     */
    public function simple_update_pk($table_name, $params, $params_pk, $conn_ident = null, $file_invoker = null, $line_invoker = null)
    {
        if (is_string($table_name) && class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
            $class_name = $table_name;
            $table_name = $class_name::getTableName();
        }
        $query = "UPDATE $table_name SET " . self::subquery($params) . " WHERE " . self::subquery($params_pk, ' AND ', true);
        return $this->query($query, $conn_ident, $file_invoker, $line_invoker);
    }

    /**
     *
     * @param string|null $table_name
     * @param array<string, mixed> $params_pk
     * @param \mysqli|resource|string|null $conn_ident
     * @param string|null $file_invoker
     * @param int|string|null $line_invoker
     * @return \mysqli_result|bool
     */
    public function simple_delete($table_name, $params_pk, $conn_ident = null, $file_invoker = null, $line_invoker = null)
    {
        if (is_string($table_name) && class_exists($table_name) && is_subclass_of($table_name, \Ufw\Model\Record\Base::class)) {
            $class_name = $table_name;
            $table_name = $class_name::getTableName();
        }
        $query = "DELETE FROM $table_name WHERE " . self::subquery($params_pk, ' AND ', true);
        return $this->query($query, $conn_ident, $file_invoker, $line_invoker);
    }

    /**
     * Legacy array offset for dynamic row fields ({@see fetch_all()} index column).
     *
     * @param mixed $value
     * @return array-key
     */
    protected static function rowFieldArrayKey($value)
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value)) {
            if ($value === '') {
                return '';
            }
            if (preg_match('/^-?\d+$/', $value) === 1) {
                return (int) $value;
            }
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_bool($value)) {
            return (int) $value;
        }
        if ($value === null) {
            return '';
        }
        return '';
    }

    /**
     * @param array<mixed> $values
     * @return string
     */
    protected static function joinQuotedList(array $values)
    {
        $quoted = static::quote($values);
        $parts = array();
        foreach ($quoted as $part) {
            $parts[] = is_scalar($part) ? (string) $part : '';
        }
        return join(', ', $parts);
    }

    /**
     * Static function
     *
     * @param array<int|string, mixed> $array
     * @param string $glue
     * @param bool $cmpQuery
     * @return string
     */
    public static function subquery($array, $glue = ",\n", $cmpQuery = false)
    {
        $query = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $query[] = "$key IN (" . static::joinQuotedList($value) . ")";
            } elseif ($value instanceof \Ufw\Db\Condition) {
                $query[] = (string) $value;
            } elseif ($value instanceof \Ufw\Db\Expression) {
                $query[] = "$key = " . (string) $value;
            } else {
                $quoted = static::quote($value);
                if ($cmpQuery && is_null($value)) {
                    $query[] = "$key IS " . $quoted;
                } else {
                    $query[] = "$key = " . $quoted;
                }
            }
        }
        return join($glue, $query);
    }

    /**
     *
     * @param string $query
     * @param \mysqli|resource|string|null $conn_ident
     * @param string|null $file_invoker
     * @param int|string|null $line_invoker
     * @param int $retries
     * @return \mysqli_result|true|false
     */
    public function query($query, $conn_ident = null, $file_invoker = null, $line_invoker = null, $retries = 0)
    {
        if ($this->query_log && $this->sw instanceof \Ufw\Utils\Stopwatch) {
            $this->sw->reset();
        }

        // echo $query, "\n\n";
        if ($conn_ident instanceof \mysqli || is_resource($conn_ident)) {
            $dbhandle = $conn_ident;
        } else {
            if (!$conn_ident && !$this->last_used_conn) {
                $conn_ident = 'default';
            }
            if (is_string($conn_ident) && $conn_ident !== '' && strcmp($conn_ident, '__last_used')) {
                $this->last_used_conn = & $this->db_connections[$conn_ident];
            }
            $ident = 'default';
            if (is_array($this->last_used_conn) && isset($this->last_used_conn['ident']) && is_string($this->last_used_conn['ident']) && $this->last_used_conn['ident'] !== '') {
                $ident = $this->last_used_conn['ident'];
            }
            $dbhandle = $this->connect($ident);
        }
        $this->last_query = $query;
        if ($dbhandle instanceof \mysqli) {
            $this->lastQueryHandle = $dbhandle;
        }
        if (!($dbhandle instanceof \mysqli)) {
            $this->result = false;
            $this->errno = (int) mysqli_connect_errno();
            if ($this->throwOnError) {
                $this->handleQueryError($dbhandle, $query, $file_invoker, $line_invoker);
            }
            return false;
        }
        // echo $query, "\n\n";
        // debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 11);
        // echo "\n---\n";

        $this->result = mysqli_query($dbhandle, $query);
        $this->errno = mysqli_errno($dbhandle);
        if ($this->errno) {
            if ((($this->errno == 2006) || ($this->errno == 2013)) && !$retries) { // Mysql server gone away (2006), Lost connection to Mysql during query (2013)
                $retryConnectKey = $this->resolveRetryConnectKey($conn_ident);
                $this->connect($retryConnectKey, true);
                return $this->query($query, $conn_ident, $file_invoker, $line_invoker, ++$retries);
            } elseif ($this->errno != 1062 || $this->throwOnError) { // 1062 - duplicate entry, silent unless throwing
                $this->handleQueryError($dbhandle, $query, $file_invoker, $line_invoker);
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
                'time' => ($this->sw instanceof \Ufw\Utils\Stopwatch) ? $this->sw->elapsed_hr() : '',
                'total' => ($this->sw_total instanceof \Ufw\Utils\Stopwatch) ? $this->sw_total->elapsed_hr() : ''
            );
        }

        if ($this->result instanceof \mysqli_result) {
            return $this->result;
        }
        if ($this->result === true) {
            return true;
        }
        return false;
    }

    /**
     * Connection key for {@see connect()} after "server gone away" / lost connection (errno 2006 / 2013).
     *
     * @param \mysqli|resource|string|null $conn_ident
     * @return string
     */
    protected function resolveRetryConnectKey($conn_ident)
    {
        $retryConnectKey = 'default';
        if (is_array($this->last_used_conn) && isset($this->last_used_conn['ident']) && is_string($this->last_used_conn['ident']) && $this->last_used_conn['ident'] !== '') {
            $retryConnectKey = $this->last_used_conn['ident'];
        } elseif (is_string($conn_ident) && $conn_ident !== '' && !strcmp($conn_ident, '__last_used')) {
            $retryConnectKey = $conn_ident;
        } elseif (is_string($conn_ident) && $conn_ident !== '' && isset($this->db_connections[$conn_ident])) {
            $retryConnectKey = $conn_ident;
        }

        return $retryConnectKey;
    }

    /**
     * @return \mysqli|null
     */
    protected function lastQueryMysqli()
    {
        if ($this->lastQueryHandle instanceof \mysqli) {
            return $this->lastQueryHandle;
        }
        if (is_array($this->last_used_conn) && isset($this->last_used_conn['resource']) && ($this->last_used_conn['resource'] instanceof \mysqli)) {
            return $this->last_used_conn['resource'];
        }
        return null;
    }

    /**
     * Log a failed statement the way query() always has, then throw when {@see $throwOnError} is on.
     * Duplicate-key errno 1062 is only passed here when throwing; otherwise the caller stays silent.
     *
     * @param \mysqli|resource|false $dbhandle
     * @param string $query
     * @param string|null $file_invoker
     * @param int|string|null $line_invoker
     * @return void
     */
    protected function handleQueryError($dbhandle, $query, $file_invoker, $line_invoker)
    {
        $ident = '';
        if (is_array($this->last_used_conn) && isset($this->last_used_conn['ident']) && is_string($this->last_used_conn['ident'])) {
            $ident = $this->last_used_conn['ident'];
        }
        $error = ($dbhandle instanceof \mysqli) ? mysqli_error($dbhandle) : (string) mysqli_connect_error();
        $serverName = (isset($_SERVER['SERVER_NAME']) && is_string($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : '';
        $phpSelf = (isset($_SERVER['PHP_SELF']) && is_string($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : '';
        $queryString = (isset($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : '';
        $httpReferer = (isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '';
        $httpUserAgent = (isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $str = "Date and time: " . date("Y-m-d H:i:s") . "\n" .
            "Conn: {$ident}\n" .
            "Query: " . $query . "\n" .
            "Error: " . $error . " ($this->errno)\n" .
            ($file_invoker && $line_invoker ? "File: Line $line_invoker in $file_invoker\n" : "") .
            ($serverName !== '' ? "http://{$serverName}" : '') .
                $phpSelf .
                $queryString . "\n" .
            ($httpReferer !== '' ? "Referer: {$httpReferer}\n" : '') .
            ($httpUserAgent !== '' ? "Browser: {$httpUserAgent}\n" : '');
        if ($this->logPost && !empty($_POST)) {
            $str .= "POST: ";
            foreach ($_POST as $key => $value) {
                if (is_string($key) && (is_scalar($value) || $value === null)) {
                    $str .= $key . '=' . (string) $value . '&';
                }
            }
            $str .= "\n";
        }
        ob_start();
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 11);
        $str .= ob_get_clean();
        if ($this->logfile) {
            error_log("$str\n", 3, $this->logfile);
        }
        if ($this->throwOnError) {
            throw new QueryException($error . ' (' . $this->errno . ')', (int) $this->errno, (string) $query, $ident);
        }
    }

    /**
     * Static helper: returns $value escaped for safe use in SQL statements.
     *
     * @param mixed $value
     * @return ($value is array ? array<array-key, mixed> : int|string)
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
        } elseif (is_null($value) || (is_string($value) && strcmp($value, 'nil') === 0)) {
            return 'NULL';
        } elseif ($value instanceof \Ufw\Db\Expression) {
            return (string) $value;
        }
        if (!is_string($value)) {
            return 'NULL';
        }
        $conn = self::instance()->last_used_conn;
        $live = (is_array($conn) && isset($conn['resource'])) ? $conn['resource'] : null;
        if ($live instanceof \mysqli) {
            $ret = mysqli_real_escape_string($live, $value);
            if (!$ret && $value !== '') {
                // stale connection; reopen
                $ident = (isset($conn['ident']) && is_string($conn['ident']) && $conn['ident'] !== '') ? $conn['ident'] : 'default';
                $resource = self::instance()->connect($ident, true);
                if ($resource instanceof \mysqli) {
                    $ret = mysqli_real_escape_string($resource, $value);
                } else {
                    $ret = addslashes($value);
                }
            }
            return "'$ret'";
        }
        return "'" . addslashes($value) . "'";
    }

    /**
     * @return void
     */
    public static function query_log_table()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            return;
        }
        $dbc = static::instance();
        echo '<style type="text/css"> .query-log-table TH, .query-log-table TD { padding: 4px; } </style>';
        echo '<table class="query-log-table" border="1" style="border-collapse: collapse; width: 990px;">';
        foreach ($dbc->query_log_data as $key => $row) {
            if ($key == 0) {
                echo '<tr><th>No.</th><th>' . join('</th><th>', array_keys($row)) . '</th></tr>';
            }
            $cells = array();
            foreach ($row as $cell) {
                $cells[] = is_scalar($cell) || $cell === null ? (string) $cell : '';
            }
            echo '<tr><td>', ($key + 1), '</td><td>', join('</td><td>', $cells), '</td></tr>';
        }
        echo '</table>';
    }
}
