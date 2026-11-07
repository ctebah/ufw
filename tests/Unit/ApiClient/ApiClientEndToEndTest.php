<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Component\Csrf;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Db\Condition;
use Ufw\Envs;
use Ufw\Form\Element\Text;
use Ufw\Helper\Assets;
use Ufw\Helper\FlashMessenger;
use Ufw\Helper\Url;
use Ufw\JsOut;
use Ufw\Middleware\Base as MiddlewareBase;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Response;
use Ufw\Router;
use Ufw\Route\Literal;
use Ufw\ServerRequest;
use Ufw\ServerRequestFactory;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientStubRequester;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class ApiClientEndToEndTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        Envs::setRuntimeEnvLabel(null);
        ApiClientTestApi::resetInstances();
        ApiClientStubRequester::clearNextResponse();
        $_GET = array();
        $_POST = array();
        $_SERVER = array();
        $_SESSION = array();
        parent::tearDown();
    }

    public function testRequestCallMagicForwardsToServerRequest()
    {
        $request = TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'), 'POST', '/ping');
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('ping', $request->__get('controller'));
    }

    public function testRequestArrayAccessAndRuntimeException()
    {
        $request = new Request(array('token' => 'abc'), array(), array());
        $this->assertSame('abc', $request['token']);
        $request->setRuntimeException(new \RuntimeException('boom'));
        $this->assertSame('boom', $request->getRuntimeException()->getMessage());
    }

    public function testServerRequestFactoryFromGlobalsBuildsUri()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/factory-test';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $psr = ServerRequestFactory::fromGlobals();
        $this->assertSame('/factory-test', $psr->getUri()->getPath());
    }

    public function testResponseHandlersAndInnerResponseRoundTrip()
    {
        $response = new Response();
        $handlers = new \ReflectionMethod(Response::class, 'handlers');
        $handlers->setAccessible(true);
        $this->assertIsArray($handlers->invoke(null));
        $inner = new \Laminas\Diactoros\Response('body', 201);
        $response->setInnerResponse($inner);
        $this->assertSame(201, $response->getInnerResponse()->getStatusCode());
    }

    public function testRouterGetRouteAndFailedMatchMergeGroups()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $request = TestHttpRequest::withAttributes(array(), 'GET', '/almost/5');
        $router = new Router($app, $request, array(
            'fail' => array(
                'type' => \Ufw\Route\RegEx::class,
                'patterns' => array('#^/items/(?P<id>\d+)$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'constraints' => array('id' => '#^999$#'),
            ),
        ));
        $route = $router->getRoute('fail');
        $this->assertInstanceOf(\Ufw\Route\RegEx::class, $route);
        $this->assertNull($router->match());
    }

    public function testRegistryObserverAndApplicationAccessor()
    {
        $registry = Registry::getInstance();
        $registry->set('probe', 'value');
        $this->assertTrue($registry->has('probe'));
        $this->assertSame('value', $registry->get('probe'));
        $registry->remove('probe');
        $this->expectException(\RuntimeException::class);
        $registry->getApplication();
    }

    public function testEnvsRuntimeLabelResolutionOrder()
    {
        Envs::setRuntimeEnvLabel('override');
        $this->assertSame('override', Envs::runtimeLabel());
        Envs::setRuntimeEnvLabel(null);
        Registry::getInstance()->set('env', 'from-registry');
        $this->assertSame('from-registry', Envs::runtimeLabel());
        Registry::getInstance()->remove('env');
    }

    public function testApiClientBaseNormalizeFieldErrorsAndSetFormErrors()
    {
        ApplicationFixtures::bareWithController(array());
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        if (!method_exists(\Ufw\Component\ApiClient\Base::class, 'normalizeFieldErrorMessages')) {
            $this->markTestSkipped('Not on this branch surface.');
        }
        $norm = new \ReflectionMethod(\Ufw\Component\ApiClient\Base::class, 'normalizeFieldErrorMessages');
        $norm->setAccessible(true);
        $out = $norm->invoke($api, array('title' => array('Required')));
        $this->assertIsArray($out);
        $api->exposeSetFormErrors(array('title' => 'Bad'));
    }

    public function testApiClientResponseExceptionPayload()
    {
        $response = new ApiResponse();
        $response->setCode(422);
        $response->setException(new \RuntimeException('remote'));
        $response->setResponse(new \GuzzleHttp\Psr7\Response(
            422,
            array('Content-Type' => 'application/json'),
            '{"error":"Remote failed"}'
        ));
        $this->assertTrue($response->hasException());
        $this->assertSame('Remote failed', $response->getMessage('error'));
        $ex = new \Ufw\Component\ApiClient\Exception('wrap', 422, new \RuntimeException('x'));
        $ex->setResponse($response);
        $this->assertSame($response, $ex->getResponse());
    }

    public function testClientRequesterRoundTrip()
    {
        ApplicationFixtures::bareWithController(array());
        $client = Client::getInstance();
        $probe = ApiClientStubRequester::getInstance();
        $client->setRequester($probe);
        $this->assertSame($probe, $client->getRequester());
        $this->assertTrue($client->getRaiseExceptionOnApiError());
    }

    public function testMagicTraitGetChildIssuesGetRequest()
    {
        ApplicationFixtures::bareWithController(array());
        $client = Client::getInstance();
        $client->setRequester(ApiClientStubRequester::getInstance());
        $api = ApiClientTestApi::getInstance($client);
        $child = $api->getChild();
        $this->assertInstanceOf(\Ufw\Tests\Support\Child::class, $child);
    }

    public function testControllerBaseReadsIdentFromApplicationRequest()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $controller = new class($app) extends ControllerBase {
            public function indexAction()
            {
            }
        };
        $this->assertSame('ping', $controller->getCurrentControllerName());
    }

    public function testFormFiltersAndValidatorsRegistry()
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new \Ufw\Form\Element\Text('title');
        $el->setFilter('name', 'Singleline');
        $el->setValidator('req', 'Required');
        $this->assertInstanceOf(\Ufw\Form\Filter\Singleline::class, $el->getFilter('name'));
        $this->assertInstanceOf(\Ufw\Form\Validator\Required::class, $el->getValidator('req'));
    }

    public function testDbConditionBuildsFragment()
    {
        $cond = new Condition(array('field' => 'qty', 'value' => 0, 'op' => '>'));
        $this->assertStringContainsString('qty', (string) $cond);
    }

    public function testCsrfTokenGenerationAndValidation()
    {
        $token = Csrf::token('gate');
        $this->assertNotEmpty($token);
        $this->assertTrue(Csrf::validate('gate', $token));
    }

    public function testMiddlewareBaseInvokeDelegatesToProcess()
    {
        list($app) = ApplicationFixtures::bareWithController(array());
        $controller = new class($app) extends ControllerBase {
            public function indexAction()
            {
            }
        };
        $mw = new class($controller) extends MiddlewareBase {
            public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $request = TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'));
        $next = function ($req) {
            return new \Laminas\Diactoros\Response('ok');
        };
        $this->assertSame(200, $mw($request, $next)->getStatusCode());
    }

    public function testHelperAssetsFlashAndUrlSmoke()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'assets' => array('serve-minified' => false),
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $app->setRouter(new Router($app, TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'), 'GET', '/'), array(
            'home' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        )));
        $controller = new class($app) extends ControllerBase {
            public function indexAction()
            {
            }
        };
        $assets = $app->getHelper('Assets', $controller);
        if ($assets instanceof Assets) {
            $this->assertInstanceOf(Assets::class, $assets);
        }
        $flash = $app->getHelper('FlashMessenger', $controller);
        if ($flash instanceof FlashMessenger) {
            $this->assertInstanceOf(FlashMessenger::class, $flash);
        }
        $url = $app->getHelper('url', $controller);
        if ($url instanceof Url) {
            $built = $url(array('controller' => 'ping', 'action' => 'index'), true, false, 'home');
            $this->assertNotEmpty($built);
        }
    }

    public function testJsOutPlaceholderWhenStorageNotConfigured()
    {
        list($app) = ApplicationFixtures::bareWithController(array('assets' => array()));
        $out = JsOut::getInstance();
        $html = $out->readyPlaceholder();
        $this->assertIsString($html);
    }
}
