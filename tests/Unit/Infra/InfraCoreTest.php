<?php
namespace Ufw\Tests\Unit\Infra;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Request;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

/**
 * Paths, Registry, Request proxy — small meaningful framework surface (not chasing line zero).
 */
class InfraCoreTest extends TestCase
{
    protected function setUp(): void
    {
        $ref = new \ReflectionProperty(Registry::class, 'observers');
        $ref->setAccessible(true);
        $ref->setValue(Registry::getInstance(), array('' => array()));
    }

    protected function tearDown(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $registry = Registry::getInstance();
        if ($registry->has('application_path')) {
            $registry->remove('application_path');
        }
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testPathsApplicationReadsRegistryWhenOverrideCleared()
    {
        Paths::setApplicationPath(null);
        Registry::getInstance()->set('application_path', '/path/from/registry');
        $this->assertSame('/path/from/registry', Paths::application());
    }

    public function testPathsApplicationReadsApplicationPathConstantWhenNoOverrideOrRegistry()
    {
        if (\defined('APPLICATION_PATH')) {
            $this->markTestSkipped('APPLICATION_PATH already defined in this PHP process.');
        }
        Paths::setApplicationPath(null);
        \define('APPLICATION_PATH', sys_get_temp_dir());
        $this->assertSame(sys_get_temp_dir(), Paths::application());
    }

    public function testRegistryDetachNoOpsWhenObserverKeyMissing()
    {
        $registry = Registry::getInstance();
        $observer = new class implements \SplObserver {
            public function update(\SplSubject $subject): void
            {
            }
        };
        $registry->detach($observer, 'no-such-bucket');
        $this->assertTrue(true);
    }

    public function testRegistryNotifyDeliversToNamedAndDefaultBuckets()
    {
        $registry = Registry::getInstance();
        $namedHits = 0;
        $defaultHits = 0;
        $named = new class($namedHits) implements \SplObserver {
            private $hits;

            public function __construct(&$hits)
            {
                $this->hits = &$hits;
            }

            public function update(\SplSubject $subject): void
            {
                $this->hits++;
            }
        };
        $default = new class($defaultHits) implements \SplObserver {
            private $hits;

            public function __construct(&$hits)
            {
                $this->hits = &$hits;
            }

            public function update(\SplSubject $subject): void
            {
                $this->hits++;
            }
        };
        $registry->attach($named, 'gate');
        $registry->attach($default, '');
        $registry->notify('gate');
        $this->assertSame(1, $namedHits);
        $this->assertSame(1, $defaultHits);

        $registry->notify('missing-key');
        $this->assertSame(1, $namedHits);
        $this->assertSame(2, $defaultHits);
        $registry->detach($named, 'gate');
        $registry->detach($default, '');
    }

    public function testRequestCallProxiesServerRequestMethods()
    {
        $psr = new ServerRequest(
            array(),
            array(),
            '/proxied/path',
            'PUT',
            'php://input',
            array()
        );
        $request = TestHttpRequest::withAttributes(array(), 'PUT', '/proxied/path', $psr);
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/proxied/path', $request->getUri()->getPath());
    }
}
