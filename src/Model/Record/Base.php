<?php
namespace Ufw\Model\Record;

/**
 * Active-record base: row data as {@see \Ufw\Utils\ArrayAccess}, new/existing flag, optional {@see behaviours()} pipeline,
 * and save/delete orchestration. Subclasses implement {@see insert}, {@see update}, {@see _delete} (e.g. {@see Simple}, {@see Solr}).
 *
 * {@see dbfactory()} always calls `new static($data)`. Subclasses keep that constructor signature.
 *
 * Row columns read or written as `$this->column` must be listed on the concrete record class with `@property` /
 * `@property-read` (PHPStan). Use {@see getAll()} / {@see setData()} when a field is intentionally dynamic.
 *
 * @phpstan-consistent-constructor
 */
abstract class Base extends \Ufw\Utils\ArrayAccess
{
    /**
     * @var string|null
     */
    protected static $tableName = null;

    /**
     * @var string
     */
    protected static $dbconnIdent = 'master_server';

    /**
     * Indicates whether the current record is new
     *
     * @var boolean
     */
    protected $isNewRecord = true;

    /**
     * primary key field(s) name
     *
     * @var string|array<int, string>|null
     */
    protected static $primaryKeyField = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct($data = array())
    {
        $this->setData($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return static
     */
    public static function dbfactory($data)
    {
        return (new static($data));
    }

    /**
     * @return array<string, \Ufw\Model\Behaviour\Base|array<string, mixed>>
     */
    public function behaviours()
    {
        return array();
    }

    /**
     *
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return \Ufw\Registry::getInstance()->getApplication();
    }


    /**
     * @param string $name
     * @return \Ufw\Helper\Base|null
     */
    protected function getHelper($name)
    {
        return $this->getApplication()->getHelper($name);
    }

    /**
     * @return string|null
     */
    public static function getTableName()
    {
        return static::$tableName;
    }

    /**
     * @return string
     */
    public static function getDbconnIdent()
    {
        return static::$dbconnIdent;
    }

    /**
     * * events and behaviour handlers **
     */
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    protected function beforeDataSet($data, $opts = [])
    {
        return $data;
    }

    /**
     * @param array<string, mixed> $opts
     * @return void
     */
    protected function afterDataSet($opts = [])
    {
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function beforeDelete($opts = [])
    {
    }

    /**
     * @param array<string, mixed> $opts
     * @return void
     */
    protected function afterDelete($opts = [])
    {
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function beforeInsert($opts = [])
    {
    }

    /**
     * @param array<string, mixed> $opts
     * @return void
     */
    protected function afterInsert($opts = [])
    {
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function beforeUpdate($opts = [])
    {
    }

    /**
     * @param array<string, mixed> $opts
     * @return void
     */
    protected function afterUpdate($opts = [])
    {
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function beforeSave($opts = [])
    {
    }

    /**
     * @param array<string, mixed> $opts
     * @return void
     */
    protected function afterSave($opts = [])
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    final protected function onBeforeDataSet($data, $opts = [])
    {
        foreach ($this->getBehaviours('beforeDataSet') as $behaviour) {
            /**
             * @var \Ufw\Model\Behaviour\Base $behaviour
             */
            $data = $behaviour->beforeDataSet($data, $opts);
        }
        return $this->beforeDataSet($data, $opts);
    }

    /**
     * @param array<string, mixed> $opts
     * @return void
     */
    final protected function onAfterDataSet($opts = [])
    {
        foreach ($this->getBehaviours('afterDataSet') as $behaviour) {
            $behaviour->afterDataSet($opts);
        }
        $this->afterDataSet($opts);
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    final protected function onBeforeDelete($opts = [])
    {
        foreach ($this->getBehaviours('beforeDelete') as $behaviour) {
            $behaviour->beforeDelete($opts);
        }
        return $this->beforeDelete($opts);
    }

    /**
     * @param mixed $success
     * @param array<string, mixed> $opts
     * @return void
     */
    final protected function onAfterDelete($success, $opts = [])
    {
        foreach ($this->getBehaviours('afterDelete') as $behaviour) {
            $behaviour->afterDelete($success, $opts);
        }
        $this->afterDelete();
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    final protected function onBeforeInsert($opts = [])
    {
        foreach ($this->getBehaviours('beforeInsert') as $behaviour) {
            $behaviour->beforeInsert($opts);
        }
        return $this->beforeInsert($opts);
    }

    /**
     * @param mixed $success
     * @param array<string, mixed> $opts
     * @return void
     */
    final protected function onAfterInsert($success, $opts = [])
    {
        foreach ($this->getBehaviours('afterInsert') as $behaviour) {
            $behaviour->afterInsert($success, $opts);
        }
        $this->afterInsert($opts);
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    final protected function onBeforeUpdate(&$opts = [])
    {
        foreach ($this->getBehaviours('beforeUpdate') as $behaviour) {
            $behaviour->beforeUpdate($opts);
        }
        return $this->beforeUpdate($opts);
    }

    /**
     * @param mixed $success
     * @param array<string, mixed> $opts
     * @return void
     */
    final protected function onAfterUpdate($success, $opts = [])
    {
        foreach ($this->getBehaviours('afterUpdate') as $behaviour) {
            $behaviour->afterUpdate($success, $opts);
        }
        $this->afterUpdate($opts);
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    final protected function onBeforeSave($opts = [])
    {
        foreach ($this->getBehaviours('beforeSave') as $behaviour) {
            $behaviour->beforeSave($opts);
        }
        return $this->beforeSave($opts);
    }

    /**
     * @param mixed $success
     * @param array<string, mixed> $opts
     * @return void
     */
    final protected function onAfterSave($success, $opts = [])
    {
        foreach ($this->getBehaviours('afterSave') as $behaviour) {
            $behaviour->afterSave($success, $opts);
        }
        $this->afterSave($opts);
    }

    /**
     * * getters and setters **
     */
    /**
     * @param string $eventName
     * @return \Generator<string, \Ufw\Model\Behaviour\Base>
     */
    final public function getBehaviours($eventName)
    {
        $hash = $this->getBehavioursHash();
        if (!array_key_exists($eventName, $hash->hash)) {
            return;
        }
        $behaviourKeys = $hash->hash[$eventName];
        if (!is_array($behaviourKeys)) {
            return;
        }
        foreach ($behaviourKeys as $key) {
            if (!is_int($key) && !is_string($key)) {
                continue;
            }
            if (!array_key_exists($key, $hash->info)) {
                continue;
            }
            $entry = $hash->info[$key];
            if (!property_exists($entry, 'obj')) {
                continue;
            }
            $behaviourObj = $entry->obj;
            if (!$behaviourObj instanceof \Ufw\Model\Behaviour\Base) {
                continue;
            }
            $yieldKey = is_string($key) ? $key : (string) $key;
            yield $yieldKey => $behaviourObj->setOwner($this);
        }
    }

    /**
     *
     * @return \Ufw\InfoHashArray
     */
    final public function getBehavioursHash()
    {
        if (!\Ufw\Registry::getInstance()->has('___model_behaviours')) {
            \Ufw\Registry::getInstance()->set('___model_behaviours', new \Ufw\Container(\Ufw\InfoHashArray::class, [
                'ident',
                'method'
            ]));
        }
        /** @var \Ufw\Container<\Ufw\InfoHashArray> $container */
        $container = \Ufw\Registry::getInstance()->get('___model_behaviours');
        if (!$container->has(get_class($this))) {
            $hash = $container->get(get_class($this));
            $prepared = array();
            $pos = 0;
            foreach ($this->behaviours() as $ident => $behaviour) {
                if (!is_object($behaviour)) {
                    $class = $behaviour['class'];
                    $obj = new $class($this);
                    foreach ($behaviour as $key => $value) {
                        if ($key === 'class') {
                            continue;
                        }
                        $obj->$key = $value;
                    }
                    $behaviour = $obj;
                }
                $order = 500;
                if (isset($behaviour->order) && $behaviour->order !== '') {
                    $orderRaw = $behaviour->order;
                    if (is_int($orderRaw)) {
                        $order = $orderRaw;
                    } elseif (is_numeric($orderRaw)) {
                        $order = (int) $orderRaw;
                    }
                }
                $prepared[] = array(
                    'ident' => $ident,
                    'obj' => $behaviour,
                    'order' => $order,
                    'pos' => $pos
                );
                $pos++;
            }
            usort($prepared, function ($a, $b) {
                if ($a['order'] === $b['order']) {
                    return $a['pos'] < $b['pos'] ? -1 : 1;
                }
                return $a['order'] < $b['order'] ? -1 : 1;
            });
            foreach ($prepared as $item) {
                $ident = $item['ident'];
                $behaviour = $item['obj'];
                foreach (array_intersect(\Ufw\Model\Behaviour\Base::$behaviourMethods, get_class_methods($behaviour)) as $method) {
                    $hash->add_entry_array(array(
                        'method' => $method,
                        'ident' => $ident,
                        'obj' => $behaviour
                    ));
                }
            }
        }
        return $container->get(get_class($this));
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function _delete($opts = [])
    {
        return false;
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function insert($opts = [])
    {
        return false;
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function update($opts = [])
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRelationsData()
    {
        return [];
    }

    /**
     * @return $this
     */
    public function reRead()
    {
        $pk = $this->getPrimaryKeyField();
        if (is_string($pk)) {
            $pk = array($pk);
        } elseif (!is_array($pk)) {
            $pk = array();
        }
        $query = $this->getDb()->simple_select_query($this->getTableName(), array_intersect_key($this->getAll(), array_flip($pk)));
        $data = $this->getDb()->fetch_array($this->getDb()->query($query, static::$dbconnIdent, __FILE__, __LINE__));
        $this->getDb()->free();
        $this->setAll($data);
        $this->captureReadState();
        return $this;
    }

    /**
     * @return $this
     */
    public function readRelationsData()
    {
        return $this;
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    public function delete($opts = [])
    {
        $this->onBeforeDelete($opts);
        if (!$this->getIsNewRecord()) {
            $result = $this->_delete($opts);
        } else {
            $result = false;
        }
        $this->onAfterDelete($result, $opts);
        return $result;
    }

    /**
     * @param bool $withRelationsData
     * @param array<string, mixed> $opts
     * @return mixed
     */
    public function save($withRelationsData = false, $opts = [])
    {
        $this->onBeforeSave($opts);
        if ($this->getIsNewRecord()) {
            $this->onBeforeInsert($opts);
            $result = $this->insert($opts);
            $this->onAfterInsert($result, $opts);
        } else {
            $this->onBeforeUpdate($opts);
            $result = $this->update($opts);
            $this->onAfterUpdate($result, $opts);
        }
        if ($result && $withRelationsData) {
            $this->saveRelationsData();
        }
        $this->onAfterSave($result, $opts);
        if ($result) {
            $this->setIsNewRecord(false);
            $this->captureReadState();
        }
        return $result;
    }

    /**
     * @return $this
     */
    public function saveRelationsData()
    {
        return $this;
    }

    /**
     * Save simple N-N to relational db.
     *
     * N-to-N table struct is relational_table_name, eg. a_b(a_id, b_id).
     *
     * $table_name is the name of that table - "a_b" in the example
     * $currentTableRefField - a_id
     * $relationRefField - b_id
     * $newRelationValues - b_id values to be in the DB as the result of the operation
     *
     * @param string $tableName
     * @param string $currentTableRefField
     * @param string $relationRefField
     * @param array<int, mixed> $newRelationValues
     *
     * @return \Ufw\Model\Record\Base
     */
    protected function saveSimpleRelation($tableName, $currentTableRefField, $relationRefField, $newRelationValues)
    {
        $id = $this->{$this->getPrimaryKeyField()};
        $query = $this->getDb()->simple_select_query($tableName, [
            $currentTableRefField => $id
        ]);
        $existingIds = $this->getDb()->fetch_all($query, $tableName, static::$dbconnIdent, null, $relationRefField, __FILE__, __LINE__);
        $newIds = $this->normalizeRelationValueList($newRelationValues);
        $existingNormalized = $this->normalizeRelationValueList($existingIds);

        if ($newIds) {
            foreach (array_diff($newIds, $existingNormalized) as $newId) {
                $this->getDb()->simple_insert($tableName, [
                    $currentTableRefField => $id,
                    $relationRefField => $newId
                ], static::$dbconnIdent, __FILE__, __LINE__);
            }
        }

        if ($newIds) {
            if ($toDelete = array_diff($existingNormalized, $newIds)) {
                $this->getDb()->simple_delete($tableName, [
                    $currentTableRefField => $id,
                    $relationRefField => $toDelete
                ], static::$dbconnIdent, __FILE__, __LINE__);
            }
        } else {
            if ($toDelete = $existingIds) {
                $this->getDb()->simple_delete($tableName, [
                    $currentTableRefField => $id,
                    $relationRefField => $toDelete
                ], static::$dbconnIdent, __FILE__, __LINE__);
            }
        }
        return $this;
    }

    /**
     * Pivot/relation id lists for {@see saveSimpleRelation()} (scalar ids only; keeps int/string for loose diff).
     *
     * @param array<int|string, mixed> $values
     * @return list<int|string>
     */
    protected function normalizeRelationValueList(array $values)
    {
        $out = array();
        foreach ($values as $value) {
            if (is_int($value) || is_string($value)) {
                $out[] = $value;
            }
        }
        return $out;
    }

    /**
     * @return \Ufw\Db\Db
     */
    public function getDb()
    {
        return \Ufw\Db\Db::forApp();
    }

    /**
     * @return array<string, mixed>
     */
    public function getData()
    {
        return $this->getAll() + $this->getRelationsData();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $opts
     * @return $this
     */
    public function setData($data, $opts = [])
    {
        $this->setAll($data);
        $this->_data = $this->onBeforeDataSet($this->_data, $opts);
        $this->onAfterDataSet($opts);
        return $this;
    }

    /**
     * Values copied at fetch / reRead / successful save. Null until a read snapshot is taken.
     *
     * @var array<string, mixed>|null
     */
    protected $readState = null;

    /**
     * Rows affected by the last update statement. Null when that update was skipped.
     *
     * @var int|string|null
     */
    protected $lastWriteAffected = null;

    /**
     * Copy the current row. Taken when the row is read, and again after a successful save.
     * {@see \Ufw\Model\Record\Simple::update()} uses the primary key from this copy in WHERE.
     * A record built in memory, not read, has no copy.
     *
     * @return $this
     */
    public function captureReadState()
    {
        $this->readState = $this->getAll();
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getReadState()
    {
        return $this->readState;
    }

    /**
     * @param int|string|null $affected
     * @return $this
     */
    public function setLastWriteAffected($affected)
    {
        $this->lastWriteAffected = $affected;
        return $this;
    }

    /**
     * @return int|string|null
     */
    public function getLastWriteAffected()
    {
        return $this->lastWriteAffected;
    }

    /**
     *
     * @return boolean
     */
    public function getIsNewRecord()
    {
        return $this->isNewRecord;
    }

    /**
     *
     * @param bool $isNewRecord
     * @return $this
     */
    public function setIsNewRecord($isNewRecord)
    {
        $this->isNewRecord = $isNewRecord;
        return $this;
    }

    /**
     *
     * @return string|array<int, string>|null
     */
    public function getPrimaryKeyField()
    {
        return static::$primaryKeyField;
    }

    /**
     *
     * @param string|array<int, string>|null $primaryKeyField
     * @return $this
     */
    public function setPrimaryKeyField($primaryKeyField)
    {
        static::$primaryKeyField = $primaryKeyField;
        return $this;
    }
}
