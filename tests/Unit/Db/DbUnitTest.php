<?php
namespace Ufw\Tests\Unit\Db;

use PHPUnit\Framework\TestCase;
use Ufw\Db\Db;
use Ufw\Db\Expression;

class DbUnitTest extends TestCase
{
    public function testQuoteScalarsArraysAndExpression()
    {
        $this->assertSame(7, Db::quote(7));
        $this->assertSame('NULL', Db::quote(null));
        $this->assertSame(0, Db::quote(false));
        $this->assertSame(1, Db::quote(true));
        $quoted = Db::quote(array('a', 'b'));
        $this->assertIsArray($quoted);
        $this->assertCount(2, $quoted);

        $expr = new Expression('NOW()');
        $this->assertSame('NOW()', Db::quote($expr));

        $sql = Db::subquery(array('id' => array(1, 2)), ', ', true);
        $this->assertStringContainsString('IN', $sql);
        $this->assertStringContainsString('IS NULL', Db::subquery(array('deleted' => null), ' AND ', true));
    }

    public function testSetLogFileRejectsBooleanTrue()
    {
        $db = Db::instance();
        $this->expectException(\LogicException::class);
        $db->setLogFile(true);
    }

    public function testSetLogFileFalseClearsPathAndSetThrowOnError()
    {
        $db = Db::instance();
        $db->setLogFile('')->setLogPost(false)->setThrowOnError(true);
        $logFile = new \ReflectionProperty(Db::class, 'logfile');
        $logFile->setAccessible(true);
        $this->assertNull($logFile->getValue($db));
        $this->assertTrue($db->getMysqliDriverReportMode() >= 0);
    }

    public function testQuoteNonStringScalarLikeObjectReturnsNull()
    {
        $quoted = Db::quote(new \stdClass());
        $this->assertSame('NULL', $quoted);
    }

    public function testNormalizeAssocRowDropsNumericKeys()
    {
        $method = new \ReflectionMethod(Db::class, 'normalizeAssocRow');
        $method->setAccessible(true);
        $out = $method->invoke(null, array('id' => 1, 0 => 'skip', 'name' => 'x'));
        $this->assertSame(array('id' => 1, 'name' => 'x'), $out);
    }

    public function testNormalizeDbConnectionsSkipsInvalidEntries()
    {
        $db = Db::instance();
        $method = new \ReflectionMethod(Db::class, 'normalizeDbConnections');
        $method->setAccessible(true);
        $out = $method->invoke($db, array(
            'ok' => array('ident' => 'ok', 'host' => 'h'),
            0 => array('ident' => 'bad'),
            'nope' => 'string',
        ));
        $this->assertArrayHasKey('ok', $out);
        $this->assertArrayNotHasKey(0, $out);
    }
}
