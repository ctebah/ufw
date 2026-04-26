<?php
namespace Ufw\Model\Behaviour;

/**
 * After insert, sets the single string primary key from {@see \Ufw\Db\Db::$insert_id} when the PK is one scalar column.
 */
class InsertId extends Base
{
    public function afterInsert($success, $opts = [])
    {
        if (!$success || !$this->getOwner()->getIsNewRecord()) {
            return $this;
        }
        $pk = $this->getOwner()->getPrimaryKeyField();
        if (is_array($pk) && (count($pk) == 1)) {
            $pk = reset($pk);
        }
        if (is_string($pk)) {
            $this->getOwner()->$pk = $this->getOwner()->getDb()->insert_id;
        }
        
        return $this;
    }
}
