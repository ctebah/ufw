<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Support\TestAppPaths;

use Ufw\Application;
use Ufw\Db\Db;
use Ufw\Db\QueryException;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Tests\Unit\ConfiguredDbProbe;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Utils\Stopwatch;

class DbComponentExtrasTest extends ComponentTestCase
{
    public function testDirectConnectFailureThrowsWhenThrowOnErrorEnabled()
    {
        if (!method_exists(Db::class, 'setThrowOnError')) {
            $this->markTestSkipped('Db::setThrowOnError is a 1.1 API.');
        }
        $fresh = $this->freshDb();
        self::configureDbThrowOnError($fresh, true);
        $config = include TestAppPaths::dbconnsConfigPath();
        $cn = $config['master_server'];
        $fresh->set_connection('bad', $cn['host'], $cn['user'], $cn['pass'], false, $cn['dbname'], false);
        $bad = $this->connectionsOf($fresh);
        $bad['bad']['port'] = 1;
        $bad['bad']['socket'] = '';
        $this->setConnections($fresh, $bad);
        set_error_handler(function () {
            return true;
        });
        try {
            $fresh->connect('bad');
            $this->fail('Expected QueryException from connect()');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Connect failed', $e->getMessage());
        } finally {
            restore_error_handler();
            self::configureDbThrowOnError($fresh, false);
        }
    }

    public function testConnectEmptyIdentReturnsFalse()
    {
        $db = Db::instance();
        $this->assertFalse($db->connect(''));
    }

    public function testConnectReusesOpenMysqliWithoutReconnect()
    {
        $db = Db::instance();
        $first = $db->connect('default');
        $this->assertInstanceOf(\mysqli::class, $first);
        $second = $db->connect($first);
        $this->assertSame($first, $second);
    }

    public function testConnectAppliesCollationAndSessionTimeZone()
    {
        $fresh = $this->freshDb();
        $config = include TestAppPaths::dbconnsConfigPath();
        $cn = $config['master_server'];
        $fresh->set_connection('session-tz', $cn['host'], $cn['user'], $cn['pass'], false, $cn['dbname'], false);
        $map = $this->connectionsOf($fresh);
        $map['session-tz']['port'] = isset($cn['port']) ? $cn['port'] : 3306;
        $map['session-tz']['collation'] = 'utf8mb4';
        $map['session-tz']['time_zone'] = '+00:00';
        $this->setConnections($fresh, $map);
        $this->assertInstanceOf(\mysqli::class, $fresh->connect('session-tz'));
        $result = $fresh->query('SELECT @@session.time_zone AS tz, @@collation_connection AS coll');
        $row = $fresh->fetch_array($result);
        $fresh->free($result);
        $this->assertNotEmpty($row['tz']);
        $this->assertStringContainsString('utf8mb4', (string) $row['coll']);
    }

    public function testFetchArrayDbBothReturnsAssocAndNumericKeys()
    {
        $id = $this->insertWidget(array('name' => 'both-mode', 'code' => 'bm-' . bin2hex(random_bytes(2))));
        $db = Db::instance();
        $result = $db->query('SELECT id, name FROM widget WHERE id = ' . (int) $id);
        $row = $db->fetch_array($result, Db::DB_BOTH);
        $db->free($result);
        $this->assertSame($id, (int) $row['id']);
        $this->assertSame('both-mode', $row['name']);
        $this->assertSame($id, (int) $row[0]);
        $this->assertSame('both-mode', $row[1]);
    }

    public function testQueryLogTableRendersCapturedRowsWhenNotAjax()
    {
        $db = Db::instance();
        $db->query_log_data = array(
            array(
                'query' => 'SELECT 1',
                'rows' => 1,
                'file' => 'trace',
                'line' => 10,
                'time' => '0.1',
                'total' => '1.0',
            ),
        );
        $saved = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : null;
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        ob_start();
        try {
            Db::query_log_table();
            $html = ob_get_clean();
            $this->assertStringContainsString('query-log-table', $html);
            $this->assertStringContainsString('SELECT 1', $html);
        } finally {
            if ($saved !== null) {
                $_SERVER['HTTP_X_REQUESTED_WITH'] = $saved;
            }
            $db->query_log_data = array();
        }
    }

