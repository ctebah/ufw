<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Container;
use Ufw\Registry;
use Ufw\Singleton;

class ContainerRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        Registry::getInstance()->remove('probe');
        parent::tearDown();
    }

    public function testContainerBuildsLazyInstancesPerKey()
    {
        $box = new Container(\stdClass::class, array());
        $a = $box->get('a');
        $b = $box->get('b');
        $this->assertInstanceOf(\stdClass::class, $a);
        $this->assertNotSame($a, $b);
        $this->assertSame($a, $box->get('a'));
        $this->assertTrue($box->has('a'));
        $box->set('c', $a);
        $this->assertSame($a, $box->get('c'));
        $this->assertSame(array('a' => $a, 'b' => $b, 'c' => $a), $box->getAll());
    }

    public function testRegistryStoresAndRemovesValues()
    {
        $reg = Registry::getInstance();
        $reg->set('probe', 42);
        $this->assertTrue($reg->has('probe'));
        $this->assertSame(42, $reg->get('probe'));
        $reg->remove('probe');
        $this->assertFalse($reg->has('probe'));
    }

    public function testSingletonTraitReturnsSameInstance()
    {
        $this->assertSame(SingletonProbe::getInstance(), SingletonProbe::getInstance());
    }
}

class SingletonProbe
{
    use Singleton;

    protected static $instance;
}
