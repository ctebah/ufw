<?php
namespace Ufw\Tests\Unit\ApiClient;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\ApiClient\Exception as ApiException;
use Ufw\Component\ApiClient\FormAlike;
use Ufw\Component\ApiClient\Helper;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Response;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientRequesterProbe;

class ApiClientClientTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientRequesterProbe::resetInstance();
        ApiClientTestApi::resetInstances();
        parent::tearDown();
    }

    public function testRequesterVerbsUseMockHttpClient()
    {
        ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'routes' => array(),
                'controllers' => array(),
                'module' => array(),
                'controller_aliases' => array(),
            ),
        ));
        Paths::setApplicationPath(TestAppPaths::app());

        $mock = new MockHandler(array(
            new PsrResponse(200, array('Content-Type' => 'application/json'), '{"ok":true}'),
            new PsrResponse(204),
            new PsrResponse(200, array('Content-Type' => 'application/json'), '{}'),
            new PsrResponse(200, array('Content-Type' => 'application/json'), '{}'),
            new PsrResponse(200, array('Content-Type' => 'application/json'), '{}'),
            new PsrResponse(200, array('Content-Type' => 'application/json'), '{}'),
        ));
        $guzzle = new GuzzleClient(array(
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'http://api.test',
        ));

        $requester = ApiClientRequesterProbe::getInstance();
        $requester->injectHttpClient($guzzle);
        $requester->disableDebugLog();

        $this->assertTrue($requester->get('/one')->isOk());
        $requester->head('/two');
        $requester->delete('/three', array('a' => 1));
        $requester->post('/four', array('b' => 2));
        $requester->put('/five', array('c' => 3));
        $requester->patch('/six', array('d' => 4));
        $this->assertInstanceOf(ApiResponse::class, $requester->getLastResponse());
    }

    public function testResponseExceptionPathAndDelegation()
    {
        $wrapped = new ApiResponse();
        $this->assertFalse($wrapped->hasException());
        $this->assertTrue($wrapped->isOk());

        $psr = new PsrResponse(400, array('Content-Type' => 'application/json'), '{"error":"bad"}');
        $wrapped->setResponse($psr);
        $this->assertSame(400, $wrapped->getCode());
        $this->assertSame('bad', $wrapped->getMessage('error'));

        $inner = new \Exception('fail');
        $wrapped->setException($inner);
        $this->assertTrue($wrapped->hasException());
        $this->assertFalse($wrapped->isOk());
        $this->assertSame('fail', $wrapped->getException()->getMessage());
    }

    public function testApiExceptionCarriesResponse()
    {
        $response = new ApiResponse();
        $ex = new ApiException('api', 422, null);
        $ex->setResponse($response);
        $this->assertSame($response, $ex->getResponse());
    }

    public function testApiExceptionNormalizesArrayMessagesAndPrevious()
    {
        $ex = new ApiException(array('a', 'b'), 500, new \Exception('prev'));
        $this->assertSame(array('a', 'b'), $ex->getMessages());
        $this->assertSame('a', $ex->getMessage());
    }

    public function testResponseMagicCallDelegatesToPsrResponse()
    {
        $wrapped = new ApiResponse();
        $psr = new PsrResponse(200, array('X-Test' => 'yes'), 'body');
        $wrapped->setResponse($psr);
        $this->assertSame('yes', $wrapped->getHeaderLine('X-Test'));
    }

    public function testHelperAssignValueToKeyAndCreatePayload()
    {
        ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'routes' => array(),
                'controllers' => array(),
                'module' => array(),
                'controller_aliases' => array(),
            ),
        ));
        $helper = Helper::getInstance();
        $payload = $helper->assignValueToKey(array(), 'user[name]', 'Ann');
        $this->assertSame('Ann', $payload['user']['name']);

        $ref = ApiClientTestApi::getInstance(\Ufw\Component\ApiClient\Client::getInstance());
        $ref->setFieldsMap(array('title' => 'title'));
        $built = $helper->createPayload($ref, array('title'), array('title' => 'T'));
        $this->assertSame('T', $built['title']);
    }

    public function testHelperErrorPathsAndFormAlikePopulate()
    {
        ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'routes' => array(),
                'controllers' => array(),
                'module' => array(),
                'controller_aliases' => array(),
            ),
        ));
        $helper = Helper::getInstance();
        $paths = $helper->getErrorPaths(
            array('field' => 'local'),
            array('field' => array('Required'))
        );
        $this->assertNotEmpty($paths);

        $form = new FormAlike(array('action' => '/noop'));
        $form->populate(array('x' => '1'));
        $this->assertSame(array('x' => '1'), $form->getValues());
    }
}
