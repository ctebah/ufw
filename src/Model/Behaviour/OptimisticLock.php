<?php
namespace Ufw\Model\Behaviour;

use Ufw\Model\Exception\StaleRecord;

/**
 * Rejects an update when the version column no longer matches the value from the read snapshot.
 * The token is not re-read from the database. Without a snapshot, the value already on the record is used
 * (typically a hidden form field carrying the earlier read).
 *
 * After a successful update the row is read back, so the record and its snapshot carry the new version
 * and a second save of the same object does not look stale. Skipped when the record already declares
 * {@see ReadAfterSave}. The read happens in afterUpdate so {@see Json::afterSave} still decodes it.
 *
 * Default order is 150 so it runs after {@see ChangedOnly} (140) and can append version to that SET list.
 */
class OptimisticLock extends Base
{
    public $order = 150;

    /**
     * @var string
     */
    public $versionField = 'version';

    /**
     * @param array<string, mixed> $opts
     * @return $this
     */
    public function beforeInsert($opts = [])
    {
        $field = $this->versionField;
        $data = $this->getOwner()->getAll();
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            $this->getOwner()->$field = 1;
        }
        return $this;
    }

    /**
     * @param array<string, mixed> $opts
     * @return $this
     */
    public function beforeUpdate(&$opts = [])
    {
        $field = $this->versionField;
        $expected = $this->expectedVersion($field);
        if ($expected instanceof \Ufw\Db\Expression || is_array($expected)) {
            throw new \LogicException('Optimistic lock column "' . $field . '" must be a scalar from the read, not an expression or array');
        }
        if ($expected === null || $expected === '') {
            throw new \LogicException('Optimistic lock column "' . $field . '" has no value from the read snapshot or the record');
        }
        if (!isset($opts['_updateWhere']) || !is_array($opts['_updateWhere'])) {
            $opts['_updateWhere'] = array();
        }
        $opts['_updateWhere'][$field] = $expected;

        if (array_key_exists('_updateValues', $opts) && $opts['_updateValues'] === array()) {
            return $this;
        }
        $expr = new \Ufw\Db\Expression($field . ' + 1');
        if (array_key_exists('_updateValues', $opts) && is_array($opts['_updateValues'])) {
            $opts['_updateValues'][$field] = $expr;
            return $this;
        }
        $data = $this->getOwner()->getAll();
        $data[$field] = $expr;
        $opts['_updateValues'] = $data;
        return $this;
    }

    /**
     * @param mixed $success
     * @param array<string, mixed> $opts
     * @return $this
     */
    public function afterUpdate($success, $opts = [])
    {
        if (!$success) {
            return $this;
        }
        $affected = $this->getOwner()->getLastWriteAffected();
        if ($affected === 0 || $affected === '0') {
            throw new StaleRecord('Row changed since it was read');
        }
        if ($affected !== null && !$this->ownerReadsAfterSave()) {
            $this->getOwner()->reRead();
        }
        return $this;
    }

    /**
     * @return bool
     */
    protected function ownerReadsAfterSave()
    {
        foreach ($this->getOwner()->getBehaviours('afterSave') as $behaviour) {
            if ($behaviour instanceof ReadAfterSave) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $field
     * @return mixed
     */
    protected function expectedVersion($field)
    {
        $state = $this->getOwner()->getReadState();
        if (is_array($state) && array_key_exists($field, $state)) {
            return $state[$field];
        }
        $data = $this->getOwner()->getAll();
        if (array_key_exists($field, $data)) {
            return $data[$field];
        }
        return null;
    }
}
