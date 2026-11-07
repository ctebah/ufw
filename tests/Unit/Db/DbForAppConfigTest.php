<?php
namespace Ufw\Tests\Unit\Db;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Db\Db;
use Ufw\Registry;
use Ufw\Tests\Unit\ConfiguredDbProbe;

class DbForAppConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        $prop = new \ReflectionProperty(Db::class, 'instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        Registry::getInstance()->remove('application');
        $configProp = new \ReflectionProperty(Application::class, 'config');
        $configProp->setAccessible(true);
        $configProp->setValue(null, null);
        parent::tearDown();
    }

    public function testConfiguredInstanceClassReadsApplicationConfig()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('db' => array('class' => ConfiguredDbProbe::class)));
        Registry::getInstance()->set('application', $app);

        $method = new \ReflectionMethod(Db::class, 'configuredInstanceClass');
        $method->setAccessible(true);
        $this->assertSame(ConfiguredDbProbe::class, $method->invoke(null));
    }

    public function testConfiguredInstanceClassThrowsWhenClassIsInvalid()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('db' => array('class' => \stdClass::class)));
        Registry::getInstance()->set('application', $app);

        $method = new \ReflectionMethod(Db::class, 'configuredInstanceClass');
        $method->setAccessible(true);
        $this->expectException(\LogicException::class);
        $method->invoke(null);
    }

    public function testConfiguredInstanceClassReturnsNullWhenDbKeyMissing()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array()));
        Registry::getInstance()->set('application', $app);

        $method = new \ReflectionMethod(Db::class, 'configuredInstanceClass');
        $method->setAccessible(true);
        $this->assertNull($method->invoke(null));
    }
}
