<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Registry;

class ApplicationHasActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testHasActionEmptyActionWithHttpMethodReturnsFalse(): void
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $bothEmpty = $has->invoke($app, 'ping', '', null, null);
        $this->assertIsArray($bothEmpty);
        $this->assertNull($bothEmpty['action']);
        $this->assertFalse($has->invoke($app, 'ping', 'index', new \stdClass(), null));
    }

    public function testHasActionApiEarlyReturnWithoutHttpVerb(): void
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'ping', 'index', null, null);
        $this->assertIsArray($result);
        $this->assertSame('api', $result['kind']);
        $this->assertNull($result['action']);
    }
}
