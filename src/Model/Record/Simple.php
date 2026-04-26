<?php
namespace Ufw\Model\Record;

/**
 * MySQL (or compatible) persistence through {@see \Ufw\Db\Db} `simple_*` helpers - insert, update by PK, delete by PK.
 */
abstract class Simple extends Base
{
    protected function _delete($opts = [])
    {
        $this->onBeforeDelete();
        if (!$this->getIsNewRecord()) {
            $pk = $this->getPrimaryKeyField();
            if (!is_array($pk)) {
                $pk = [
                    $pk
                ];
            }
            $result = $this->getDb()->simple_delete($this->getTableName(), array_intersect_key($this->getAll(), array_flip($pk)), static::$dbconnIdent, __FILE__, __LINE__);
        }
        $this->onAfterDelete($result);
        return $result;
    }

    protected function insert($opts = [])
    {
        return $this->getDb()->simple_insert($this->getTableName(), $this->getAll(), static::$dbconnIdent, __FILE__, __LINE__);
    }

    protected function update($opts = [])
    {
        $pk = $this->getPrimaryKeyField();
        if (!is_array($pk)) {
            $pk = [
                $pk
            ];
        }
        $pkCriteria = [];
        foreach ($pk as $field) {
            $pkCriteria[$field] = $this->$field;
        }
        return $this->getDb()->simple_update_pk($this->getTableName(), $this->getAll(), $pkCriteria, static::$dbconnIdent, __FILE__, __LINE__);
    }
}
