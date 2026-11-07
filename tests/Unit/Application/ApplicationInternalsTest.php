<?php
namespace Ufw\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Registry;

class ApplicationInternalsTest extends TestCase
{
    protected function setUp(): void
    {
        $config = new \ReflectionProperty(Application::class, 'config');
        $config->setAccessible(true);
        $config->setValue(null, null);
    }

    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testBuildRequestContextUsesServerNameWhenHostHeaderMissing()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $_SERVER = array(
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/only-uri',
            'SERVER_NAME' => 'server.test',
        );
        $build = new \ReflectionMethod(Application::class, 'buildRequestContextLinesFromGlobals');
        $build->setAccessible(true);
        $blob = implode("\n", $build->invoke($app));
        $this->assertStringContainsString('http://server.test/only-uri', $blob);
        $_SERVER = array();
    }

    public function testBuildRequestContextWhenUriEmptyNotesPlaceholder()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $_SERVER = array('REQUEST_METHOD' => 'HEAD');
        $build = new \ReflectionMethod(Application::class, 'buildRequestContextLinesFromGlobals');
        $build->setAccessible(true);
        $blob = implode("\n", $build->invoke($app));
        $this->assertStringContainsString('REQUEST_URI empty', $blob);
        $_SERVER = array();
    }

    public function testGetModuleDispatchConfigNormalizesModuleBlock()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Application::class, 'getModuleDispatchConfig');
        $method->setAccessible(true);
        $out = $method->invoke($app, array(
            'module' => array(
                'shop' => array('controllers' => array('item'), 0 => 'skip'),
            ),
        ), 'shop');
        $this->assertArrayHasKey('controllers', $out);
        $this->assertArrayNotHasKey(0, $out);
    }

    public function testNormalizeStringListDropsNonStrings()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Application::class, 'normalizeStringList');
        $method->setAccessible(true);
        $list = $method->invoke($app, array('a', 1, 'b'));
        $this->assertSame(array('a', 'b'), $list);
    }

    public function testGetDispatchConfigReturnsEmptyArrayWhenApplicationConfigMissing()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Application::class, 'getDispatchConfig');
        $method->setAccessible(true);
        $this->assertSame(array(), $method->invoke($app));
    }
}
