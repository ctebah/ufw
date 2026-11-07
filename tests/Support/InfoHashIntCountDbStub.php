<?php
namespace Ufw\Tests\Support;

/**
 * Minimal Db double for InfoHash::getTotalCount() integer branch.
 */
class InfoHashIntCountDbStub
{
    /**
     * @return self
     */
    public static function instance()
    {
        return new self();
    }

    /**
     * @return list<int>
     */
    public function fetch_all($query, $class = null, $conn = null, $index = null, $column = null, $file = null, $line = null)
    {
        return array(42);
    }
}
