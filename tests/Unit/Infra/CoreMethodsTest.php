<?php
namespace Ufw\Tests\Unit\Infra;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Base\Execute;
use Ufw\Component\ApiClient\Base\TraitD;
use Ufw\Component\ApiClient\Base\TraitR;
use Ufw\Component\ApiClient\Base\TraitRE;
use Ufw\Component\ApiClient\Base\TraitW;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Requester;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Form\Element\SubElementsAbstract;
use Ufw\Form\Element\Text;
use Ufw\Form\Validator\RegEx as RegExValidator;
use Ufw\Helper\Bootstrap4plain;
use Ufw\Helper\FormErrorsToFlashMessenger;
use Ufw\Helper\FlashMessenger;
use Ufw\Helper\JsTranslate;
use Ufw\Helper\Menu;
use Ufw\Helper\Render;
use Ufw\InfoHashArray;
use Ufw\JsOut;
use Ufw\Middleware\Base as MiddlewareBase;
use Ufw\Middleware\Dispatch;
use Ufw\Middleware\Layout;
use Ufw\Paths;
use Ufw\Request;
use Ufw\Response;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\ApiClientRequesterProbe;

class CoreMethodsTest extends TestCase
{
    use FormFixtureConfig;
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientRequesterProbe::resetInstance();
        parent::tearDown();
    }

    public function testApiClientRequesterSingletonAndProtectedHttpClientSetter()
    {
        ApplicationFixtures::bareWithController();
        $probe = GapRequesterProbe::getInstance();
        $this->assertSame($probe, GapRequesterProbe::getInstance());
        $client = new \GuzzleHttp\Client();
        $method = new \ReflectionMethod(Requester::class, 'setHttpClient');
        $method->setAccessible(true);
        $method->invoke($probe, $client);
    }

    public function testApiClientResponseServerAndRequestExceptionFlags()
    {
        $resp = new ApiResponse();
        $this->assertFalse($resp->isRequestException());
        $this->assertFalse($resp->isServerError());
        $req = new PsrRequest('GET', '/');
        $http = new PsrResponse(500);
        $resp->setException(new ServerException('srv', $req, $http));
        $this->assertTrue($resp->isServerError());
        $resp->setException(new RequestException('req', $req));
        $this->assertTrue($resp->isRequestException());
    }

    public function testApiClientExecuteInvokeAndCrudTraits()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        Client::getInstance()->setRequester(ApiClientRequesterProbe::getInstance());

        $execute = GapExecuteProbe::getInstance(Client::getInstance());
        $execute();
        $this->assertSame($execute, $execute->run());

        $rw = GapApiRwProbe::getInstance(Client::getInstance());
        $rw->read(true);
        $rw->forget();
        $rw->write();
        $rw->remove();

        $re = GapApiReProbe::getInstance(Client::getInstance());
        $re->read(false);
        $re->forget();
    }

    public function testControllerBaseUserAndLayoutMutators()
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $controller->setLayout('default');
        $this->assertSame('default', $controller->getLayout());
        $this->assertInstanceOf(\Ufw\Helper\User::class, $controller->getUser());
    }

    public function testFormLayerMetadataMutatorsAndSubElementTrait()
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        Paths::setApplicationPath(TestAppPaths::app());

        $form = new GapFormProbe();
        $form->setAutoViewScript(false);
        $form->setValidated(true);
        $this->assertSame($form, $form->getForm());
        $this->assertNotSame('', $form->getId());

        $text = new Text('title');
        $text->setType('text');
        $text->setValueUnfiltered("a\nb");
        $this->assertSame("a\nb", $text->getValueUnfiltered());

        $form->setElements(array('title' => $text));
        $text->addFilter('Trim');
        $this->assertTrue($text->hasFilter('Trim'));
        $text->removeFilter('Trim');
        $form->removeOption('nope');
        $this->assertSame(array(), $form->getOptions());
        $opts = $form->getOptionsToAttribs();
        $this->assertIsArray($opts);

        $form->setDisableTranslator(true);
        $this->assertTrue($form->getDisableTranslator());
        $form->setDisableTranslator(false);

        $regex = new RegExValidator('/^a$/');
        $this->assertSame('/^a$/', $regex->getPattern());
        $templates = $regex->getMessageTemplates();
        $this->assertIsArray($templates);
        $vars = $regex->getMessageVariables();
        $this->assertIsArray($vars);

        $text->addValidators(array(array('name' => 'Required')));
        $this->assertTrue($text->hasValidator('Required'));
        $text->removeValidator('Required');
        $text->setValidators(array());

        $file = new \Ufw\Form\Element\File('doc');
        $file->setForm($form);
        $file->setStoredFilename('stored.pdf');
        $this->assertSame('stored.pdf', $file->getStoredFilename());

        $composite = new class extends SubElementsAbstract {
            protected $type = 'text';

            public function __construct()
            {
                parent::__construct('wrap');
                $this->subEl = array(new Text('inner'));
            }
        };
        $composite->setForm($form);
        $composite->setValue(array('inner' => 'v'), array('inner' => 'v'));
        $this->assertTrue($composite->isValid(array('inner' => 'v')));

        $text->setAttrib('x', '1');
        $text->removeAttrib('x');
        $this->assertFalse($text->hasAttrib('x'));

        $baseInitForm = new class(array('action' => '/base-init-only')) extends \Ufw\Form\Base {
        };
        $this->assertSame('/base-init-only', $baseInitForm->getAction());
    }

    public function testHelpersFormMenuRenderAndJsTranslate()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $text = new Text('fld');
        $text->setValue('1');

        $plain = new Bootstrap4plain();
        $plain->setController($controller);
        $plain();
        $this->assertNotSame('', trim($plain->field($text)));

        $bridge = new FormErrorsToFlashMessenger();
        $bridge->setController($controller);
        $emptyForm = new GapFormProbe();
        $bridge($emptyForm);

        $menu = $app->getHelper('Menu', $controller);
        if ($menu instanceof Menu) {
            $menu->setDefinition(array('home' => array('label' => 'Home', 'uri' => '/')));
            $this->assertIsArray($menu->getDefinition());
        }

        $render = $app->getHelper('Render', $controller);
        $this->assertInstanceOf(Render::class, $render);
        $this->assertInstanceOf(\Negotiation\Negotiator::class, $render->getNegotiator());
        $render->renderScript(array('x' => 1), 'ping/index.php', false);

        $jsTr = new JsTranslate();
        $jsTr->setController($controller);
        $this->assertSame('""', $jsTr(''));
    }

    public function testMiddlewareBaseDispatchAndLayoutProcess()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $controller = new class($app) extends ControllerBase {
            public function indexAction()
            {
            }

            public function getResponse()
            {
                return new Response();
            }
        };
        $controller->setCurrentActionMethod('indexAction');
        $app->setCurrentController($controller);
        $app->setRequest(TestHttpRequest::withAttributes(array(
            'controller' => 'ping',
            'action' => 'index',
        ), 'GET', '/'));

        $mw = new class($controller) extends MiddlewareBase {
            public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
            {
                return $handler->handle($request);
            }

            public function exposeUrlHelper()
            {
                return $this->getHelper('Url');
            }

            public function exposeApplication()
            {
                return $this->getApplication();
            }
        };
        $this->assertSame($app, $mw->exposeApplication());
        $this->assertNotNull($mw->exposeUrlHelper());

        $dispatch = new Dispatch($controller);
        $handler = new class($app) implements \Psr\Http\Server\RequestHandlerInterface {
            /** @var \Ufw\Application */
            private $app;

            public function __construct(\Ufw\Application $app)
            {
                $this->app = $app;
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->app->getResponse();
            }
        };
        $dispatch->process(TestHttpRequest::withAttributes(array(), 'GET', '/'), $handler);

        $response = new Response();
        $response->setFormat('html');
        $response->setContent('body');
        $controllerWithResp = new class($app, $response) extends ControllerBase {
            /** @var Response */
            private $resp;

            public function __construct(\Ufw\Application $app, Response $resp)
            {
                parent::__construct($app);
                $this->resp = $resp;
            }

            public function getResponse()
            {
                return $this->resp;
            }
        };
        $app->setCurrentController($controllerWithResp);
        $xhr = TestHttpRequest::withAttributes(array(), 'GET', '/');
        $layout = new Layout($controllerWithResp);
        $layout->process($xhr, $handler);
    }

    public function testRequestHttpVerbsAndPrevRequestChain()
    {
        $psr = new ServerRequest(array(), array(), '/x', 'DELETE');
        $request = new Request(array(), array(), array(), null, $psr);
        $this->assertTrue($request->isDelete());
        $psr = $psr->withMethod('HEAD');
        $request->setServerRequest($psr);
        $this->assertTrue($request->isHead());
        $psr = $psr->withMethod('PUT');
        $request->setServerRequest($psr);
        $this->assertTrue($request->isPut());
        $prev = new Request(array(), array(), array());
        $request->setPrevRequest($prev);
        $this->assertSame($prev, $request->getPrevRequest());
    }

    public function testResponsePsr7SurfaceAndEmit()
    {
        ApplicationFixtures::bareWithController();
        $response = new Response();
        $response->setContent('ok');
        $response->setFormat('html');
        $stream = new Stream('php://memory', 'wb+');
        $response->withBody($stream);
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('', $response->getHeaderLine('Missing'));
        $this->assertFalse($response->hasHeader('Missing'));
        $this->assertSame('1.1', $response->getProtocolVersion());
        $this->assertSame('OK', $response->getReasonPhrase());
        $response->withHeader('Content-Type', 'text/plain');
        $this->assertSame(array('text/plain'), $response->getHeader('Content-Type'));
        ob_start();
        $response->emit();
        ob_end_clean();
    }

    public function testServerRequestValidateUploadedFiles()
    {
        $method = new \ReflectionMethod(ServerRequest::class, 'validateUploadedFiles');
        $method->setAccessible(true);
        $req = new ServerRequest();
        $method->invoke($req, array());
        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($req, array(new \stdClass()));
    }

    public function testInfoHashArrayAddEntryAndJsOutReadyTypeAttribute()
    {
        $hash = new InfoHashArray();
        $hash->add_entry_array(array('id' => 1, 'name' => 'alpha'));
        $js = JsOut::getInstance();
        $ref = new \ReflectionMethod(JsOut::class, 'getReadyScriptTypeAttribute');
        $ref->setAccessible(true);
        $this->assertStringContainsString('module', $ref->invoke($js));
    }

    public function testReCaptchaValidatorSiteKeyAndFlashMessengerOffPath()
    {
        ApplicationFixtures::bareWithController();
        $validator = new \Ufw\Form\Validator\ReCaptcha('secret', 'site');
        $this->assertSame('site', $validator->getSiteKey());

        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        @session_start();
        $_SESSION = array();
        $flash = new FlashMessenger();
        $flash->off();
        $flash->addMessage(null);
        $this->assertSame(array(), $flash->getCurrentMessages());
    }
}

class GapRequesterProbe extends Requester
{
    protected function getApiEndpointConfig()
    {
        return array('base_uri' => 'http://req.test');
    }
}

class GapExecuteProbe extends Execute
{
    protected function _do()
    {
        return null;
    }
}

class GapApiRwProbe extends ApiClientTestApi
{
    use TraitR;
    use TraitW;
    use TraitD;

    protected function _doRead()
    {
        return null;
    }

    protected function _doWrite()
    {
        return null;
    }

    protected function _doRemove()
    {
        return null;
    }
}

class GapApiReProbe extends ApiClientTestApi
{
    use TraitRE;

    protected function _doRead()
    {
        return null;
    }
}

class GapFormProbe extends \Ufw\Form\Base
{
    public function init()
    {
        $this->setMethod('post');
        $this->setAction('/gap-form');
    }
}
