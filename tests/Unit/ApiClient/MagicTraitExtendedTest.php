<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Exception as ApiException;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Form\Element\Text;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientChildInvokeProbe;
use Ufw\Tests\Support\ApiClientStubRequester;

class MagicTraitExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
    }

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientChildInvokeProbe::resetInstances();
        ApiClientStubRequester::clearNextResponse();
        parent::tearDown();
    }

    public function testMagicGetExposesHelperSingleton()
    {
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $this->assertSame(\Ufw\Component\ApiClient\Helper::getInstance(), $api->helper);
    }

    public function testGetApplicationThroughTrait()
    {
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $this->assertInstanceOf(\Ufw\Application::class, $api->exposeGetApplication());
    }

    public function testGetPrefixedMethodReturnsSiblingApiClass()
    {
        $client = Client::getInstance();
        $client->setRequester(ApiClientStubRequester::getInstance());
        $parent = ApiClientTestApi::getInstance($client);
        $child = $parent->getChild();
        $this->assertInstanceOf(\Ufw\Tests\Support\Child::class, $child);
    }

    public function testRequesterGetReturnsResponseWhenRaiseOnErrorDisabled()
    {
        $client = Client::getInstance();
        $client->setRequester(ApiClientStubRequester::getInstance());
        $api = ApiClientTestApi::getInstance($client);
        $api->setRaiseExceptionOnApiError(false);
        $response = $api->get('/probe');
        $this->assertInstanceOf(ApiResponse::class, $response);
    }

    public function testRaiseExceptionOnApiErrorThrowsWhenResponseHasException()
    {
        $client = Client::getInstance();
        $client->setRequester(ApiClientStubRequester::getInstance());
        $api = ApiClientTestApi::getInstance($client);
        $api->setRaiseExceptionOnApiError(true);
        ApiClientStubRequester::setNextResponse($this->apiErrorResponse());
        $this->expectException(ApiException::class);
        $api->get('/down');
    }

    public function testInvokePassesFormInstanceToSubResource()
    {
        $client = Client::getInstance();
        $client->setRequester(ApiClientStubRequester::getInstance());
        $api = ApiClientChildInvokeProbe::getInstance($client);
        $child = ApiClientChildInvokeProbe::getInstance($client);
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $form->populate(array('title' => 'From form'));
        $api->child($form);
        $this->assertSame('From form', $child->getForm()->getValues()['title']);
    }

    public function testNormalizeExceptionMessageReturnsNullForNonStringList()
    {
        $method = new \ReflectionMethod(ApiClientTestApi::class, 'normalizeExceptionMessage');
        $method->setAccessible(true);
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $this->assertNull($method->invoke($api, array(1, 2)));
    }

    /**
     * @return ApiResponse
     */
    private function apiErrorResponse()
    {
        $response = new ApiResponse();
        $response->setCode(503);
        $response->setException(new \RuntimeException('api-down'));

        return $response;
    }
}
