<?php

namespace Ufw;

/**
 * Two-index collection (hash + info) over DB-backed or in-memory rows: {@see $hash} groups by a secondary key,
 * {@see $info} stores rows by id; supports JSON serialization and iteration.
 *
 * @implements \Iterator<int|string, object>
 *
 * @property-read array<int|string> $id_arr
 * @property-read array<int|string> $idArr
 * @property-read string $id_list
 * @property-read string $idList
 */
class InfoHash implements \JsonSerializable, \Iterator, \Countable
{

    /**
     * Rows keyed by id field values (PHP coerces scalar keys at runtime).
     *
     * @var array<array-key, object>
     */
    public $info = array();

    /**
     * Hash field value → id field value (PHP coerces array keys/types at runtime).
     *
     * @var array<array-key, mixed>
     */
    public $hash = array();

    /**
     * @var string
     */
    public $id_field_name;

    /**
     * @var string
     */
    public $hash_field_name;

    /**
     * @var string|null
     */
    protected $data_query;

    /**
     * @var \mysqli|resource|string|false|null
     */
    protected $data_conn;

    /**
     * @var int|null
     */
    protected $total_count = null;

    /**
     * @var class-string<\Ufw\Db\Db>
     */
    protected static $dbClass = \Ufw\Db\Db::class;

    /**
     * @var array<int|string>
     */
    protected $id_arr = array();

    /**
     * @var string|null
     */
    protected $id_list;

    /**
     * @param string $id_field_name
     * @param string $hash_field_name
     */
    public function __construct($id_field_name = "id", $hash_field_name = "name")
    {
        $this->id_field_name = $id_field_name;
        $this->hash_field_name = $hash_field_name;
    }

    /**
     * @param \mysqli|resource|string|false|null $conn
     * @return \mysqli|resource|string|null
     */
    protected function normalizeConn($conn)
    {
        return $conn === false ? null : $conn;
    }

    /**
     * @param object $object
     * @return int|string|null
     */
    protected function idFromObject($object)
    {
        $id = $object->{$this->id_field_name};
        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * @param object $object
     * @return string|null
     */
    protected function hashKeyFromObject($object)
    {
        $hash = $object->{$this->hash_field_name};
        if (is_string($hash)) {
            return $hash;
        }
        if (is_int($hash)) {
            return (string) $hash;
        }
        return null;
    }

    /**
     * @param object $object
     * @param string $propertyName
     * @return int|string|null
     */
    protected function scalarKeyFromObject($object, $propertyName)
    {
        $value = $object->$propertyName;
        return is_int($value) || is_string($value) ? $value : null;
    }

    /**
     * @param object $object
     * @param string $propertyName
     * @return string
     */
    protected function scalarStringFromObject($object, $propertyName)
    {
        $value = $object->$propertyName;
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }
        return '';
    }

    /**
     * Offset for legacy {@see $info} / {@see $hash} writes (PHP array-key casting).
     *
     * @param mixed $value
     * @return array-key
     */
    protected function writeArrayKey($value)
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
     * @param object $parent
     * @param string $propertyName
     * @return array<array-key, mixed>
     */
    protected function arrayPropertyRef($parent, $propertyName)
    {
        if (property_exists($parent, $propertyName)) {
            $existing = $parent->$propertyName;
            if (is_array($existing)) {
                return $existing;
            }
        }
        $empty = array();
        $parent->$propertyName = $empty;
        return $empty;
    }

    /**
     * List property on an {@see $info} row (legacy dynamic stdClass bags).
     *
     * @param array-key $infoKey
     * @param string $propertyName
     * @return array<array-key, mixed>
     */
    protected function &listPropertyOnInfoRow($infoKey, $propertyName)
    {
        $row = $this->info[$infoKey];
        if (!property_exists($row, $propertyName) || !is_array($row->$propertyName)) {
            $row->$propertyName = array();
        }
        return $row->$propertyName;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $out = null;
        switch ($name) {
            case 'id_arr':
            case 'idArr':
                $this->make_id_arr();
                $out = $this->id_arr;
                break;
            case 'id_list':
            case 'idList':
                $this->make_id_list();
                $out = $this->id_list;
                break;
        }
        return $out;
    }

