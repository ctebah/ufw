<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\ApiClient\Base;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Response;
use Ufw\Registry;
use Ufw\Tests\Support\RuntimeProbe;

class ApiClientBaseTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiClientProbe::resetInstances();
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testSingletonInvokeAndClientMutators()
    {
        $client = Client::getInstance();
        $api = ApiClientProbe::getInstance($client);
        $this->assertSame($api, ApiClientProbe::getInstance());
        $this->assertSame($api, $api());

        $psr = new \Laminas\Diactoros\Response('php://memory', 200);
        $psr->getBody()->write('ok');
        $wrapped = new Response();
        $wrapped->setResponse($psr);

        $api->setFieldsMap(array('remote' => 'local'))
            ->setRaiseExceptionOnApiError(false)
            ->setResponse($wrapped);

        $this->assertSame(array('remote' => 'local'), $api->getFieldsMap());
        $this->assertFalse($api->getRaiseExceptionOnApiError());
        $body = $api->getResponse()->getBody();
        $this->assertSame('ok', RuntimeProbe::coerceHttpBodyToPlainText($body));
        $this->assertSame($client, $api->getClient());
    }

    public function testIsLoggedOnAndNormalizeFieldErrors()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);

        $api = ApiClientProbe::getInstance(Client::getInstance());
        $user = new class {
            public function isLoggedOn()
            {
                return true;
            }
        };
        $api->setUser($user);
        $this->assertTrue($api->exposeIsLoggedOn());

        if (!method_exists(Base::class, 'normalizeFieldErrorMessages')) {
            $this->markTestSkipped('normalizeFieldErrorMessages is not on v1.0.2 ApiClient Base.');
        }
        $method = new \ReflectionMethod(Base::class, 'normalizeFieldErrorMessages');
        $method->setAccessible(true);
        $this->assertSame(array('a'), $method->invoke($api, array('a', '', 7)));
        $this->assertSame(array('msg'), $method->invoke($api, 'msg'));
    }
}

class ApiClientProbe extends Base
{
    /**
     * @param object|null $user
     */
    public function setUser($user)
    {
        $this->_user = $user;
    }

    public function exposeIsLoggedOn()
    {
        return $this->isLoggedOn();
    }

    public static function resetInstances()
    {
        $prop = new \ReflectionProperty(Base::class, '_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
    }
}
