<?php
namespace Ufw\Model\Record;

/**
 * MySQL (or compatible) persistence through {@see \Ufw\Db\Db} `simple_*` helpers - insert, update by PK, delete by PK.
 */
abstract class Simple extends Base
{
    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function _delete($opts = [])
    {
        if ($this->getIsNewRecord()) {
            return false;
        }
        $pk = $this->getPrimaryKeyField();
        if (is_string($pk)) {
            $pk = array($pk);
        } elseif (!is_array($pk)) {
            $pk = array();
        }
        return $this->getDb()->simple_delete($this->getTableName(), array_intersect_key($this->getAll(), array_flip($pk)), static::$dbconnIdent, __FILE__, __LINE__);
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function insert($opts = [])
    {
        return $this->getDb()->simple_insert($this->getTableName(), $this->getAll(), static::$dbconnIdent, __FILE__, __LINE__);
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    protected function update($opts = [])
    {
        $pk = $this->getPrimaryKeyField();
        if (is_string($pk)) {
            $pk = array($pk);
        } elseif (!is_array($pk)) {
            $pk = array();
        }
        $state = $this->getReadState();
        $pkCriteria = array();
        foreach ($pk as $field) {
            if (is_array($state) && array_key_exists($field, $state)) {
                $pkCriteria[$field] = $state[$field];
            } else {
                $pkCriteria[$field] = $this->$field;
            }
        }
        $data = $this->getAll();
        if (array_key_exists('_updateValues', $opts)) {
            if (!is_array($opts['_updateValues']) || $opts['_updateValues'] === array()) {
                $this->setLastWriteAffected(null);
                return true;
            }
            $data = \Ufw\filter_string_keyed_array($opts['_updateValues']);
        }
        if (isset($opts['_updateWhere']) && is_array($opts['_updateWhere'])) {
            $pkCriteria = $pkCriteria + \Ufw\filter_string_keyed_array($opts['_updateWhere']);
        }
        $result = $this->getDb()->simple_update_pk(
            $this->getTableName(),
            $data,
            $pkCriteria,
            static::$dbconnIdent,
            __FILE__,
            __LINE__
        );
        $this->setLastWriteAffected($this->getDb()->rows_affected);
        return $result;
    }
}
