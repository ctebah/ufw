<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Ufw\Application;
use Ufw\Db\Db;
use Ufw\Db\QueryException;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\BrokenDbInstanceProbe;

class DbQueryHelpersTest extends ComponentTestCase
{
    protected function tearDown(): void
    {
        $this->resetDbSingleton();
        parent::tearDown();
    }

    public function testConstructorAppliesThrowOnErrorFromFixtureOpts()
    {
        $previous = Paths::application();
        Paths::setApplicationPath(TestAppPaths::throwApp());
        $this->resetDbSingleton();
        try {
            $db = Db::instance();
            $this->assertTrue($db->getThrowOnError());
            $logPost = new \ReflectionProperty(Db::class, 'logPost');
            $logPost->setAccessible(true);
            $this->assertTrue($logPost->getValue($db));
        } finally {
            Paths::setApplicationPath($previous);
            $this->resetDbSingleton();
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDbLogEnvEnablesQueryLogAndStopwatchesOnConstruct()
    {
        if (!defined('DBLOG_ENV')) {
            define('DBLOG_ENV', true);
        }
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        \Ufw\Paths::setApplicationPath(TestAppPaths::app());
        $ref = new \ReflectionClass(Db::class);
        $db = $ref->newInstanceWithoutConstructor();
        $ref->getConstructor()->invoke($db);
        $this->assertTrue($db->query_log);
        $sw = new \ReflectionProperty(Db::class, 'sw');
        $sw->setAccessible(true);
        $this->assertInstanceOf(\Ufw\Utils\Stopwatch::class, $sw->getValue($db));
    }

    public function testConnectReturnsFalseWhenResourceNeverEstablished()
    {
        $db = $this->freshDb();
        $this->setConnections($db, array(
            'shell' => array('ident' => 'shell'),
        ));
        $this->assertFalse($db->connect('shell'));
    }

    public function testConnectReturnsFalseForUnknownIdentWithoutAttemptingDriver()
    {
        $db = $this->freshDb();
        $this->setConnections($db, array());
        $this->assertFalse($db->connect('not-in-map'));
    }

    public function testConnectReturnsResourceHandleWithoutReconnect()
    {
        if (!function_exists('fopen')) {
            $this->markTestSkipped('fopen unavailable');
        }
        $db = $this->freshDb();
        $handle = fopen('php://memory', 'r+');
        $this->assertIsResource($handle);
        try {
            $this->assertSame($handle, $db->connect($handle));
        } finally {
            fclose($handle);
        }
    }

    public function testQueryLogTableReturnsImmediatelyForAjaxRequests()
    {
        $db = Db::instance();
        $db->query_log_data = array(array('query' => 'SELECT 1', 'rows' => 1));
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        ob_start();
        try {
            Db::query_log_table();
            $this->assertSame('', ob_get_clean());
        } finally {
            $db->query_log_data = array();
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }
    }

    public function testSimpleSelectQueryNormalizesWhereFieldsOrderAndLimit()
    {
        $db = Db::instance();
        $sql = $db->simple_select_query('widget', array(
            'where' => 'ignored',
            'fields' => array('id', 'name', 99),
            'orderby' => array('name ASC', 0),
            'limit' => 5,
        ));
        $this->assertStringContainsString('WHERE 1=1', $sql);
        $this->assertStringContainsString('id, name', $sql);
        $this->assertStringContainsString('ORDER BY name ASC', $sql);
        $this->assertStringContainsString('LIMIT 5', $sql);

        $fallback = $db->simple_select_query('widget', array(
            'where' => array('id' => 1),
            'fields' => 123,
        ));
        $this->assertStringContainsString('SELECT * FROM widget', $fallback);
    }

    public function testFetchArrayReturnsNullForFailedQueryResult()
    {
        $db = Db::instance();
        $this->assertNull($db->fetch_array(false));
    }

    public function testFetchAllWithoutIndexBuildsListOfObjects()
    {
        $id = $this->insertWidget(array('name' => 'list-row', 'code' => 'lr-' . bin2hex(random_bytes(2))));
        $db = Db::instance();
        $rows = $db->fetch_all(
            'SELECT id, name FROM widget WHERE id = ' . (int) $id,
            'stdClass'
        );
        $this->assertCount(1, $rows);
        $this->assertSame('list-row', $rows[0]->name);
    }

    public function testHandleQueryErrorAppendsPostPayloadWhenEnabled()
    {
        $db = Db::instance();
        $tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-db-post-' . bin2hex(random_bytes(4)) . '.log';
        $savedLog = $this->property($db, 'logfile');
        $savedPost = $this->property($db, 'logPost');
        $this->setProperty($db, 'logfile', $tmpdir);
        $db->setLogPost(true);
        $_POST = array('gate' => 'payload');
        try {
            $this->assertFalse($db->query('SELECT no_such_gate_column FROM widget', null, __FILE__, __LINE__));
            $this->assertFileExists($tmpdir);
            $contents = file_get_contents($tmpdir);
            $this->assertStringContainsString('gate=payload', $contents);
        } finally {
            $_POST = array();
            $this->setProperty($db, 'logfile', $savedLog);
            $this->setProperty($db, 'logPost', $savedPost);
            if (is_file($tmpdir)) {
                @unlink($tmpdir);
            }
        }
    }

    public function testQueryThrowsViaHandleQueryErrorWhenConnectionUnavailable()
    {
        $db = $this->freshDb();
        $this->setConnections($db, array(
            'offline' => array('ident' => 'offline'),
        ));
        self::configureDbThrowOnError($db, true);
        try {
            $this->expectException(QueryException::class);
            $db->query('SELECT 1', 'offline');
        } finally {
            self::configureDbThrowOnError($db, false);
        }
    }

    public function testDuplicateInsertThrowsWhenThrowOnErrorEnabled()
    {
        $code = 'dup-throw-' . bin2hex(random_bytes(3));
        $this->insertWidget(array('code' => $code, 'name' => 'first'));
        $db = Db::instance();
        self::configureDbThrowOnError($db, true);
        try {
            $this->expectException(QueryException::class);
            $db->simple_insert('widget', array(
                'name' => 'dup',
                'qty' => 1,
                'data' => '{}',
                'version' => 1,
                'code' => $code,
            ));
        } finally {
            self::configureDbThrowOnError($db, false);
        }
    }

    public function testForAppRejectsConfiguredInstanceThatIsNotDb()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'db' => array('class' => BrokenDbInstanceProbe::class),
        ));
        Registry::getInstance()->set('application', $app);
        $this->resetDbSingleton();
        try {
            $this->expectException(\LogicException::class);
            Db::forApp();
        } finally {
            Registry::getInstance()->remove('application');
            ApplicationFixtures::resetApplicationStaticConfig();
            $this->resetDbSingleton();
        }
    }

    public function testQueryLogTableCoercesNonScalarCellsToEmptyString()
    {
        $db = Db::instance();
        $db->query_log_data = array(
            array('query' => 'SELECT 1', 'rows' => array('nested')),
        );
        $saved = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : null;
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        ob_start();
        try {
            Db::query_log_table();
            $html = ob_get_clean();
            $this->assertStringContainsString('query-log-table', $html);
        } finally {
            $db->query_log_data = array();
            if ($saved !== null) {
                $_SERVER['HTTP_X_REQUESTED_WITH'] = $saved;
            }
        }
    }

    public function testGetThrowOnErrorReflectsSetter()
    {
        $db = Db::instance();
        $db->setThrowOnError(true);
        $this->assertTrue($db->getThrowOnError());
        $db->setThrowOnError(false);
        $this->assertFalse($db->getThrowOnError());
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
     * @param array $connections
     */
    private function setConnections(Db $db, array $connections)
    {
        $prop = new \ReflectionProperty(Db::class, 'db_connections');
        $prop->setAccessible(true);
        $prop->setValue($db, $connections);
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
