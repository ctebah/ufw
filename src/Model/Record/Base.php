<?php
namespace Ufw\Model\Record;

/**
 * Active-record base: row data as {@see \Ufw\Utils\ArrayAccess}, new/existing flag, optional {@see behaviours()} pipeline,
 * and save/delete orchestration. Subclasses implement {@see insert}, {@see update}, {@see _delete} (e.g. {@see Simple}, {@see Solr}).
 */
abstract class Base extends \Ufw\Utils\ArrayAccess
{
    protected static $tableName = null;
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
     * @var string|array
     */
    protected static $primaryKeyField = null;

    public function __construct($data = array())
    {
        $this->setData($data);
    }

    public static function dbfactory($data)
    {
        return (new static($data));
    }

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
        return \Ufw\Registry::getInstance()->get('application');
    }


    protected function getHelper($name)
    {
        return $this->getApplication()->getHelper($name);
    }

    public static function getTableName()
    {
        return static::$tableName;
    }

    public static function getDbconnIdent()
    {
        return static::$dbconnIdent;
    }

    /**
     * * events and behaviour handlers **
     */
    protected function beforeDataSet($data)
    {
        return $data;
    }

    protected function afterDataSet($opts = [])
    {
    }

    protected function beforeDelete($opts = [])
    {
    }

    protected function afterDelete($opts = [])
    {
    }

    protected function beforeInsert($opts = [])
    {
    }

    protected function afterInsert($opts = [])
    {
    }

    protected function beforeUpdate($opts = [])
    {
    }

    protected function afterUpdate($opts = [])
    {
    }

    protected function beforeSave($opts = [])
    {
    }

    protected function afterSave($opts = [])
    {
    }

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

    final protected function onAfterDataSet($opts = [])
    {
        foreach ($this->getBehaviours('afterDataSet') as $behaviour) {
            $behaviour->afterDataSet($opts);
        }
        $this->afterDataSet($opts);
    }

    final protected function onBeforeDelete($opts = [])
    {
        foreach ($this->getBehaviours('beforeDelete') as $behaviour) {
            $behaviour->beforeDelete($opts);
        }
        return $this->beforeDelete($opts);
    }

    final protected function onAfterDelete($success, $opts = [])
    {
        foreach ($this->getBehaviours('afterDelete') as $behaviour) {
            $behaviour->afterDelete($success, $opts);
        }
        $this->afterDelete();
    }

    final protected function onBeforeInsert($opts = [])
    {
        foreach ($this->getBehaviours('beforeInsert') as $behaviour) {
            $behaviour->beforeInsert($opts);
        }
        return $this->beforeInsert($opts);
    }

    final protected function onAfterInsert($success, $opts = [])
    {
        foreach ($this->getBehaviours('afterInsert') as $behaviour) {
            $behaviour->afterInsert($success, $opts);
        }
        $this->afterInsert($opts);
    }

    final protected function onBeforeUpdate($opts = [])
    {
        foreach ($this->getBehaviours('beforeUpdate') as $behaviour) {
            $behaviour->beforeUpdate($opts);
        }
        return $this->beforeUpdate($opts);
    }

    final protected function onAfterUpdate($success, $opts = [])
    {
        foreach ($this->getBehaviours('afterUpdate') as $behaviour) {
            $behaviour->afterUpdate($success, $opts);
        }
        $this->afterUpdate($opts);
    }

    final protected function onBeforeSave($opts = [])
    {
        foreach ($this->getBehaviours('beforeSave') as $behaviour) {
            $behaviour->beforeSave($opts);
        }
        return $this->beforeSave($opts);
    }

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
    final public function getBehaviours($eventName)
    {
        if (isset($this->getBehavioursHash()->hash[$eventName])) {
            foreach ($this->getBehavioursHash()->hash[$eventName] as $key) {
                yield $key => $this->getBehavioursHash()->info[$key]->obj->setOwner($this);
            }
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
        /**
         *
         * @var \Ufw\Container $container
         */
        $container = \Ufw\Registry::getInstance()->get('___model_behaviours');
        if (!$container->has(get_class($this))) {
            $hash = $container->get(get_class($this));
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

    protected function _delete($opts = [])
    {
        return false;
    }

    protected function insert($opts = [])
    {
        return false;
    }

    protected function update($opts = [])
    {
        return false;
    }

    public function getRelationsData()
    {
        return [];
    }

    public function reRead()
    {
        $pk = $this->getPrimaryKeyField();
        if (!is_array($pk)) {
            $pk = [$pk];
        }
        $query = $this->getDb()->simple_select_query($this->getTableName(), array_intersect_key($this->getAll(), array_flip($pk)));
        $data = $this->getDb()->fetch_array($this->getDb()->query($query, static::$dbconnIdent, __FILE__, __LINE__));
        $this->getDb()->free();
        $this->setAll($data);
        return $this;
    }

    public function readRelationsData()
    {
        return $this;
    }

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
        }
        return $result;
    }

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
     * @param array $newRelationValues
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

        if ($newRelationValues) {
            foreach (array_diff($newRelationValues, $existingIds) as $newId) {
                $this->getDb()->simple_insert($tableName, [
                    $currentTableRefField => $id,
                    $relationRefField => $newId
                ], static::$dbconnIdent, __FILE__, __LINE__);
            }
        }

        if ($newRelationValues) {
            if ($toDelete = array_diff($existingIds, $newRelationValues)) {
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

    public function getDb()
    {
        return \Ufw\Db\Db::instance();
    }

    public function getData()
    {
        return $this->getAll() + $this->getRelationsData();
    }

    public function setData($data, $opts = [])
    {
        $this->setAll($data);
        $this->_data = $this->onBeforeDataSet($this->_data, $opts);
        $this->onAfterDataSet($opts);
        return $this;
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
     * @param boolean $isNewRecord
     */
    public function setIsNewRecord($isNewRecord)
    {
        $this->isNewRecord = $isNewRecord;
        return $this;
    }

    /**
     *
     * @return string|array
     */
    public function getPrimaryKeyField()
    {
        return static::$primaryKeyField;
    }

    /**
     *
     * @param string|array $primaryKeyField
     */
    public function setPrimaryKeyField($primaryKeyField)
    {
        static::$primaryKeyField = $primaryKeyField;
        return $this;
    }
}
