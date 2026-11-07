<?php
namespace Ufw\Tests\Unit\Db;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Db\Db;
use Ufw\Registry;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Unit\ConfiguredDbProbe;

class DbForAppTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->resetDbSingleton();
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testForAppReturnsConfiguredDbSubclass()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'db' => array('class' => ConfiguredDbProbe::class),
        ));
        Registry::getInstance()->set('application', $app);
        $this->resetDbSingleton();

        $db = Db::forApp();
        $this->assertInstanceOf(ConfiguredDbProbe::class, $db);
        $this->assertSame('probe', $db->marker());
    }

    public function testForAppFallsBackToCoreDbWhenClassKeyMissing()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('db' => array('driver' => 'mysqli')));
        Registry::getInstance()->set('application', $app);
        $this->resetDbSingleton();

        $db = Db::forApp();
        $this->assertInstanceOf(Db::class, $db);
        $this->assertNotInstanceOf(ConfiguredDbProbe::class, $db);
    }

    public function testForAppRejectsNonSubclassConfiguredClass()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('db' => array('class' => \stdClass::class)));
        Registry::getInstance()->set('application', $app);
        $this->resetDbSingleton();

        $this->expectException(\LogicException::class);
        Db::forApp();
    }

    private function resetDbSingleton()
    {
        $prop = new \ReflectionProperty(Db::class, 'instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
