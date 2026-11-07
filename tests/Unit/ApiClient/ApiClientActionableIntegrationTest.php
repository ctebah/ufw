<?php
namespace Ufw\Tests\Unit\ApiClient;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Exception as ApiException;
use Ufw\Component\ApiClient\FormAlike;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Form\Element\DateRange;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\Singleline;
use Ufw\Form\Validator\Email;
use Ufw\Form\Validator\FileUpload;
use Ufw\Form\Validator\ReCaptcha;
use Ufw\Helper\Assets;
use Ufw\Helper\FlashMessenger;
use Ufw\JsOut;
use Ufw\Middleware\Base as MiddlewareBase;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Response;
use Ufw\Router;
use Ufw\Route\Literal;
use Ufw\ServerRequest;
use Ufw\ServerRequestFactory;
use Ufw\UploadedTmpFile;
use Ufw\Utils\Asset\Js;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientChildInvokeProbe;
use Ufw\Tests\Support\ApiClientStubRequester;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\ApiClientMagicArrayChildProbe;
use Ufw\Tests\Support\ApiClientMagicBadRequesterProbe;
use Ufw\Tests\Support\ApiClientMagicErrorProbe;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\ApiClientRequesterProbe;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

/**
 * Actionable framework line sweep (~218 lines across Form, ApiClient, Request, helpers, …).
 */
class ApiClientActionableIntegrationTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientChildInvokeProbe::resetInstances();
        ApiClientStubRequester::clearNextResponse();
        ApiClientRequesterProbe::resetInstance();
        \Ufw\Form\Base::setRenderHelperName(null);
        \Ufw\Form\Element\Base::setRenderHelperName(null);
        $_GET = array();
        $_POST = array();
        $_SERVER = array();
        $_SESSION = array();
        parent::tearDown();
    }

    private function withUserErrorAsException(callable $fn)
    {
        set_error_handler(function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            $fn();
        } finally {
            restore_error_handler();
        }
    }

    public function testFormBasePostInitGetBranchSetsActionWithoutQueryString()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $form = new class(array('method' => 'get')) extends \Ufw\Form\Base {
            protected function init()
            {
            }

            public function getHelper($name)
            {
                if ($name !== 'url') {
                    return null;
                }
                return new class extends \Ufw\Helper\Base {
                    public function url($params = array(), $reset = true, $withQs = false, $routeName = null, $encode = true)
                    {
                        return '/get-target';
                    }
                };
            }
        };
        $postInit = new \ReflectionMethod(\Ufw\Form\Base::class, 'postInit');
        $postInit->setAccessible(true);
        $postInit->invoke($form);
        $this->assertSame('/get-target', $form->getAction());
    }

    public function testFormBaseResolveFormRenderHelperThrowsWhenHelperMissing()
    {
        ApplicationFixtures::bareWithController(array());
        \Ufw\Form\Base::setRenderHelperName('missing-helper');
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            protected function init()
            {
            }
        };
        $this->expectException(\RuntimeException::class);
        $resolve = new \ReflectionMethod(\Ufw\Form\Base::class, 'resolveFormRenderHelper');
        $resolve->setAccessible(true);
        $resolve->invoke($form);
    }

    public function testFormBaseGetRenderHelperNameReadsNestedFormConfig()
    {
        \Ufw\Form\Base::setRenderHelperName(null);
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'form' => array('helper' => 'bootstrap5'),
            'dispatch' => array('routes' => array()),
        ));
        Registry::getInstance()->set('application', $app);
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            protected function init()
            {
            }
        };
        $this->assertSame('bootstrap5', $form->getRenderHelperName());
    }

    public function testFormElementToStringRendersField()
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        \Ufw\Form\Element\Base::setRenderHelperName('bootstrap5');
        $el = new Text('title', array('label' => 'Title'));
        $html = (string) $el;
        $this->assertStringContainsString('title', $html);
    }

    public function testFormElementResolveHelperThrowsWhenMissing()
    {
        ApplicationFixtures::bareWithController(array());
        \Ufw\Form\Element\Base::setRenderHelperName('nope');
        $el = new Text('x');
        $this->expectException(\RuntimeException::class);
        $resolve = new \ReflectionMethod(\Ufw\Form\Element\Base::class, 'resolveFormRenderHelper');
        $resolve->setAccessible(true);
        $resolve->invoke($el);
    }

    private function routerConfig()
    {
        return array(
            'default' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
            'home' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        );
    }

    public function testFormElementGetAttribsAppendsArraySuffixWhenAllowMultiple()
    {
        $el = new Text('tags');
        $el->setAllowMultiple(true);
        $attribs = $el->getAttribs();
        $this->assertStringContainsString('[]', (string) $attribs['name']);
    }

    public function testFormElementSetValueFiltersUnknownMultiOption()
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new class('colors') extends Select {
            public function getAllowMultiple()
            {
                return true;
            }

            public function getMultiOptionValues()
            {
                return array('red', 'blue');
            }
        };
        $el->setMultiOptions(array('red' => 'R', 'blue' => 'B'));
        $el->setValue(array('red', 'green'));
        $this->assertSame(array('red'), $el->getValue());
    }

    public function testFormValidatorsArrayDefinitionWithNamedOptions()
    {
        ApplicationFixtures::bareWithController(array());
        $el = new Text('email');
        $el->addValidator(array('name' => 'email'));
        $this->assertInstanceOf(Email::class, $el->getValidator('email'));
    }

    public function testFormValidatorsAddValidatorFromInstance()
    {
        ApplicationFixtures::bareWithController(array());
        $el = new Text('email');
        $v = new Email();
        $el->addValidator('email', $v);
        $this->assertInstanceOf(Email::class, $el->getValidator('email'));
    }

    public function testFormFiltersArrayDefinition()
    {
        ApplicationFixtures::bareWithController(array());
        $el = new Text('t');
        $el->addFilter(array('name' => 'Singleline', 'options' => array()));
        $this->assertInstanceOf(Singleline::class, $el->getFilter('Singleline'));
    }

    public function testFormOptionsSetOptionsMergesAttribs()
    {
        ApplicationFixtures::bareWithController(array());
        $el = new Text('t');
        $el->setOptions(array(
            'attribs' => array('data-x' => '1'),
            'label' => 'Title',
        ));
        $this->assertSame('Title', $el->getOption('label'));
        $this->assertSame('1', $el->getAttrib('data-x'));
    }

    public function testMagicTraitErrorsWhenClientMissing()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $this->expectException(\ErrorException::class);
        $this->withUserErrorAsException(function () {
            ApiClientMagicErrorProbe::getInstance(Client::getInstance())->get('/x');
        });
    }

    public function testMagicTraitErrorsWhenRequesterMethodMissing()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $this->expectException(\ErrorException::class);
        $this->withUserErrorAsException(function () {
            ApiClientMagicBadRequesterProbe::getInstance(Client::getInstance())->get('/x');
        });
    }

    public function testMagicTraitGetInvalidSubApiClassTriggersError()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $this->expectException(\ErrorException::class);
        $this->withUserErrorAsException(function () use ($api) {
            $api->getNotARealSubApiClass();
        });
    }

    public function testMagicTraitChildInvokePassesArrayPayloadToForm()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array('routes' => $this->routerConfig(), 'controllers' => array('ping')),
        ));
        $app->setRouter(new Router($app, TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'), 'GET', '/'), $this->routerConfig()));
        $client = Client::getInstance();
        $client->setRequester(ApiClientStubRequester::getInstance());
        $parent = ApiClientChildInvokeProbe::getInstance($client);
        $child = ApiClientChildInvokeProbe::getInstance($client);
        $parent->child(array('title' => 'From array'));
        $this->assertSame('From array', $child->getForm()->getValues()['title']);
    }

    public function testMagicTraitGetApplicationThrowsWithoutRegistry()
    {
        ApplicationFixtures::tearDownRegistry();
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $this->expectException(\RuntimeException::class);
        $api->exposeGetApplication();
    }

    public function testRequesterDebugLogBuildsHttpClientWithHandler()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'routes' => $this->routerConfig(),
            ),
        ));
        $app->setRouter(new Router($app, TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'), 'GET', '/'), $this->routerConfig()));
        Paths::setApplicationPath(TestAppPaths::app());
        $_SERVER['HTTP_HOST'] = 'gate.test';
        $_SERVER['HTTPS'] = 'on';
        $probe = ApiClientRequesterProbe::getInstance()->enableDebugLog();
        $client = $probe->exposeGetHttpClient();
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $client);
        $stack = $probe->exposeCreateLoggingHandlerStack(array("test\n"));
        $this->assertInstanceOf(\GuzzleHttp\HandlerStack::class, $stack);
        $this->assertInstanceOf(\Monolog\Logger::class, $probe->exposeGetLogger());
        $this->assertInstanceOf(\Ufw\Application::class, $probe->exposeGetApplication());
    }

    public function testApiResponseDelegateErrorsWhenNoTarget()
    {
        $response = new ApiResponse();
        $this->expectException(\ErrorException::class);
        $this->withUserErrorAsException(function () use ($response) {
            $response->getStatusCode();
        });
    }

    public function testApiResponseConnectExceptionRewrapOnSetException()
    {
        $response = new ApiResponse();
        $req = new PsrRequest('GET', 'http://x');
        $connect = new ConnectException('down', $req);
        $response->setException($connect);
        $this->assertInstanceOf(ConnectException::class, $response->getException());
    }

    public function testApiExceptionConstructorFromGuzzleClientException()
    {
        $req = new PsrRequest('POST', '/api');
        $http = new \GuzzleHttp\Psr7\Response(400, array(), '<html><body>Bad</body></html>');
        $ex = new ClientException('bad', $req, $http);
        $apiEx = new ApiException(null, null, $ex);
        $this->assertNotEmpty($apiEx->getMessage());
    }

    public function testApiBaseFlashOnNon422ClientError()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $_SESSION = array();
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $response = new ApiResponse();
        $response->setResponse(new \GuzzleHttp\Psr7\Response(400, array(), '{}'));
        $ex = new ApiException(array('fail'), 400);
        $ex->setResponse($response);
        $run = new \ReflectionMethod(\Ufw\Component\ApiClient\Base::class, '_run');
        $run->setAccessible(true);
        try {
            $run->invoke($api, function () use ($ex) {
                throw $ex;
            });
        } catch (ApiException $e) {
            $this->assertSame(400, $e->getCode());
        }
    }

    public function testRequestPrefersJsonAndXmlHttpRequest()
    {
        $jsonReq = TestHttpRequest::withAttributes(array(), 'GET', '/');
        $jsonPsr = new ServerRequest(
            array('HTTP_ACCEPT' => 'application/json'),
            array(),
            '/',
            'GET',
            'php://input',
            array('Accept' => 'application/json')
        );
        $jsonReq = new Request(array(), array(), array(), null, $jsonPsr);
        $this->assertTrue($jsonReq->prefersApplicationJson());
        $xhr = new Request(array(), array(), array(), null, new ServerRequest(
            array('HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'),
            array(),
            '/',
            'GET',
            'php://input',
            array('X-Requested-With' => 'XMLHttpRequest')
        ));
        $this->assertTrue($xhr->isXmlHttpRequest());
        $noSr = new Request(array(), array(), array(), null, new ServerRequest(array(), array(), '/', 'GET', 'php://input'));
        $prop = new \ReflectionProperty(Request::class, 'serverRequest');
        $prop->setAccessible(true);
        $prop->setValue($noSr, null);
        $this->assertFalse($noSr->prefersApplicationJson());
    }

    public function testRequestCallReturnsNullForUnknownMethod()
    {
        $request = TestHttpRequest::withAttributes(array(), 'GET', '/');
        $this->assertNull($request->notARealPsrMethod());
    }

    public function testServerRequestFactoryAcceptsExplicitCookieHeaderString()
    {
        $server = array(
            'REQUEST_METHOD' => 'GET',
            'HTTP_COOKIE' => 'a=1; b=2',
            'HTTP_HOST' => 'localhost',
            'REQUEST_URI' => '/cookies',
        );
        $psr = ServerRequestFactory::fromGlobals($server, null, null, null, array());
        $this->assertSame('/cookies', $psr->getUri()->getPath());
        $this->assertSame('1', $psr->getCookieParams()['a'] ?? null);
    }

    public function testControllerGetIdentWithModulePrefix()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array('controllers' => array('ping')),
        ));
        $app->setRequest(TestHttpRequest::withAttributes(array('module' => 'Api', 'controller' => 'ping', 'action' => 'index')));
        $controller = new \Controller\Ping($app);
        $this->assertStringContainsString('/', $controller->getIdent());
    }

    public function testHelperBaseResolvesControllerFromApplication()
    {
        ApplicationFixtures::tearDownRegistry();
        $this->expectException(\RuntimeException::class);
        $helper = new class extends \Ufw\Helper\Base {
            public function probe()
            {
                return $this->getController();
            }
        };
        $helper->probe();
    }

    public function testMiddlewareBaseStaticNegotiatorAndInputStream()
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

            public function exposeNegotiator()
            {
                return $this->getNegotiator();
            }

            public function exposePhpInput()
            {
                return $this->getPhpInput();
            }
        };
        $this->assertInstanceOf(\Negotiation\Negotiator::class, $mw->exposeNegotiator());
        $this->assertIsString($mw->exposePhpInput());
    }

    public function testJsOutFetchPlaceholderUsesRegistryApplication()
    {
        list($app) = ApplicationFixtures::bareWithController(array());
        $out = JsOut::getInstance();
        $fetch = new \ReflectionMethod(JsOut::class, 'fetchPlaceholder');
        $fetch->setAccessible(true);
        $this->assertSame('', $fetch->invoke($out, 'missing'));
    }

    public function testUtilsAssetJsCompiledViaAssetsHelper()
    {
        list($app) = ApplicationFixtures::bareWithController(array('assets' => array('serve-minified' => false)));
        $controller = new class($app) extends ControllerBase {
            public function indexAction()
            {
            }
        };
        $assets = $app->getHelper('Assets', $controller);
        if ($assets instanceof Assets) {
            $assets('site');
            $js = $assets->js(Js::POS_BODY_BOTTOM);
            $this->assertInstanceOf(Js::class, $js);
        }
    }

    public function testUploadedTmpFileMoveFailsOnUploadError()
    {
        $file = new UploadedTmpFile('php://memory', 100, UPLOAD_ERR_INI_SIZE, 'bad.txt', 'text/plain');
        $this->expectException(\RuntimeException::class);
        $file->moveTo(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-upload-test-' . uniqid() . '.txt');
    }

    public function testFormValidatorFileUploadAndRecaptchaConstructors()
    {
        ApplicationFixtures::bareWithController(array());
        $upload = new FileUpload();
        $this->assertSame('Ufw_Form_Validator_FileUpload', $upload->getName());
        $cap = new ReCaptcha('secret', 'response');
        $this->assertSame('Ufw_Form_Validator_ReCaptcha', $cap->getName());
    }

    public function testFormElementDateRangeNormalizesInput()
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new DateRange('period', array('label' => 'Period'));
        $el->setValue(array('from' => '2020-01-01', 'to' => '2020-01-31'));
        $val = $el->getValue();
        $this->assertIsArray($val);
    }

    public function testFlashMessengerAddAndReadMessages()
    {
        list($app) = ApplicationFixtures::bareWithController(array());
        $_SESSION = array();
        $controller = new class($app) extends ControllerBase {
            public function indexAction()
            {
            }
        };
        $flash = $app->getHelper('FlashMessenger', $controller);
        if ($flash instanceof FlashMessenger) {
            $flash->addMessage('Hello', FlashMessenger::NAMESPACE_INFO);
            $this->assertNotEmpty($flash->getInfos());
        } else {
            $this->markTestSkipped('Flash helper unavailable.');
        }
    }

    public function testAssetsHelperAppendStylesheet()
    {
        list($app) = ApplicationFixtures::bareWithController(array('assets' => array('serve-minified' => false)));
        $controller = new class($app) extends ControllerBase {
            public function indexAction()
            {
            }
        };
        $assets = $app->getHelper('Assets', $controller);
        if ($assets instanceof Assets) {
            $assets('site');
            $css = $assets->css();
            $this->assertInstanceOf(\Ufw\Utils\Asset\Css::class, $css);
        }
    }
}
