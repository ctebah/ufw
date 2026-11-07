<?php
namespace Ufw\Tests\Unit\Db;

use PHPUnit\Framework\TestCase;
use Ufw\Db\QueryException;

class DbLayerTest extends TestCase
{
    public function testQueryExceptionExposesQueryContext()
    {
        $ex = new QueryException('syntax error', 1064, 'SELECT 1', 'default');
        $this->assertSame(1064, $ex->getErrno());
        $this->assertSame('SELECT 1', $ex->getQuery());
        $this->assertSame('default', $ex->getConnIdent());
        $this->assertSame('syntax error', $ex->getMessage());
    }
}