    /**
     * @return array<int, object>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_values($this->info);
    }

    public function count(): int
    {
        return count($this->info);
    }

    public function rewind(): void
    {
        reset($this->info);
    }

    /**
     * @return object|false
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->info);
    }

    /**
     * @return int|string|null
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->info);
    }

    public function next(): void
    {
        next($this->info);
    }

    public function valid(): bool
    {
        return !is_null($this->key());
    }

    /**
     * @param object $object
     * @return $this
     */
    public function addEntry($object)
    {
        // Match master: PHP array-key coercion (int/string/float/null); do not drop rows silently.
        $this->info[$this->writeArrayKey($object->{$this->id_field_name})] = $object;
        $this->hash[$this->writeArrayKey($object->{$this->hash_field_name})] = $this->writeArrayKey($object->{$this->id_field_name});
        return $this;
    }

    /**
     * @param object $object
     * @return void
     * @deprecated
     */
    public function add_entry($object)
    {
        $this->addEntry($object);
    }

    /**
     * @param array<mixed, mixed> $object
     * @return void
     */
    public function add_entry_array($object)
    {
        $this->info[$this->writeArrayKey($object[$this->id_field_name])] = (object) $object;
        $this->hash[$this->writeArrayKey($object[$this->hash_field_name])] = $this->writeArrayKey($object[$this->id_field_name]);
    }

