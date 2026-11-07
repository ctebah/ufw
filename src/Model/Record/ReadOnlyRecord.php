<?php
namespace Ufw\Model\Record;

/**
 * Base for records that must not be persisted (views, snapshots). On PHP 8.1+ the name cannot be
 * `ReadOnly` because `readonly` is a reserved keyword.
 */
abstract class ReadOnlyRecord extends Base
{

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    final public function delete($opts = [])
    {
        throw new \Exception('Read-only record');
    }

    /**
     * @param bool $withRelationsData
     * @param array<string, mixed> $opts
     * @return mixed
     */
    final public function save($withRelationsData = false, $opts = [])
    {
        throw new \Exception('Read-only record');
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    final protected function _delete($opts = [])
    {
        throw new \Exception('Read-only record');
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    final protected function insert($opts = [])
    {
        throw new \Exception('Read-only record');
    }

    /**
     * @param array<string, mixed> $opts
     * @return mixed
     */
    final protected function update($opts = [])
    {
        throw new \Exception('Read-only record');
    }
}
