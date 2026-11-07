<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientChildInvokeProbe;
use Ufw\Tests\Support\ApiClientStubRequester;
use Ufw\Route\Literal;
use Ufw\Router;
use Ufw\Tests\Support\TestHttpRequest;

class MagicTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientChildInvokeProbe::resetInstances();
        ApiClientStubRequester::clearNextResponse();
        parent::tearDown();
    }

    public function testInvokeSubResourceWithArrayPayloadPopulatesForm()
    {
        list($app) = ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array(), 'controllers' => array('ping'))));
        $request = TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'));
        $app->setRequest($request);
        $router = new Router($app, $request, array(
            'default' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $router->match();
        $app->setRouter($router);

        $client = Client::getInstance();
        $client->setRequester(ApiClientStubRequester::getInstance());
        $api = ApiClientChildInvokeProbe::getInstance($client);
        $child = ApiClientChildInvokeProbe::getInstance($client);
        $child->setFieldsMap(array('title' => 'title'));

        $response = $api->child(array('title' => 'From array'));
        $this->assertSame($child, $response);
        $values = $child->getForm()->getValues();
        $this->assertSame('From array', $values['title']);
    }

    public function testNormalizeExceptionMessageCoversStringAndList()
    {
        $method = new \ReflectionMethod(\Ufw\Component\ApiClient\Base::class, 'normalizeExceptionMessage');
        $method->setAccessible(true);
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $this->assertSame('err', $method->invoke($api, 'err'));
        $list = $method->invoke($api, array('a', 1, 'b'));
        $this->assertSame(array('a', 'b'), $list);
    }
}
