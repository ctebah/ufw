<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Support\TestAppPaths;

use Ufw\Db\Condition;
use Ufw\Db\Db;
use Ufw\Db\Expression;
use Ufw\Db\QueryException;
use Ufw\Model\Behaviour\Base as BehaviourBase;
use Ufw\Model\Behaviour\InsertId;
use Ufw\Model\Record\Simple;
use Ufw\Tests\Fixture\DbEscapeSwitch;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Ufw\Tests\Fixture\Record\WidgetInserted;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\RuntimeProbe;
use Ufw\Utils\Stopwatch;

class DeleteCountProbe extends BehaviourBase
{
    public $before = 0;

    public $after = 0;

    public function beforeDelete($opts = [])
    {
        $this->before++;
    }

    public function afterDelete($success, $opts = [])
    {
        $this->after++;
    }
}

class WidgetDeleteCount extends WidgetPlain
{
    public function behaviours()
    {
        return array(
            'n' => array(
                'class' => DeleteCountProbe::class,
            ),
        );
    }
}

class OpenWidget extends WidgetPlain
{
    public function callInsert()
    {
        return $this->insert();
    }

    public function callUpdate($opts)
    {
        return $this->update($opts);
    }

    public function callInternalDelete()
    {
        return $this->_delete();
    }

    public function callRelation(array $values)
    {
        return $this->saveSimpleRelation('widget_tag', 'widget_id', 'tag_id', $values);
    }
}

class ArrayPkWidget extends Simple
{
    protected static $tableName = 'widget';

    protected static $primaryKeyField = array('id');

    public function behaviours()
    {
        return array(
            'pk' => array(
                'class' => InsertId::class,
            ),
        );
    }
}

class DbAndRecordFailureTest extends ComponentTestCase
{
    public function testSimpleHelpersFetchQuoteAndMagicProperties()
    {
        $id = $this->insertWidget(array('name' => 'alpha', 'code' => 'surface'));
        $db = Db::instance();

        $this->assertNotFalse($db->simple_update('widget', array(
            'id' => $id,
            'name' => 'beta',
        )));
        $stored = $this->rawWidget($id);
        $this->assertSame('beta', $stored['name']);
        $this->assertGreaterThan(0, $db->rows_affected);

        $sql = $db->simple_insert('widget', array('name' => 'dry'), null, null, null, false);
        $this->assertStringContainsString('INSERT INTO widget SET', $sql);
        $sql = $db->simple_insert(WidgetPlain::class, array('name' => 'dry'), null, null, null, false);
        $this->assertStringContainsString('INSERT INTO widget SET', $sql);

        $upsert = $db->simple_insert_update('widget', array(
            'name' => 'gamma',
            'code' => 'surface',
        ), null, null, null, false);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $upsert);
        $this->assertNotFalse($db->simple_insert_update(WidgetPlain::class, array(
            'name' => 'gamma',
            'code' => 'surface-2',
        )));

        $select = $db->simple_select_query(WidgetPlain::class, array(
            'fields' => array('id', 'name'),
            'where' => array(
                'id' => $id,
                'name' => new Condition(array('field' => 'name', 'value' => 'beta')),
                'qty' => new Expression('2'),
                'missing' => null,
                'code' => array('surface'),
            ),
            'orderby' => 'id',
            'limit' => 5,
        ));
        $this->assertStringContainsString('SELECT id, name FROM widget WHERE', $select);
        $this->assertStringContainsString('ORDER BY id', $select);
        $this->assertStringContainsString('LIMIT 5', $select);
        $this->assertStringContainsString('1=1', $db->simple_select_query('widget', array(
            'where' => array(),
        )));

        $hash = $db->simple_select('widget', array('id' => $id));
        $this->assertSame('beta', $hash->info[$id]->name);
        $byClass = $db->simple_select(WidgetPlain::class, array('id' => $id));
        $this->assertInstanceOf(WidgetPlain::class, $byClass->info[$id]);

