<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Support\TestAppPaths;

use Ufw\Db\Db;
use Ufw\Db\QueryException;

class QueryErrorTest extends ComponentTestCase
{
    public function testDuplicateKeyStaysFalseUntilThrowingIsEnabled()
    {
        $this->insertWidget(array('code' => 'dup'));
        $db = Db::instance();
        $db->setThrowOnError(false);
        $this->assertFalse($db->simple_insert('widget', array(
            'name' => 'x',
            'code' => 'dup',
        )));
        $this->assertSame(1062, (int) $db->errno);

        $db->setThrowOnError(true);
        try {
            $db->simple_insert('widget', array(
                'name' => 'y',
                'code' => 'dup',
            ));
            $this->fail('Expected QueryException');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->getErrno());
        }
    }

    public function testBadSqlStaysFalseUntilThrowingIsEnabled()
    {
        $db = Db::instance();
        $db->setThrowOnError(false);
        $this->assertFalse($db->query('SELECT missing_column_zzz FROM widget'));
        $this->assertSame(1054, (int) $db->errno);

        $db->setThrowOnError(true);
        try {
            $db->query('SELECT missing_column_zzz FROM widget');
            $this->fail('Expected QueryException');
        } catch (QueryException $e) {
            $this->assertSame(1054, $e->getErrno());
        }
    }

    public function testAKilledConnectionIsRetriedOnce()
    {
        $db = Db::instance();
        $db->setThrowOnError(false);
        $this->assertNotFalse($db->query('SELECT 1', 'default'));
        $idResult = $db->query('SELECT CONNECTION_ID() AS cid', 'default');
        $cid = $db->fetch_array($idResult);
        $db->free($idResult);

        $config = include TestAppPaths::dbconnsConfigPath();
        $cn = $config['master_server'];
        $killer = mysqli_connect($cn['host'], $cn['user'], $cn['pass'], $cn['dbname'], (int) $cn['port']);
        $this->assertInstanceOf(\mysqli::class, $killer);
        $this->assertTrue($killer->query('KILL ' . (int) $cid['cid']));
        $killer->close();

        $this->assertNotFalse($db->query('SELECT 1 AS ok', 'default'));
        $this->assertSame(0, (int) $db->errno);
        $after = $db->query('SELECT CONNECTION_ID() AS cid', 'default');
        $newId = $db->fetch_array($after);
        $db->free($after);
        $this->assertNotSame((string) $cid['cid'], (string) $newId['cid']);
    }

    public function testAKilledConnectionIsRetriedWhenTheQueryOmitsTheConnection()
    {
        $db = Db::instance();
        $db->setThrowOnError(false);
        $this->assertNotFalse($db->query('SELECT 1', 'default'));
        $idResult = $db->query('SELECT CONNECTION_ID() AS cid');
        $cid = $db->fetch_array($idResult);
        $db->free($idResult);

        $config = include TestAppPaths::dbconnsConfigPath();
        $cn = $config['master_server'];
        $killer = mysqli_connect($cn['host'], $cn['user'], $cn['pass'], $cn['dbname'], (int) $cn['port']);
        $this->assertInstanceOf(\mysqli::class, $killer);
        $this->assertTrue($killer->query('KILL ' . (int) $cid['cid']));
        $killer->close();

        $this->assertNotFalse($db->query('SELECT 1 AS ok'));
        $after = $db->query('SELECT CONNECTION_ID() AS cid');
        $newId = $db->fetch_array($after);
        $db->free($after);
        $this->assertNotSame((string) $cid['cid'], (string) $newId['cid']);
    }
}
