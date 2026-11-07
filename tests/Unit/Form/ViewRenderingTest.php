<?php
namespace Ufw\Tests\Unit\Form;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Form\Element\Checkbox;
use Ufw\Form\Element\Csrf as CsrfElement;
use Ufw\Form\Element\Radio;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Helper\Bootstrap3plain;
use Ufw\Helper\Bootstrap4plain;
use Ufw\JsOut;
use Ufw\Middleware\Layout;
use Ufw\Paths;
use Ufw\Placeholder;
use Ufw\Request;
use Ufw\Response;
use Ufw\Router;
use Ufw\Route\Literal;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\ApiClientRequesterProbe;
use Ufw\Utils\Asset\Css;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class ViewRenderingTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientRequesterProbe::resetInstance();
        Paths::setApplicationPath(TestAppPaths::app());
        $ref = new \ReflectionClass(JsOut::class);
        $prop = $ref->getProperty('_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
        $ph = new \ReflectionClass(\Ufw\Helper\Placeholder::class);
        $container = $ph->getProperty('container');
        $container->setAccessible(true);
        $container->setValue(null, null);
        parent::tearDown();
    }

    public function testApplicationDispatchCleansOutputBufferOnControllerFailure()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'controllers' => array('ping', 'throwingPing'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $app->setDispatchControllers(array('ping', 'throwingPing'));
        $request = TestHttpRequest::withAttributes(array(
            'controller' => 'throwingPing',
            'action' => 'index',
        ));
        $this->expectException(\RuntimeException::class);
        $app->dispatch($request);
    }

    public function testApplicationRunHandlesUncaughtDispatchException()
    {
        $app = new class extends Application {
            public function dispatch($request = null)
            {
                throw new \RuntimeException('dispatch failed');
            }

            protected function dispatchError($action = 'not-found', $controller = 'error')
            {
                return $this;
            }
        };
        $app->setConfig(array('logging' => array('exception_log_path' => false)));
        \Ufw\Registry::getInstance()->set('application', $app);
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        ob_start();
        $app->run();
        ob_end_clean();
        $this->assertSame(404, $app->getResponse()->getStatusCode());
    }

    public function testApplicationLoggingHelpersSkipOrFormatContext()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'logging' => array('exception_log_path' => false),
        ));
        $log = new \ReflectionMethod(Application::class, 'logExceptionToFile');
        $log->setAccessible(true);
        $log->invoke($app, new \RuntimeException('x'), null);

        $app->setConfig(array(
            'logging' => array('exception_log_path' => 'custom/exceptions.log'),
        ));
        $format = new \ReflectionMethod(Application::class, 'formatRequestContextForLog');
        $format->setAccessible(true);
        $text = $format->invoke($app, null);
        $this->assertStringContainsString('uncaught exception', $text);

        $badUri = new class(array('controller' => 'ping', 'action' => 'index'), array(), array(), null, new ServerRequest()) extends Request {
            public function getUri()
            {
                throw new \RuntimeException('uri fail');
            }
        };
        $app->setRequest($badUri);
        $text2 = $format->invoke($app, 404);
        $this->assertStringContainsString('could not build URI', $text2);

        $globals = new \ReflectionMethod(Application::class, 'buildRequestContextLinesFromGlobals');
        $globals->setAccessible(true);
        $_SERVER = array('REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test');
        $lines = $globals->invoke($app);
        $this->assertNotSame(array(), $lines);
    }

    public function testPathsExceptionLogPathVariants()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $this->assertStringContainsString('exceptions.txt', Paths::exceptionLogFilePath(null));
        $this->assertNull(Paths::exceptionLogFilePath(array('logging' => array('exception_log_path' => false))));
        $this->assertNull(Paths::exceptionLogFilePath(array('logging' => array('exception_log_path' => ''))));
        $abs = Paths::exceptionLogFilePath(array('logging' => array('exception_log_path' => '/var/log/exc.log')));
        $this->assertSame('/var/log/exc.log', $abs);
        $winAbs = Paths::exceptionLogFilePath(array('logging' => array('exception_log_path' => 'C:\\logs\\exc.log')));
        $this->assertSame('C:\\logs\\exc.log', $winAbs);
        $rel = Paths::exceptionLogFilePath(array('logging' => array('exception_log_path' => 'log/custom.txt')));
        $this->assertStringContainsString('custom.txt', $rel);
    }

    public function testPathsApplicationRequiresConfiguredRoot()
    {
        Paths::setApplicationPath(null);
        $registry = \Ufw\Registry::getInstance();
        if ($registry->has('application_path')) {
            $registry->remove('application_path');
        }
        $this->expectException(\RuntimeException::class);
        Paths::application();
    }

    public function testFormRenderHelperBaseCustomViewScriptAndFieldBranches()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $helper = new Bootstrap4plain();
        $helper->setController($controller);

        $form = new class(array('action' => '/x', 'viewScript' => 'bootstrap4/form/form.php')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $this->assertNotSame('', trim($helper->form($form)));

        $csrf = new CsrfElement('token');
        $this->assertNotSame('', trim($helper->field($csrf)));

        $select = new Select('role', array('multiOptions' => array('a' => 'A')));
        $select->setMetadata(array('hint' => 'x'));
        $this->assertNotSame('', trim($helper->field($select)));

        $text = new Text('title');
        $text->setRenderScript('bootstrap4/form/input.php');
        $this->assertNotSame('', trim($helper->field($text)));
    }

    public function testBootstrapPlainHelpersUsePlainPartialsForChoiceControls()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());

        $b3 = new Bootstrap3plain();
        $b3->setController($controller);
        $cb = new Checkbox('agree');
        $this->assertStringContainsString('checkbox', $b3->field($cb));

        $radio = new Radio('pick', array('multiOptions' => array('a' => 'A')));
        $this->assertNotSame('', trim($b3->field($radio)));

        $b4 = new Bootstrap4plain();
        $b4->setController($controller);
        $radio->setRenderScript('bootstrap4plain/form/multi-radio.php');
        $this->assertNotSame('', trim($b4->field($radio)));
    }

    public function testJsOutStoragePipelineAndWrapperModes()
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-jsout-' . uniqid('', true);
        mkdir($tmp);
        list($app) = ApplicationFixtures::bareWithController(array(
            'jsOutStorage' => array(
                'webpath' => '/static/js/gc',
                'absrootpath' => $tmp,
                'serve-minified' => true,
                'readyWrapper' => 'jquery',
            ),
        ));
        $slot = $app->getHelper('placeholder')->placeholder('jsReady');
        $slot->append('console.log(1);');
        $app->getHelper('placeholder')->placeholder('jsExt')->append('var x=1;');

        $js = JsOut::getInstance();
        $out = $js->readyPlaceholder();
        $this->assertStringContainsString('.min.js', $out);

        $app->setConfig(array(
            'jsOutStorage' => array(
                'webpath' => '/static/js/gc',
                'absrootpath' => $tmp,
                'readyWrapper' => 'dom',
            ),
        ));
        $wrap = new \ReflectionMethod(JsOut::class, 'wrapJsReadyFragments');
        $wrap->setAccessible(true);
        $classic = $wrap->invoke($js, 'doWork();');
        $this->assertStringContainsString('__ufwDomReady', $classic);

        $app->setConfig(array(
            'jsOutStorage' => array(
                'webpath' => '/static/js/gc',
                'absrootpath' => $tmp,
                'readyWrapper' => 'none',
            ),
        ));
        $this->assertSame('plain();', $wrap->invoke($js, 'plain();'));

        $app->setConfig(array('jsOutStorage' => null));
        $placeholderOut = new \ReflectionMethod(JsOut::class, 'placeholderScriptsOnlyOutput');
        $placeholderOut->setAccessible(true);
        $app->getHelper('placeholder')->placeholder('jsInline')->append('inline();');
        $html = $placeholderOut->invoke($js);
        $this->assertStringContainsString('inline();', $html);
    }

    public function testJsOutUsesExistingBundleWhenMinFilePresent()
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-jsout-' . uniqid('', true);
        mkdir($tmp);
        list($app) = ApplicationFixtures::bareWithController(array(
            'jsOutStorage' => array(
                'webpath' => '/gc',
                'absrootpath' => $tmp,
                'serve-minified' => true,
            ),
        ));
        $app->getHelper('placeholder')->placeholder('jsReady')->append('a();');
        $app->getHelper('placeholder')->placeholder('jsExt')->append('b();');
        $js = JsOut::getInstance();
        $first = $js->readyPlaceholder();
        $this->assertStringContainsString('.min.js', $first);
        $second = $js->readyPlaceholder();
        $this->assertStringContainsString('.min.js', $second);
    }

    public function testApiClientRequesterExceptionBranchesInRawAndVerbs()
    {
        ApplicationFixtures::bareWithController();
        $server = new MockHandler(array(
            new ServerException('srv', new PsrRequest('GET', '/s'), new PsrResponse(500)),
        ));
        $client = new GuzzleClient(array('handler' => HandlerStack::create($server), 'base_uri' => 'http://api.test'));
        $requester = ApiClientRequesterProbe::getInstance();
        $requester->injectHttpClient($client);
        $requester->disableDebugLog();
        $requester->raw('GET', '/s', array());
        $this->assertTrue($requester->getLastResponse()->hasException());

        $clientEx = new MockHandler(array(
            new ClientException('cli', new PsrRequest('GET', '/c'), new PsrResponse(400)),
        ));
        $requester->injectHttpClient(new GuzzleClient(array('handler' => HandlerStack::create($clientEx), 'base_uri' => 'http://api.test')));
        $requester->get('/c');
        $this->assertTrue($requester->getLastResponse()->isClientException());

        $generic = new MockHandler(array(
            new \RuntimeException('generic'),
        ));
        $requester->injectHttpClient(new GuzzleClient(array(
            'handler' => HandlerStack::create($generic),
            'base_uri' => 'http://api.test',
        )));
        $requester->post('/g', array('x' => 1));
        $this->assertTrue($requester->getLastResponse()->hasException());
    }

    public function testLayoutMiddlewareRendersAjaxBareLayout()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app) = ApplicationFixtures::bareWithController();
        $response = new Response();
        $response->setFormat('html');
        $response->setContent('<p>inner</p>');
        $controller = new class($app, $response) extends \Ufw\Controller\Base {
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
        $app->setCurrentController($controller);
        $xhrServer = new ServerRequest(
            array('REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'),
            array(),
            '/',
            'GET',
            'php://input',
            array('X-Requested-With' => 'XMLHttpRequest')
        );
        $app->setRequest(new Request(array(
            'controller' => 'ping',
            'action' => 'index',
        ), array(), array(), null, $xhrServer));
        $layout = new Layout($controller);
        $handler = new class($response) implements \Psr\Http\Server\RequestHandlerInterface {
            /** @var Response */
            private $response;

            public function __construct(Response $response)
            {
                $this->response = $response;
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->response;
            }
        };
        $layout->process($xhrServer, $handler);
        $this->assertStringContainsString('<p>inner</p>', (string) $response->getContent());
    }

    public function testCssAssetCompilesNestedLocalResources()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $css = new Css();
        $css->setApplication($app)->init(array(
            'cdn' => array('https://cdn.example/lib.css'),
            'local' => array(
                array('/a.css', '/b.css'),
                '/single.css',
            ),
        ));
        $html = (string) $css;
        $this->assertStringContainsString('cdn.example', $html);
        $this->assertStringContainsString('single.css', $html);
    }
}
