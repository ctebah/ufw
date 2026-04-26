<?php
namespace Ufw\Model\Record;

/**
 * Base for records that must not be persisted (views, snapshots). On PHP 8.1+ the name cannot be
 * `ReadOnly` because `readonly` is a reserved keyword.
 */
abstract class ReadOnlyRecord extends Base
{

    final public function delete($opts = [])
    {
        throw new \Exception('Read-only record');
    }

    final public function save($withRelationsData = false, $opts = [])
    {
        throw new \Exception('Read-only record');
    }

    final protected function _delete($opts = [])
    {
        throw new \Exception('Read-only record');
    }

    final protected function insert($opts = [])
    {
        throw new \Exception('Read-only record');
    }

    final protected function update($opts = [])
    {
        throw new \Exception('Read-only record');
    }
}