    /**
     * @return $this
     */
    public function rehash()
    {
        $this->hash = array();
        if ($this->info) {
            foreach ($this->info as $key => $value) {
                $this->hash[$this->writeArrayKey($value->{$this->hash_field_name})] = $key;
            }
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function make_id_arr()
    {
        $this->id_arr = array_keys($this->info);
        return $this;
    }

    /**
     * @return $this
     */
    public function make_id_list()
    {
        $this->make_id_arr();
        $quoted = $this->getDbClass()::quote($this->id_arr);
        $parts = array();
        foreach ($quoted as $part) {
            $parts[] = is_scalar($part) ? (string) $part : '';
        }
        $this->id_list = join(', ', $parts);
        return $this;
    }

    /**
     * @param string|null $value_field
     * @return string
     */
    public function dump_opt_list($value_field = null)
    {
        $buf = '';
        if (!$value_field)
            $value_field = $this->hash_field_name;
        foreach ($this->info as $key => $value) {
            $label = $this->scalarStringFromObject($value, $value_field);
            $buf .= '<option value="' . $key . '">' . $label . '</option>';
        }
        return $buf;
    }

    /**
     * @param string $options_str
     * @param mixed $selected_value
     * @return string
     */
    public function tweak_selected($options_str, $selected_value)
    {
        if ($selected_value === false || !is_scalar($selected_value)) {
            return $options_str;
        }
        $selected = (string) $selected_value;
        if ($selected !== '') {
            $replaced = preg_replace('/<option value="' . $selected . '">/i', '<option value="' . $selected . '" selected="selected">', $options_str);
            if ($replaced !== null) {
                $options_str = $replaced;
            }
        }
        return $options_str;
    }

    /**
     * @param string $query
     * @param class-string $class_name
     * @param \mysqli|resource|string|false|null $conn
     * @param array<int, string>|string $collect_properties
     * @return array<string, array<array-key, mixed>>|null
     */
    public function set_data($query, $class_name = "stdClass", $conn = false, $collect_properties = array())
    {
        $out = null;
        $this->setDataQuery($query)->setDataConn($conn);
        $dbc = $this->getDbClass()::instance();
        $result = $dbc->query($query, $this->normalizeConn($conn), __FILE__, __LINE__);
        if ($result instanceof \mysqli_result) {
            if ($collect_properties) {
                if (!is_array($collect_properties)) {
                    $collect_properties = array(
                        $collect_properties
                    );
                }
                $out = array();
                while ($row = $dbc->fetch_object($result, $class_name)) {
                    $this->addEntry($row);
                    foreach ($collect_properties as $value) {
                        if (!isset($out[$value])) {
                            $out[$value] = array();
                        }
                        $prop = $row->$value;
                        $out[$value][$this->writeArrayKey($prop)] = $prop;
                    }
                }
            } else {
                $out = null;
                while ($row = $dbc->fetch_object($result, $class_name)) {
                    $this->addEntry($row);
                }
            }
            $dbc->free($result);
        }
        return $out;
    }

    /**
     * @param string $query
     * @param string $id_field_local
     * @param \mysqli|resource|string|false|null $conn
     * @return void
     */
    public function aggregate_data($query, $id_field_local, $conn = false)
    {
        $dbc = $this->getDbClass()::instance();
        $result = $dbc->query($query, $this->normalizeConn($conn), __FILE__, __LINE__);
        if (!($result instanceof \mysqli_result)) {
            return;
        }
        while ($row = $dbc->fetch_array($result, \Ufw\Db\Db::DB_ASSOC)) {
            $idKey = $this->writeArrayKey($row[$id_field_local]);
            $existing = array_key_exists($idKey, $this->info) ? (array) $this->info[$idKey] : array();
            $this->info[$idKey] = (object) array_merge($existing, $row);
        }
        $dbc->free($result);
    }

    /**
     * @param string $query
     * @param string $id_field_local
     * @param string $property_name
     * @param \mysqli|resource|string|false|null $conn
     * @param class-string $class_name
     * @param callable|scalar|false $collect_property
     * @param string|false $datafield
     * @param string|false $indexfield
     * @return list<mixed>
     */
    public function add_array_data($query, $id_field_local, $property_name, $conn = false, $class_name = "stdClass", $collect_property = false, $datafield = false, $indexfield = false)
    {
        $dbc = $this->getDbClass()::instance();
        $result = $dbc->query($query, $this->normalizeConn($conn), __FILE__, __LINE__);
        $data = array();
        if ($result instanceof \mysqli_result) {
            while ($row = $dbc->fetch_object($result, $class_name)) {
                $this->__set_property_value($row, $id_field_local, $property_name, $datafield, $indexfield);
                if ($collect_property) {
                    if (is_scalar($collect_property)) {
                        $data[] = $row->$collect_property;
                    } elseif (is_callable($collect_property)) {
                        $data[] = call_user_func($collect_property, $this->info[$this->writeArrayKey($row->$id_field_local)], $row);
                    }
                }
            }
            $dbc->free($result);
        }
        return $data;
    }

    /**
     * @param iterable<object> $arr
     * @param string $id_field_local
     * @param string $property_name
     * @param string|false $datafield
     * @param string|false $indexfield
     * @return void
     */
    public function set_array_data($arr, $id_field_local, $property_name, $datafield = false, $indexfield = false)
    {
        foreach ($arr as $obj) {
            $this->__set_property_value($obj, $id_field_local, $property_name, $datafield, $indexfield);
        }
    }

    /**
     * @param object $obj
     * @param string $id_field_local
     * @param string $property_name
     * @param string|false $datafield
     * @param string|false $indexfield
     * @return void
     */
    protected function __set_property_value($obj, $id_field_local, $property_name, $datafield = false, $indexfield = false)
    {
        $infoKey = $this->writeArrayKey($obj->$id_field_local);
        $bucket = &$this->listPropertyOnInfoRow($infoKey, $property_name);
        if ($datafield) {
            if ($indexfield) {
                $bucket[$this->writeArrayKey($obj->$indexfield)] = $obj->$datafield;
            } else {
                $bucket[] = $obj->$datafield;
            }
        } else {
            if ($indexfield) {
                $bucket[$this->writeArrayKey($obj->$indexfield)] = $obj;
            } else {
                $bucket[] = $obj;
            }
        }
    }

    /**
     * @param mixed $node_name
     * @return string|null
     */
    protected static function __xmlElementName($node_name)
    {
        if (is_string($node_name)) {
            return $node_name;
        }
        if (is_int($node_name) || is_float($node_name)) {
            return (string) $node_name;
        }
        return null;
    }

    /**
     * @param mixed $node_name
     * @param mixed $node_value
     * @param \SimpleXMLElement $parent_node
     * @return void
     */
    protected static function __xml_serialize_recursive($node_name, $node_value, $parent_node)
    {
        $type = gettype($node_value);
        switch ($type) {
            case 'array':
            case 'object':
                $nodeLabel = self::__xmlElementName($node_name);
                if ($nodeLabel === null) {
                    break;
                }
                if (is_numeric($node_name)) {
                    $node = $parent_node->addChild('item');
                    $node->addAttribute('key', $nodeLabel);
                } else {
                    $node = $parent_node->addChild($nodeLabel);
                }
                if (is_object($node_value)) {
                    $entries = get_object_vars($node_value);
                } else {
                    $entries = is_array($node_value) ? $node_value : array();
                }
                foreach ($entries as $key => $value) {
                    self::__xml_serialize_recursive($key, $value, $node);
                }
                break;
            case 'integer':
            case 'boolean':
            case 'double':
            case 'string':
                $nodeLabel = self::__xmlElementName($node_name);
                if ($nodeLabel === null) {
                    break;
                }
                if (!is_string($node_value)) {
                    break;
                }
                $text = htmlspecialchars($node_value);
                if (is_numeric($node_name)) {
                    $node = $parent_node->addChild('item', $text);
                    $node->addAttribute('key', $nodeLabel);
                } else {
                    $node = $parent_node->addChild($nodeLabel, $text);
                }
                break;
            case 'unknown type':
            default:
                // do nothing
                break;
        }
    }

    /**
     * @param mixed $php_val
     * @param \SimpleXMLElement|string|null $xml_node
     * @return string|false
     */
    protected function __xml_serialize($php_val, $xml_node = null)
    {
        if (is_string($xml_node)) {
            $xml_node = new \SimpleXMLElement("<$xml_node></$xml_node>");
        } elseif (!$xml_node) {
            $xml_node = new \SimpleXMLElement('<root></root>');
        }
        if (is_object($php_val)) {
            $vars = get_object_vars($php_val);
        } elseif (is_array($php_val)) {
            $vars = $php_val;
        } else {
            $vars = array();
        }
        foreach ($vars as $key => $value) {
            self::__xml_serialize_recursive($key, $value, $xml_node);
        }
        return $xml_node->asXML();
    }

    /**
     * @param string $root_node_name
     * @param string|object $mode
     * @return string|false
     */
    public function asXML($root_node_name = 'root', $mode = 'auto')
    {
        switch ($mode) {
            case 'info_only':
                $xml = $this->__xml_serialize($this->info, $root_node_name);
                break;
            case 'auto':
                $this->make_id_arr();
                $this->make_id_list();
                if (count($this->id_arr) == 1) {
                    $onlyId = $this->id_arr[0];
                    $xml = $this->__xml_serialize($this->info[$onlyId], $root_node_name);
                } else {
                    $xml = $this->__xml_serialize($this->info, $root_node_name);
                }
                break;
            default: // some object
                $xml = self::__xml_serialize($mode, $root_node_name);
                break;
        }
        return $xml;
    }

    /**
     * @return string|null
     */
    public function getDataQuery()
    {
        return $this->data_query;
    }

    /**
     * @param string $data_query
     * @return $this
     */
    public function setDataQuery($data_query)
    {
        $this->data_query = $data_query;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        if ($this->total_count === null && $this->getDataQuery()) {
            $query = $this->getDataQuery();
            if (strpos($query, 'LIMIT') !== false) {
                $query = preg_replace('~(ORDER BY|LIMIT).*$~is', '', $query);
                if ($query !== null && strpos($query, 'GROUP BY') !== false) {
                    $query = "SELECT COUNT(1) AS cnt FROM ($query) tmp";
                } elseif ($query !== null) {
                    $query = preg_replace('~^\s*SELECT\s+.*?FROM~is', 'SELECT COUNT(1) AS cnt FROM', $query);
                }
                if ($query !== null) {
                    $counts = $this->getDbClass()::instance()->fetch_all(
                        $query,
                        null,
                        $this->normalizeConn($this->getDataConn()),
                        null,
                        'cnt',
                        __FILE__,
                        __LINE__
                    );
                    if (array_key_exists(0, $counts)) {
                        $cnt = $counts[0];
                        if (is_int($cnt)) {
                            $this->total_count = $cnt;
                        } elseif (is_string($cnt) && is_numeric($cnt)) {
                            $this->total_count = (int) $cnt;
                        }
                    }
                }
            } else {
                $this->total_count = count($this->info);
            }
        }
        return (int) $this->total_count;
    }

    /**
     * @return \mysqli|resource|string|false|null
     */
    public function getDataConn()
    {
        return $this->data_conn;
    }

    /**
     * @param \mysqli|resource|string|false|null $data_conn
     * @return $this
     */
    public function setDataConn($data_conn)
    {
        $this->data_conn = $data_conn;
        return $this;
    }

    /**
     * @return class-string<\Ufw\Db\Db>
     */
    public function getDbClass()
    {
        return static::$dbClass;
    }

    /**
     * @param class-string<\Ufw\Db\Db> $dbClass
     * @return class-string<\Ufw\Db\Db>
     */
    public static function setDbClass($dbClass)
    {
        static::$dbClass = $dbClass;
        return $dbClass;
    }
}