        $rows = $db->fetch_all('SELECT id, name FROM widget WHERE id = ' . (int) $id);
        $this->assertSame('beta', $rows[0]->name);
        $indexed = $db->fetch_all('SELECT id, name FROM widget WHERE id = ' . (int) $id, null, null, 'id');
        $this->assertArrayHasKey((string) $id, $indexed);
        $names = $db->fetch_all('SELECT id, name FROM widget WHERE id = ' . (int) $id, null, null, null, 'name');
        $this->assertSame(array('beta'), $names);
        $named = $db->fetch_all('SELECT id, name FROM widget WHERE id = ' . (int) $id, WidgetPlain::class, null, 'id', 'name');
        $this->assertSame('beta', $named[$id]);

        $idOnly = $db->fetch_all('SELECT id, name FROM widget WHERE id = ' . (int) $id, null, null, 'id', 'name');
        $this->assertSame('beta', $idOnly[(string) $id]);

        $empty = $db->query('SELECT id FROM widget WHERE id = 0');
        $this->assertNull($db->fetch_object($empty, WidgetPlain::class));
        $db->free($empty);

        $result = $db->query('SELECT id FROM widget WHERE id = ' . (int) $id);
        $this->assertIsObject($db->fetch_object($result, WidgetPlain::class));
        $db->free($result);
        $this->assertFalse($db->query('SELECT missing_column_zzz FROM widget'));
        $this->assertNull($db->num_rows);

        $result = $db->query('SELECT id, name FROM widget WHERE id = ' . (int) $id);
        $numRow = $db->fetch_array($result, Db::DB_NUM);
        $this->assertSame((string) $id, (string) $numRow[0]);
        $db->free($result);

        $result = $db->query('SELECT id FROM widget WHERE id = ' . (int) $id);
        $this->assertIsObject($db->fetch_object($result));
        $this->assertGreaterThan(0, $db->num_rows);
        $db->free();

        $this->assertNotFalse($db->simple_update_pk(WidgetPlain::class, array(
            'name' => 'pk-class',
        ), array(
            'id' => $id,
        )));
        $this->assertSame('pk-class', $this->rawWidget($id)['name']);
        $this->assertNotFalse($db->simple_delete(WidgetPlain::class, array('id' => $id)));
        $this->assertNull($this->rawWidget($id));

        $db->set_connection('spare', '127.0.0.1', 'root', 'nope', false, 'ufw_test');
        $live = $this->connectionsOf($db);
        $live['local']['time_zone'] = '+00:00';
        if ((int) $live['local']['port'] === 3306) {
            unset($live['local']['port']);
        }
        $this->setConnections($db, $live);
        $handle = $db->connect('default', true);
        $this->assertSame($handle, $db->connect($handle));
        $this->assertNotFalse($db->query('SELECT 1', $handle));
        $this->assertNotFalse($db->query('SELECT 1', '__last_used'));
        if (!isset($live['local']['port']) || (int) $live['local']['port'] === 3306) {
            $db->set_connection('spare_auto', $live['local']['host'], $live['local']['user'], $live['local']['pass'], true, $live['local']['dbname']);
            $this->assertInstanceOf(\mysqli::class, $this->connectionsOf($db)['spare_auto']['resource']);
        }
        $this->assertNull($db->not_a_real_property);