    public function testFailedQueryWritesToConfiguredLogFileWithoutPostBody()
    {
        $db = Db::instance();
        $tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-db-gate-' . bin2hex(random_bytes(4)) . '.log';
        $logfile = $this->property($db, 'logfile');
        $logPost = $this->property($db, 'logPost');
        $this->setProperty($db, 'logfile', $tmpdir);
        $db->setLogPost(false);
        $_POST = array('secret' => 'value');
        try {
            $this->assertFalse($db->query('SELECT missing_gate_column FROM widget', null, __FILE__, __LINE__));
            $this->assertFileExists($tmpdir);
            $contents = file_get_contents($tmpdir);
            $this->assertStringContainsString('missing_gate_column', $contents);
            $this->assertStringNotContainsString('secret=value', $contents);
        } finally {
            $_POST = array();
            $this->setProperty($db, 'logfile', $logfile);
            $this->setProperty($db, 'logPost', $logPost);
            if (is_file($tmpdir)) {
                @unlink($tmpdir);
            }
        }
    }

    public function testFixtureLogAppConstructorSetsCustomLogPath()
    {
        $previous = Paths::application();
        Paths::setApplicationPath(TestAppPaths::logApp());
        $this->resetDbSingleton();
        try {
            $db = Db::instance();
            $logPath = (string) $this->property($db, 'logfile');
            $this->assertStringContainsString('fixture-log-app', $logPath);
            $this->assertStringContainsString('custom_dblog.tmp', $logPath);
            $this->assertFalse($this->property($db, 'logPost'));
        } finally {
            Paths::setApplicationPath($previous);
            $this->resetDbSingleton();
        }
    }

    public function testSetLogFileRejectsBooleanTrue()
    {
        $db = $this->freshDb();
        $this->expectException(\LogicException::class);
        $db->setLogFile(true);
    }

    public function testSimpleInsertWithRecordClassAndDryRunReturnsSqlString()
    {
        $db = Db::instance();
        $sql = $db->simple_insert(WidgetPlain::class, array(
            'name' => 'dry-run',
            'code' => 'dr-' . bin2hex(random_bytes(2)),
            'qty' => 1,
        ), null, null, null, false);
        $this->assertIsString($sql);
        $this->assertStringContainsString('INSERT INTO widget', $sql);
        $this->assertStringContainsString('dry-run', $sql);
    }

    public function testSimpleSelectViaRecordClassBuildsInfoHash()
    {
        $id = $this->insertWidget(array('name' => 'info-hash', 'code' => 'ih-' . bin2hex(random_bytes(2))));
        $db = Db::instance();
        $hash = $db->simple_select(WidgetPlain::class, array('id' => $id));
        $this->assertSame('info-hash', $hash->info[$id]->name);
    }

    public function testForAppReturnsConfiguredDbSubclassInComponentContext()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('db' => array('class' => ConfiguredDbProbe::class)));
        Registry::getInstance()->set('application', $app);
        $this->resetDbSingleton();
        try {
            $db = Db::forApp();
            $this->assertInstanceOf(ConfiguredDbProbe::class, $db);
            $this->assertSame('probe', $db->marker());
        } finally {
            Registry::getInstance()->remove('application');
            $this->resetDbSingleton();
        }
    }

    public function testInsertIdMagicReturnsNullWhenNoQueryHandleExists()
    {
        $fresh = $this->freshDb();
        $handle = new \ReflectionProperty(Db::class, 'lastQueryHandle');
        $handle->setAccessible(true);
        $handle->setValue($fresh, null);
        $conn = new \ReflectionProperty(Db::class, 'last_used_conn');
        $conn->setAccessible(true);
        $conn->setValue($fresh, null);
        $this->assertNull($fresh->insert_id);
    }

    public function testQueryLogRowsFieldUsesNumRowsForSelectAndAffectedForUpdate()
    {
        $db = Db::instance();
        $this->setProperty($db, 'sw', new Stopwatch());
        $this->setProperty($db, 'sw_total', new Stopwatch());
        $db->query_log = true;
        $db->query_log_data = array();
        try {
            $id = $this->insertWidget(array('name' => 'log-metrics', 'code' => 'lm-' . bin2hex(random_bytes(2))));
            $db->query('SELECT id FROM widget WHERE id = ' . (int) $id);
            $selectRow = end($db->query_log_data);
            $db->simple_update('widget', array('id' => $id, 'name' => 'log-metrics-updated'));
            $updateRow = end($db->query_log_data);
            $this->assertSame(1, (int) $selectRow['rows']);
            $this->assertGreaterThan(0, (int) $updateRow['rows']);
        } finally {
            $db->query_log = false;
            $db->query_log_data = array();
        }
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

    private function resetDbSingleton()
    {
        $prop = new \ReflectionProperty(Db::class, 'instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
