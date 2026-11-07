<?php
namespace Ufw\Tests\Unit\Db;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Db\Db;
use Ufw\Paths;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Unit\ConfiguredDbProbe;

class DbThrowOnErrorConfigTest extends TestCase
{
    public function testDbconnsKeyTurnsThrowingOnAndIsNotAConnection()
    {
        $db = $this->constructDb(TestAppPaths::throwApp());
        $this->assertTrue($db->getThrowOnError());

        $connections = $this->connectionsOf($db);
        $this->assertArrayNotHasKey('___opts', $connections);
        $this->assertArrayNotHasKey('note', $connections);
        $this->assertArrayHasKey('master_server', $connections);
        $this->assertSame('local', $connections['master_server']['ident']);
    }

    public function testMissingKeyStaysSilent()
    {
        $db = $this->constructDb(TestAppPaths::app());
        $this->assertFalse($db->getThrowOnError());
        $this->assertArrayHasKey('default', $this->connectionsOf($db));
        $this->assertNull($db->rows_affected);
        $this->assertNull($db->insert_id);
    }

    public function testExplicitFalseStaysSilentAndIsNotAConnection()
    {
        $db = $this->constructDb(TestAppPaths::silentApp());
        $this->assertFalse($db->getThrowOnError());

        $connections = $this->connectionsOf($db);
        $this->assertArrayNotHasKey('___opts', $connections);
        $this->assertArrayHasKey('default', $connections);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInstanceIgnoresAppConfigClass()
    {
        $this->setAppConfig(array(
            'db' => array(
                'class' => ConfiguredDbProbe::class,
            ),
        ));
        $this->assertInstanceOf(Db::class, Db::instance());
        $this->assertNotInstanceOf(ConfiguredDbProbe::class, Db::instance());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAppConfigClassIsWhatGetDbReturns()
    {
        $this->setAppConfig(array(
            'db' => array(
                'class' => ConfiguredDbProbe::class,
            ),
        ));
        $db = (new WidgetPlain())->getDb();
        $this->assertInstanceOf(ConfiguredDbProbe::class, $db);
        $this->assertSame('probe', $db->marker());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAppConfigClassThatIsNotADbIsRejected()
    {
        $this->setAppConfig(array(
            'db' => array(
                'class' => 'Ufw\\Tests\\Unit\\NotADb',
            ),
        ));
        $this->expectException(\LogicException::class);
        (new WidgetPlain())->getDb();
    }

    public function testLogFileAndLogPostComeFromDbconnsOpts()
    {
        $db = $this->constructDb(TestAppPaths::logApp());
        $logfile = $this->dbProperty($db, 'logfile');
        $this->assertStringEndsWith('custom_dblog.tmp', str_replace('\\', '/', $logfile));
        $this->assertFalse($this->dbProperty($db, 'logPost'));
        if (is_file($logfile)) {
            unlink($logfile);
        }
        $_POST = array('password' => 'secret');
        $errno = new \ReflectionProperty(Db::class, 'errno');
        $errno->setAccessible(true);
        $errno->setValue($db, 1054);
        try {
            $method = new \ReflectionMethod(Db::class, 'handleQueryError');
            $method->setAccessible(true);
            $method->invoke($db, false, 'SELECT 1', null, null);
        } finally {
            $_POST = array();
        }
        $body = file_get_contents($logfile);
        $this->assertStringContainsString('SELECT 1', $body);
        $this->assertStringNotContainsString('POST:', $body);
        $this->assertStringNotContainsString('secret', $body);
        $db->setLogFile(false);
        $this->assertNull($this->dbProperty($db, 'logfile'));
    }

    public function testMysqliDriverReportModeCanBeReadAndUpdated()
    {
        $db = Db::instance();
        $previous = $db->getMysqliDriverReportMode();
        $db->setMysqliDriverReportMode(MYSQLI_REPORT_STRICT);
        $this->assertSame(MYSQLI_REPORT_STRICT, $db->getMysqliDriverReportMode());
        $db->setMysqliDriverReportMode($previous);
    }

    public function testSetLogPostCanBeToggledOnDbInstance()
    {
        $db = Db::instance();
        $db->setLogPost(false);
        $this->assertFalse($this->dbProperty($db, 'logPost'));
        $db->setLogPost(true);
        $this->assertTrue($this->dbProperty($db, 'logPost'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMissingLogFileOptUsesTheDefaultPath()
    {
        $db = Db::instance();
        $this->assertSame(
            Paths::application() . '/log/error_dblog.tmp',
            $this->dbProperty($db, 'logfile')
        );
        $this->assertTrue($this->dbProperty($db, 'logPost'));
    }

    /**
     * @param array $config
     */
    private function setAppConfig(array $config)
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig($config);
    }

    /**
     * @param string $applicationPath
     * @return Db
     */
    private function constructDb($applicationPath)
    {
        $previous = Paths::application();
        Paths::setApplicationPath($applicationPath);
        try {
            $ref = new \ReflectionClass(Db::class);
            $ctor = $ref->getConstructor();
            $ctor->setAccessible(true);
            $db = $ref->newInstanceWithoutConstructor();
            $ctor->invoke($db);
            return $db;
        } finally {
            Paths::setApplicationPath($previous);
        }
    }

    /**
     * @param Db $db
     * @return array
     */
    private function connectionsOf(Db $db)
    {
        return $this->dbProperty($db, 'db_connections');
    }

    /**
     * @param Db $db
     * @param string $name
     * @return mixed
     */
    private function dbProperty(Db $db, $name)
    {
        $prop = new \ReflectionProperty(Db::class, $name);
        $prop->setAccessible(true);
        return $prop->getValue($db);
    }
}
