<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\Tests\Fixture\Record\WidgetPlain;

class DbFetchMethodsTest extends ComponentTestCase
{
    public function testFetchAllVariantsAndSimpleSelectHash()
    {
        $id = $this->insertWidget(array('name' => 'fetch-gate', 'code' => 'fg-' . bin2hex(random_bytes(2))));
        $db = Db::instance();

        $byName = $db->fetch_all(
            'SELECT id, name FROM widget WHERE id = ' . (int) $id,
            null,
            null,
            null,
            'name'
        );
        $this->assertSame(array('fetch-gate'), $byName);

        $hash = $db->simple_select('widget', array('id' => $id));
        $this->assertSame('fetch-gate', $hash->info[$id]->name);

        $row = $db->fetch_all(
            'SELECT id, name FROM widget WHERE id = ' . (int) $id,
            WidgetPlain::class,
            null,
            'id',
            'name'
        );
        $this->assertSame('fetch-gate', $row[$id]);
    }

    public function testSetConnectionRegistersCustomIdent()
    {
        $db = Db::instance();
        $db->set_connection('gate-local', '127.0.0.1', 'root', '', false, 'jep', false);
        $connections = (new \ReflectionProperty(Db::class, 'db_connections'));
        $connections->setAccessible(true);
        $all = $connections->getValue($db);
        $this->assertArrayHasKey('gate-local', $all);
    }

    public function testSimpleUpdateDeleteAndMagicRowCounts()
    {
        $id = $this->insertWidget(array('name' => 'before-update', 'code' => 'upd-' . bin2hex(random_bytes(2))));
        $db = Db::instance();

        $db->simple_update('widget', array('id' => $id, 'name' => 'after-update'));
        $this->assertGreaterThan(0, (int) $db->rows_affected);

        $result = $db->query('SELECT id, name FROM widget WHERE id = ' . (int) $id);
        $this->assertSame(1, (int) $db->num_rows);
        $row = $db->fetch_array($result, Db::DB_ASSOC);
        $this->assertSame('after-update', $row['name']);
        $db->free($result);

        $db->simple_delete('widget', array('id' => $id));
        $this->assertGreaterThan(0, (int) $db->rows_affected);
    }

    public function testThrowOnErrorRaisesQueryExceptionForSyntaxError()
    {
        $db = Db::instance();
        self::configureDbThrowOnError($db, true);
        try {
            $this->expectException(\Ufw\Db\QueryException::class);
            $db->query('SELEC 1');
        } finally {
            self::configureDbThrowOnError($db, false);
        }
    }

    public function testSimpleInsertUpdateAndSelectQueryHelpers()
    {
        $db = Db::instance();
        $code = 'siu-' . bin2hex(random_bytes(2));
        $this->assertNotFalse($db->simple_insert('widget', array(
            'name' => 'insert-update',
            'qty' => 1,
            'data' => '{}',
            'version' => 1,
            'code' => $code,
        )));
        $id = (int) $db->insert_id;
        $this->assertGreaterThan(0, $id);

        $this->assertNotFalse($db->simple_insert_update('widget', array(
            'id' => $id,
            'name' => 'upsert-name',
            'qty' => 2,
            'data' => '{}',
            'version' => 1,
            'code' => $code,
        )));

        $query = $db->simple_select_query('widget', array('code' => $code));
        $this->assertStringContainsString('widget', $query);
        $this->assertStringContainsString($code, $query);
    }

    public function testFetchAllIndexedObjectsWithoutOneProperty()
    {
        $id = $this->insertWidget(array('name' => 'full-row', 'code' => 'fr-' . bin2hex(random_bytes(2))));
        $db = Db::instance();
        $rows = $db->fetch_all(
            'SELECT id, name, code FROM widget WHERE id = ' . (int) $id,
            WidgetPlain::class,
            null,
            'id'
        );
        $this->assertArrayHasKey($id, $rows);
        $this->assertSame('full-row', $rows[$id]->name);
    }

    public function testQueryLogRecordsExecutedStatements()
    {
        $queries = $this->capturedQueries(function () {
            $this->insertWidget(array('name' => 'logged', 'code' => 'log-' . bin2hex(random_bytes(2))));
        });
        $this->assertNotEmpty($queries);
        $this->assertTrue((bool) preg_match('/insert/i', $queries[0]));
    }

    public function testDuplicateInsertErrorIsSilentWithoutThrowOnError()
    {
        $code = 'dup-' . bin2hex(random_bytes(3));
        $this->insertWidget(array('code' => $code, 'name' => 'first'));
        $db = Db::instance();
        self::configureDbThrowOnError($db, false);
        $result = $db->simple_insert('widget', array(
            'name' => 'dup',
            'qty' => 1,
            'data' => '{}',
            'version' => 1,
            'code' => $code,
        ));
        $this->assertFalse($result);
        $this->assertSame(1062, (int) $db->errno);
    }

    public function testQueryAcceptsLiveMysqliHandle()
    {
        $db = Db::instance();
        $handle = $db->connect('default');
        $this->assertInstanceOf(\mysqli::class, $handle);
        $result = $db->query('SELECT 1 AS ok', $handle);
        $row = $db->fetch_array($result);
        $this->assertSame(1, (int) $row['ok']);
        $db->free($result);
    }
}
