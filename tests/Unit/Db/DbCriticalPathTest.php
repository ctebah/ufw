<?php
namespace Ufw\Tests\Unit\Db;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Db\Db;
use Ufw\Paths;
use Ufw\Request;
use Ufw\ServerRequest;

/**
 * Critical paths in **framework** src files (modified on feat/db-layer vs v1.0.2), not wholly new 1.1 types.
 */
class DbCriticalPathTest extends TestCase
{
    protected function tearDown(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $_POST = array();
        $_SERVER = array();
        parent::tearDown();
    }

    public function testDbConstructorReadsThrowOnErrorFromDbconnsOpts()
    {
        $db = $this->constructDb(TestAppPaths::throwApp());
        $this->assertTrue($db->getThrowOnError());
        $connections = $this->dbProperty($db, 'db_connections');
        $this->assertArrayNotHasKey('___opts', $connections);
    }

    public function testDbConstructorReadsLogFileOptAndHandleQueryErrorRespectsLogPost()
    {
        $db = $this->constructDb(TestAppPaths::logApp());
        $logfile = $this->dbProperty($db, 'logfile');
        $this->assertStringEndsWith('custom_dblog.tmp', str_replace('\\', '/', (string) $logfile));
        $this->assertFalse($this->dbProperty($db, 'logPost'));

        if (is_string($logfile) && is_file($logfile)) {
            unlink($logfile);
        }
        $_POST = array('password' => 'secret');
        $errno = new \ReflectionProperty(Db::class, 'errno');
        $errno->setAccessible(true);
        $errno->setValue($db, 1054);
        try {
            $method = new \ReflectionMethod(Db::class, 'handleQueryError');
            $method->setAccessible(true);
            $method->invoke($db, false, 'SELECT bad_col FROM widget', null, null);
        } finally {
            $_POST = array();
        }
        $body = is_string($logfile) ? (string) file_get_contents($logfile) : '';
        $this->assertStringContainsString('SELECT bad_col', $body);
        $this->assertStringNotContainsString('secret', $body);
        if (is_string($logfile) && is_file($logfile)) {
            unlink($logfile);
        }
    }

    public function testDbMysqliDriverReportModeRoundTrip()
    {
        $db = Db::instance();
        $previous = $db->getMysqliDriverReportMode();
        $db->setMysqliDriverReportMode(MYSQLI_REPORT_STRICT);
        $this->assertSame(MYSQLI_REPORT_STRICT, $db->getMysqliDriverReportMode());
        $db->setMysqliDriverReportMode($previous);
    }

    public function testApplicationFormatsRichRequestContextForLogs()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $psr = new ServerRequest(
            array(
                'REQUEST_METHOD' => 'GET',
                'HTTP_REFERER' => 'https://ref.example/',
                'REMOTE_ADDR' => '10.0.0.2',
                'HTTP_USER_AGENT' => str_repeat('x', 250),
            ),
            array(),
            '/items?page=2',
            'GET',
            'php://input',
            array(),
            array(),
            array('page' => '2'),
            null,
            '1.1',
            array('module' => 'shop', 'controller' => 'item', 'action' => 'list')
        );
        $app->setRequest(new Request(array(), array(), array(), null, $psr));

        $format = new \ReflectionMethod(Application::class, 'formatRequestContextForLog');
        $format->setAccessible(true);
        $text = $format->invoke($app, 500);
        $this->assertStringContainsString('500', $text);
        $this->assertStringContainsString('page=2', $text);
        $this->assertStringContainsString('module=shop', $text);
        $this->assertStringContainsString('Referer:', $text);
        $this->assertStringContainsString('Client: 10.0.0.2', $text);
        $this->assertStringContainsString('...', $text);
    }

    public function testApplicationBuildsContextFromGlobalsWhenRequestMissing()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/form/save';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = 'on';

        $format = new \ReflectionMethod(Application::class, 'formatRequestContextForLog');
        $format->setAccessible(true);
        $text = $format->invoke($app, 422);
        $this->assertStringContainsString('POST', $text);
        $this->assertStringContainsString('https://localhost/form/save', $text);
    }

    public function testApplicationStaticGetConfigReturnsSetConfig()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('feature' => true));
        $this->assertSame(array('feature' => true), Application::getConfig());
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
