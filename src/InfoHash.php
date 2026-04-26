<?php

namespace Ufw;

/**
 * Two-index collection (hash + info) over DB-backed or in-memory rows: {@see $hash} groups by a secondary key,
 * {@see $info} stores rows by id; supports JSON serialization and iteration.
 *
 * @property array $id_arr
 * @property array $idArr
 * @property string $id_list
 * @property string $idList
 *
 */
class InfoHash implements \JsonSerializable, \Iterator, \Countable
{

    public $info = array(), $hash = array();

    public $id_field_name, $hash_field_name;

    /**
     * Query used to populate data
     * 
     * @var string
     */
    protected $data_query;

    /**
     * DB conn used to fetch data_query
     * 
     * @var string
     */
    protected $data_conn;

    /**
     * Get total number of possible items in collection (can be used only with data query set)
     * 
     * @var int
     */
    protected $total_count = null;

    protected static $dbClass = \Ufw\Db\Db::class;

    protected $id_arr;

    protected $id_list;

    public function __construct($id_field_name = "id", $hash_field_name = "name")
    {
        $this->id_field_name = $id_field_name;
        $this->hash_field_name = $hash_field_name;
    }

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

    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->info);
    }

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

    public function addEntry($object)
    {
        $this->info[$object->{$this->id_field_name}] = $object;
        $this->hash[$object->{$this->hash_field_name}] = $object->{$this->id_field_name};
        return $this;
    }

    /**
     * @see @method addEntry
     * @deprecated
     */
    public function add_entry($object)
    {
        $this->addEntry($object);
    }

    public function add_entry_array($object)
    {
        $this->info[$object[$this->id_field_name]] = (object) $object;
        $this->hash[$object[$this->hash_field_name]] = $object[$this->id_field_name];
    }

    public function rehash()
    {
        $this->hash = array();
        if ($this->info) {
            foreach ($this->info as $key => $value)
                $this->hash[$value->{$this->hash_field_name}] = $key;
        }
    }

    public function make_id_arr()
    {
        $this->id_arr = array_keys($this->info);
    }

    public function make_id_list()
    {
        $this->id_list = join(', ', $this->getDbClass()::quote($this->id_arr));
    }

    public function dump_opt_list($value_field = null)
    {
        $buf = '';
        if (!$value_field)
            $value_field = $this->hash_field_name;
        foreach ($this->info as $key => $value) {
            $buf .= '<option value="' . $key . '">' . $value->$value_field . '</option>';
        }
        return $buf;
    }

    public function tweak_selected($options_str, $selected_value)
    {
        if (($selected_value !== false) && strlen($selected_value)) {
            $options_str = preg_replace('/<option value="' . $selected_value . '">/i', '<option value="' . $selected_value . '" selected="selected">', $options_str);
        }
        return $options_str;
    }

    public function set_data($query, $class_name = "stdClass", $conn = false, $collect_properties = array())
    {
        $out = null;
        $this->setDataQuery($query)->setDataConn($conn);
        $dbc = $this->getDbClass()::instance();
        $result = $dbc->query($query, $conn, __FILE__, __LINE__);
        if ($result) {
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
                        $out[$value][$row->$value] = $row->$value;
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

    public function aggregate_data($query, $id_field_local, $conn = false)
    {
        $dbc = $this->getDbClass()::instance();
        $result = $dbc->query($query, $conn, __FILE__, __LINE__);
        while ($row = $dbc->fetch_array($result, $this->getDbClass()::DB_ASSOC)) {
            $this->info[$row[$id_field_local]] = (object) array_merge((array) $this->info[$row[$id_field_local]], $row);
        }
        $dbc->free($result);
    }

    public function add_array_data($query, $id_field_local, $property_name, $conn = false, $class_name = "stdClass", $collect_property = false, $datafield = false, $indexfield = false)
    {
        /** @var \Ufw\Db\Db $dbc */
        $dbc = $this->getDbClass()::instance();
        $result = $dbc->query($query, $conn, __FILE__, __LINE__);
        $data = array();
        while ($row = $dbc->fetch_object($result, $class_name)) {
            $this->__set_property_value($row, $id_field_local, $property_name, $datafield, $indexfield);
            if ($collect_property) {
                if (is_scalar($collect_property)) {
                    $data[] = $row->$collect_property;
                } elseif (is_callable($collect_property)) {
                    $data[] = call_user_func($collect_property, $this->info[$row->$id_field_local], $row);
                }
            }
        }
        $dbc->free($result);
        return $data;
    }

    public function set_array_data($arr, $id_field_local, $property_name, $datafield = false, $indexfield = false)
    {
        foreach ($arr as $obj) {
            $this->__set_property_value($obj, $id_field_local, $property_name, $datafield, $indexfield);
        }
    }

    protected function __set_property_value($obj, $id_field_local, $property_name, $datafield = false, $indexfield = false)
    {
        if ($datafield) {
            if ($indexfield) {
                $this->info[$obj->$id_field_local]->{$property_name}[$obj->$indexfield] = $obj->$datafield;
            } else {
                $this->info[$obj->$id_field_local]->{$property_name}[] = $obj->$datafield;
            }
        } else {
            if ($indexfield) {
                $this->info[$obj->$id_field_local]->{$property_name}[$obj->$indexfield] = $obj;
            } else {
                $this->info[$obj->$id_field_local]->{$property_name}[] = $obj;
            }
        }
    }

    protected static function __xml_serialize_recursive($node_name, $node_value, &$parent_node)
    {
        $type = gettype($node_value);
        switch ($type) {
            case 'array':
            case 'object':
                if (is_numeric($node_name)) {
                    $node = $parent_node->addChild('item');
                    $node->addAttribute('key', $node_name);
                } else {
                    $node = $parent_node->addChild($node_name);
                }
                foreach ($node_value as $key => $value) {
                    self::__xml_serialize_recursive($key, $value, $node);
                }
                break;
            case 'integer':
            case 'boolean':
            case 'double':
            case 'string':
                if (is_numeric($node_name)) {
                    $node = $parent_node->addChild('item', htmlspecialchars($node_value));
                    $node->addAttribute('key', $node_name);
                } else {
                    $node = $parent_node->addChild($node_name, htmlspecialchars($node_value));
                }
                break;
            case 'unknown type':
            default:
                // do nothing
                break;
        }
    }

    protected function __xml_serialize($php_val, $xml_node = null)
    {
        if (is_string($xml_node)) {
            $xml_node = new \SimpleXMLElement("<$xml_node></$xml_node>");
        } elseif (!$xml_node) {
            $xml_node = new \SimpleXMLElement('<root></root>');
        }
        if (is_object($php_val))
            $vars = get_object_vars($php_val);
        else
            $vars = &$php_val;
        foreach ($vars as $key => $value) {
            self::__xml_serialize_recursive($key, $value, $xml_node);
        }
        return $xml_node->asXML();
    }

    public function asXML($root_node_name = 'root', $mode = 'auto')
    {
        switch ($mode) {
            case 'info_only':
                $xml = $this->__xml_serialize($this->info, $root_node_name);
                break;
            case 'auto':
                if (count($this->id_arr) == 1) {
                    $xml = $this->__xml_serialize($this->info[$this->id_list], $root_node_name);
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
     *
     * @return string
     */
    public function getDataQuery()
    {
        return $this->data_query;
    }

    /**
     *
     * @param string $data_query
     */
    public function setDataQuery($data_query)
    {
        $this->data_query = $data_query;
        return $this;
    }

    /**
     *
     * @return number
     */
    public function getTotalCount()
    {
        if (!isset($this->total_count) && $this->getDataQuery()) {
            if (strpos($this->getDataQuery(), "LIMIT")) {
                $query = preg_replace('~(ORDER BY|LIMIT).*$~is', '', $this->getDataQuery());
                if (strpos($query, "GROUP BY")) {
                    $query = "SELECT COUNT(1) AS cnt FROM ($query) tmp";
                } else {
                    $query = preg_replace('~^\s*SELECT\s+.*?FROM~is', 'SELECT COUNT(1) AS cnt FROM', $query);
                }
                list($this->total_count) = $this->getDbClass()::instance()->fetch_all($query, null, $this->getDataConn(), null, 'cnt', __FILE__, __LINE__);
            } else {
                $this->total_count = count($this->info);
            }
        }
        return $this->total_count + 0;
    }

    public function getDataConn()
    {
        return $this->data_conn;
    }

    public function setDataConn($data_conn)
    {
        $this->data_conn = $data_conn;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getDbClass()
    {
        return static::$dbClass;
    }

    /**
     *
     * @param \Ufw\InfoHash $dbClass
     */
    public static function setDbClass($dbClass)
    {
        static::$dbClass = $dbClass;
        return static::class;
    }
}