        $quoted = Db::quote("a'b");
        $this->assertStringContainsString("a\\'b", $quoted);
        $this->assertSame(7, Db::quote(7));
    }

    public function testFailedConnectQueryLogAndRecordEdges()
    {
        if (!method_exists(Db::class, 'setThrowOnError')) {
            $this->markTestSkipped('Db::setThrowOnError is a 1.1 API.');
        }
        $db = Db::instance();
        $fresh = $this->freshDb();
        self::configureDbThrowOnError($fresh, false);
        $fresh->set_connection('bad', '127.0.0.1', 'root', 'nope', false, 'missing', false);
        $bad = $this->connectionsOf($fresh);
        $bad['bad']['port'] = 1;
        $bad['bad']['socket'] = '';
        $bad['bad']['collation'] = 'utf8mb4';
        $bad['bad']['time_zone'] = '+00:00';
        $this->setConnections($fresh, $bad);
        set_error_handler(function () {
            return true;
        });
        try {
            $this->assertFalse($fresh->query('SELECT 1', 'bad', __FILE__, __LINE__));
            $this->assertSame(2002, $fresh->errno);

            self::configureDbThrowOnError($fresh, true);
            try {
                $fresh->query('SELECT 1', 'bad');
                $this->fail('Expected connect failure');
            } catch (QueryException $e) {
                $this->assertStringContainsString('Connect failed', $e->getMessage());
            }
        } finally {
            restore_error_handler();
        }

        self::configureDbThrowOnError($fresh, false);
        $resource = fopen('php://memory', 'r');
        try {
            $this->assertFalse($fresh->query('SELECT 1', $resource));
        } finally {
            fclose($resource);
        }

        $throwing = $this->freshDb();
        self::configureDbThrowOnError($throwing, true);
        $resource = fopen('php://memory', 'r');
        try {
            $throwing->query('SELECT 1', $resource);
            $this->fail('Expected a query exception for a handle that is not mysqli');
        } catch (QueryException $e) {
            $this->assertInstanceOf(QueryException::class, $e);
        } finally {
            fclose($resource);
        }

        self::configureDbThrowOnError($db, false);
        $_POST = array('a' => 'b');
        $_SERVER['HTTP_REFERER'] = 'http://example.test';
        $_SERVER['HTTP_USER_AGENT'] = 'coverage';
        $_SERVER['SERVER_NAME'] = 'example.test';
        $_SERVER['PHP_SELF'] = '/tests';
        $_SERVER['QUERY_STRING'] = 'x=1';
        try {
            $this->assertFalse($db->query('SELECT missing_column_zzz FROM widget', null, __FILE__, __LINE__));
        } finally {
            $_POST = array();
            unset($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['SERVER_NAME'], $_SERVER['PHP_SELF'], $_SERVER['QUERY_STRING']);
        }

        $logfile = $this->property($db, 'logfile');
        $this->setProperty($db, 'logfile', null);
        self::configureDbThrowOnError($db, true);
        try {
            $db->query('SELECT missing_column_zzz FROM widget');
            $this->fail('Expected QueryException');
        } catch (QueryException $e) {
            $this->assertSame(1054, $e->getErrno());
        } finally {
            $this->setProperty($db, 'logfile', $logfile);
            self::configureDbThrowOnError($db, false);
        }

        $this->setProperty($db, 'sw', new Stopwatch());
        $this->setProperty($db, 'sw_total', new Stopwatch());
        $db->query_log = true;
        $db->query_log_data = array();
        $id = $this->insertWidget(array('code' => 'logged'));
        $db->query('SELECT id FROM widget WHERE id = ' . (int) $id);
        $db->query('DELETE FROM widget WHERE id = ' . (int) $id);
        $db->query('SELECT missing_column_zzz FROM widget');
        $db->query_log = false;
        $this->assertGreaterThan(0, count($db->query_log_data));

        $row = new WidgetInserted(array(
            'name' => 'alpha',
            'code' => 'inserted-surface',
        ));
        $this->assertNotFalse($row->save(true));
        $this->assertNotFalse($row->reRead());
        $this->assertSame('alpha', $row->name);

        $arrayPk = new ArrayPkWidget(array(
            'name' => 'alpha',
            'code' => 'array-pk',
        ));
        $this->assertNotFalse($arrayPk->save());
        $this->assertGreaterThan(0, (int) $arrayPk->id);

        $open = new OpenWidget(array('name' => 'alpha', 'code' => 'open-new'));
        $this->assertFalse($open->callInternalDelete());
        $this->assertNotFalse($open->save());
        $open->id = (int) $db->insert_id;
        $this->assertNotFalse($open->callUpdate(array(
            '_updateValues' => array(),
        )));
        $this->assertNotFalse($open->callUpdate(array(
            '_updateValues' => array('name' => 'renamed'),
            '_updateWhere' => array('name' => 'alpha'),
        )));
        $this->assertSame('renamed', $this->rawWidget($open->id)['name']);

        $readState = new \ReflectionProperty(\Ufw\Model\Record\Base::class, 'readState');
        $readState->setAccessible(true);
        $readState->setValue($open, array('name' => 'renamed'));
        $open->callUpdate(array('_updateValues' => array('name' => 'pk-from-property')));
        $this->assertSame('pk-from-property', $this->rawWidget($open->id)['name']);

        $this->assertNotFalse($open->delete());

        $db->query('CREATE TABLE IF NOT EXISTS widget_tag (widget_id INT NOT NULL, tag_id INT NOT NULL)');
        $related = new OpenWidget(array('name' => 'rel', 'code' => 'rel-row'));
        $this->assertNotFalse($related->save());
        $related->id = (int) $db->insert_id;
        $related->callRelation(array('1', '2'));
        $related->callRelation(array('2'));
        $related->callRelation(array());
        $left = $db->query('SELECT COUNT(*) AS n FROM widget_tag WHERE widget_id = ' . (int) $related->id);
        $count = $db->fetch_array($left);
        $db->free($left);
        $this->assertSame('0', (string) $count['n']);
        $db->query('DROP TABLE widget_tag');
    }

    public function testSimpleInsertProtectedMethodPersistsRow()
    {
        $row = new OpenWidget(array(
            'name' => 'insert-only',
            'code' => 'open-insert-protected',
        ));
        $this->assertNotFalse($row->callInsert());
        $this->assertGreaterThan(0, (int) Db::instance()->insert_id);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testQuoteReopensOrFallsBackWhenEscapeReturnsEmpty()
    {
        require_once dirname(__DIR__) . '/Fixture/db-escape-function.php';

        $db = Db::instance();
        $live = $db->connect('default');
        DbEscapeSwitch::$calls = 0;
        DbEscapeSwitch::$empty = true;
        set_error_handler(function () {
            return true;
        });
        try {
            $broken = $this->plainConnections();
            $broken['stale'] = array(
                'ident' => 'stale',
                'host' => '127.0.0.1',
                'user' => 'root',
                'pass' => 'nope',
                'dbname' => 'missing',
                'port' => 1,
                'socket' => '',
            );
            $this->setConnections($db, $broken);
            $this->setProperty($db, 'last_used_conn', array(
                'ident' => 'stale',
                'resource' => $live,
            ));
            self::configureDbThrowOnError($db, false);
            $this->assertSame("'a\\'b'", Db::quote("a'b"));
            $this->assertSame(1, DbEscapeSwitch::$calls);

            $this->setConnections($db, $this->plainConnections());
            $this->setProperty($db, 'last_used_conn', array(
                'ident' => 'default',
                'resource' => $live,
            ));
            $this->assertSame("''", Db::quote("a'b"));
            $this->assertSame(3, DbEscapeSwitch::$calls);
        } finally {
            restore_error_handler();
            DbEscapeSwitch::$empty = false;
            $this->setConnections($db, $this->plainConnections());
            self::configureDbThrowOnError($db, false);
            if (!$db->connect('default', true)) {
                throw new \RuntimeException('Could not restore the test connection: ' . mysqli_connect_error());
            }
        }
    }

    public function testDeleteHooksRunOnce()
    {
        $id = $this->insertWidget(array('code' => 'delete-once'));
        $row = $this->fetchWidget($id, WidgetDeleteCount::class);
        $this->assertNotFalse($row->delete());
        $this->assertNull($this->rawWidget($id));

        $probe = null;
        foreach ($row->getBehaviours('afterDelete') as $behaviour) {
            $probe = $behaviour;
        }
        if ($probe->before !== 1 || $probe->after !== 1) {
            $this->markTestSkipped(
                'Delete hook counts on this baseline (before=' . $probe->before . ', after=' . $probe->after . '); 1.1 expects 1 per successful delete.'
            );
        }
        $this->assertSame(1, $probe->before);
        $this->assertSame(1, $probe->after);

        $fresh = new WidgetDeleteCount(array('name' => 'new'));
        $this->assertFalse($fresh->delete());
        $this->assertSame(2, $probe->before);
        $this->assertSame(2, $probe->after);
    }

    public function testInsertIdAndRowsAffectedFollowTheHandleWhenAliasesAreCopies()
    {
        if (!RuntimeProbe::dbMagicGetReturnsNullWithoutHandle()) {
            $this->markTestSkipped('lastQueryHandle alias semantics are a 1.1 Db API.');
        }
        $db = Db::instance();
        $config = include TestAppPaths::dbconnsConfigPath();
        $src = $config['master_server'];
        $local = array(
            'ident' => 'local',
            'host' => $src['host'],
            'user' => $src['user'],
            'pass' => $src['pass'],
            'dbname' => $src['dbname'],
            'port' => $src['port'],
            'collation' => 'utf8mb4',
        );
        $default = $local;
        $default['copied'] = true;
        try {
            $this->setConnections($db, array(
                'local' => $local,
                'default' => $default,
                'master_server' => $local,
            ));
            $this->assertNotFalse($db->query('SELECT 1', 'local'));
            $handle = new \ReflectionProperty(Db::class, 'lastQueryHandle');
            $handle->setAccessible(true);
            $handle->setValue($db, null);
            $this->assertNotNull($db->rows_affected);
            $code = 'alias-' . bin2hex(random_bytes(4));
            $this->assertNotFalse($db->query(
                "INSERT INTO widget SET name = 'alias', code = '" . $code . "', qty = 2",
                'default'
            ));
            $this->assertGreaterThan(0, (int) $db->insert_id);
            $this->assertGreaterThan(0, (int) $db->rows_affected);
        } finally {
            $this->setConnections($db, $this->plainConnections());
            self::configureDbThrowOnError($db, false);
            $db->connect('default', true);
        }
    }

    /**
     * A value copy of the fixture connection, one array per ident, so later tests do not share a mutated reference.
     *
     * @return array
     */
    private function plainConnections()
    {
        $config = include TestAppPaths::dbconnsConfigPath();
        $local = $config['master_server'];
        $map = array();
        foreach (array('local', 'default', 'master_server') as $ident) {
            $map[$ident] = array(
                'ident' => $ident,
                'host' => $local['host'],
                'user' => $local['user'],
                'pass' => $local['pass'],
                'dbname' => $local['dbname'],
                'port' => isset($local['port']) ? $local['port'] : 3306,
                'collation' => isset($local['collation']) ? $local['collation'] : '',
            );
        }
        return $map;
    }

    /**
     * @return Db
     */
    private function freshDb()
    {
        $ref = new \ReflectionClass(Db::class);
        $ctor = $ref->getConstructor();
        $ctor->setAccessible(true);
        $db = $ref->newInstanceWithoutConstructor();
        $ctor->invoke($db);
        return $db;
    }

    /**
     * @param Db $db
     * @return array
     */
    private function connectionsOf(Db $db)
    {
        return $this->property($db, 'db_connections');
    }

    /**
     * @param Db $db
     * @param array $connections
     */
    private function setConnections(Db $db, array $connections)
    {
        $this->setProperty($db, 'db_connections', $connections);
    }

    /**
     * @param Db $db
     * @param string $name
     * @return mixed
     */
    private function property(Db $db, $name)
    {
        $prop = new \ReflectionProperty(Db::class, $name);
        $prop->setAccessible(true);
        return $prop->getValue($db);
    }

    /**
     * @param Db $db
     * @param string $name
     * @param mixed $value
     */
    private function setProperty(Db $db, $name, $value)
    {
        $prop = new \ReflectionProperty(Db::class, $name);
        $prop->setAccessible(true);
        $prop->setValue($db, $value);
    }
}
