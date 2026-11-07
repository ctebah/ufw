<?php
namespace Ufw\Tests\Support;

/**
 * Returns a non-Db instance from {@see instance()} to exercise {@see \Ufw\Db\Db::forApp()} guard.
 */
class BrokenDbInstanceProbe extends \Ufw\Db\Db
{
    /**
     * @return \stdClass
     */
    public static function instance()
    {
        return new \stdClass();
    }
}
