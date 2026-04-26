<?php
namespace Ufw\Model\Behaviour;

/**
 * After a successful save, re-selects the row by primary key and calls {@see \Ufw\Model\Record\Base::readRelationsData()}
 * so defaults/triggers from the DB are visible on the in-memory record.
 */
class ReadAfterSave extends Base
{

    public function afterSave($success, $opts = [])
    {
        if (!$success || !$this->getOwner()->getPrimaryKeyField()) {
            return $this;
        }
        
        $pk = $this->getOwner()->getPrimaryKeyField();
        if (!is_array($pk)) {
            $pk = [
                $pk
            ];
        }
        $dbc = $this->getOwner()->getDb();
        $query = $dbc->simple_select_query($this->getOwner()
            ->getTableName(), array_intersect_key($this->getOwner()
            ->getAll(), array_flip($pk)));
        $data = $dbc->fetch_array($dbc->query($query, $this->getOwner()->getDbconnIdent(), __FILE__, __LINE__));
        $dbc->free();
        $this->getOwner()->setAll($data);
        
        $this->getOwner()->readRelationsData();
        
        return $this;
    }
}
