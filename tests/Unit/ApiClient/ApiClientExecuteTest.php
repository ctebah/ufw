<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Helper;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientExecuteProbe;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientStubRequester;

class ApiClientExecuteTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientExecuteProbe::resetInstances();
        parent::tearDown();
    }

    public function testExecuteRunInvokesDoAndStoresResponse()
    {
        ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'routes' => array(),
                'controllers' => array(),
                'module' => array(),
                'controller_aliases' => array(),
            ),
        ));
        $api = ApiClientExecuteProbe::getInstance(Client::getInstance());
        $api->setStubResponse(new ApiResponse());
        $api->run();
        $this->assertSame($api->getStubResponse(), $api->getResponse());
    }

    public function testClientResolveRequesterUsesInjectedInstance()
    {
        ApplicationFixtures::bareWithController();
        $client = Client::getInstance();
        $probe = ApiClientStubRequester::getInstance();
        $client->setRequester($probe);
        $this->assertSame($probe, $client->getRequester());
    }

    public function testHelperGetMappedKeyArrayViaCreatePayload()
    {
        $helper = Helper::getInstance();
        $ref = ApiClientTestApi::getInstance(Client::getInstance());
        $ref->setFieldsMap(array('title' => 'title'));
        $payload = $helper->createPayload($ref, array('title'), array('title' => 'X'));
        $this->assertSame('X', $payload['title']);
    }

    public function testExecuteDoMethodRunsDirectly()
    {
        ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'routes' => array(),
                'controllers' => array(),
                'module' => array(),
                'controller_aliases' => array(),
            ),
        ));
        $api = ApiClientExecuteProbe::getInstance(\Ufw\Component\ApiClient\Client::getInstance());
        $stub = new \Ufw\Component\ApiClient\Response();
        $api->setStubResponse($stub);
        $do = new \ReflectionMethod(ApiClientExecuteProbe::class, '_do');
        $do->setAccessible(true);
        $do->invoke($api);
        $this->assertSame($stub, $api->getResponse());
    }
}
