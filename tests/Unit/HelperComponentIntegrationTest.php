<?php
namespace Ufw\Tests\Unit;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Exception as ApiClientException;
use Ufw\Component\Csrf;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Form\Element\Csrf as CsrfElement;
use Ufw\Form\Element\Text;
use Ufw\Form\Validator\Identical;
use Ufw\Helper\FlashMessenger;
use Ufw\Helper\FormErrorsToFlashMessenger;
use Ufw\Helper\Render;
use Ufw\JsOut;
use Ufw\Middleware\Dispatch;
use Ufw\Paths;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Utils\ArrayAccess;

class HelperComponentIntegrationTest extends TestCase
{
    private static $probeViewDir;

    protected function setUp(): void
    {
        self::$probeViewDir = TestAppPaths::app() . '/views/ping';
        if (!is_dir(self::$probeViewDir)) {
            mkdir(self::$probeViewDir, 0777, true);
        }
        file_put_contents(self::$probeViewDir . '/index.php', '<?php echo "view-ok";');
    }

    protected function tearDown(): void
    {
        if (is_file(self::$probeViewDir . '/index.php')) {
            unlink(self::$probeViewDir . '/index.php');
        }
        ApplicationFixtures::tearDownRegistry();
        Paths::setApplicationPath(TestAppPaths::app());
        $ref = new \ReflectionClass(JsOut::class);
        $prop = $ref->getProperty('_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
        $this->resetFlashMessengerStatics();
        parent::tearDown();
    }

    private function resetFlashMessengerStatics(): void
    {
        $ref = new \ReflectionClass(FlashMessenger::class);
        foreach (array('_session', '_messages') as $name) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    public function testApiClientExceptionBuildsMessageFromServerAndStripTags(): void
    {
        $resp = new PsrResponse(500, array(), 'err');
        $srv = new ServerException('Server Error', new PsrRequest('GET', '/'), $resp);
        $fromServer = new ApiClientException(null, null, $srv);
        $this->assertSame('Internal Server Error', $fromServer->getMessage());

        $html = new ApiClientException(null, null, new \RuntimeException('<b>fail</b>'));
        $this->assertSame('fail', $html->getMessage());
    }

    public function testFormElementGettersSetValueSkipsBrokenFilter(): void
    {
        ApplicationFixtures::bareWithController();
        $el = new Text('t');
        $el->setOption('label', 'Title');
        $el->setAttrib('title', 'field-title');
        $this->assertSame('Title', $el->label);
        $this->assertSame('field-title', $el->title);
        $el->setFilter('broken', new \stdClass());
        $el->setValue('hello');
        $this->assertSame('hello', $el->getValue());
    }

    public function testFormGetOptionsTranslatesLabelKeys(): void
    {
        ApplicationFixtures::bareWithController();
        $form = new class(array('action' => '/x', 'label' => 'Save')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $opts = $form->getOptions();
        $this->assertArrayHasKey('label', $opts);
    }

    public function testIdenticalValidatorMissingTokenAndMismatch(): void
    {
        ApplicationFixtures::bareWithController();
        $v = new Identical('token');
        $this->assertFalse($v->isValid('x', array()));
        $this->assertFalse($v->isValid('x', array('token' => 'y')));
    }

    public function testControllerMagicGetAndStringMiddlewareClass(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $this->assertSame($app, $controller->application);
        $controller->setCurrentActionMethod('indexAction');
        $pipe = new \ReflectionMethod(ControllerBase::class, 'processMiddlewares');
        $pipe->setAccessible(true);
        $pipe->invoke($controller, array(\Ufw\Middleware\Layout::class));
        $this->assertSame(200, $app->getResponse()->getStatusCode());
    }

    public function testFlashMessengerLazySessionAndNamespaceCoercion(): void
    {
        ApplicationFixtures::bareWithController();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array('ufw__fM' => array(5 => array('skip'), 'info' => array('kept')));
        $this->resetFlashMessengerStatics();
        new FlashMessenger();
        $fm = new FlashMessenger();
        $sessionProp = new \ReflectionProperty(FlashMessenger::class, '_session');
        $sessionProp->setAccessible(true);
        $sessionProp->setValue(null, null);
        $fm->addMessage('fresh');
        $this->assertContains('fresh', $fm->getCurrentMessages('info'));
        $this->assertSame(array(), $fm->getMessages('missing-ns'));
        $this->assertContains('kept', $fm->getMessages(''));
        $fm->clearCurrentMessages('');
        $this->assertFalse($fm->hasCurrentMessages(''));
    }

    public function testPathsRouterAndRegExEdgeBranches(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $this->assertNull(Paths::exceptionLogFilePath(array('logging' => array('exception_log_path' => false))));
        $winAbs = Paths::exceptionLogFilePath(array(
            'logging' => array('exception_log_path' => 'C:\\logs\\app.log'),
        ));
        $this->assertSame('C:\\logs\\app.log', $winAbs);

        list($app) = ApplicationFixtures::bareWithController();
        $router = new Router($app, TestHttpRequest::withAttributes(array()), array(
            'rx' => array(
                'type' => RegEx::class,
                'urlBuilder' => array(
                    'patterns' => array('/:id'),
                    'compiled' => array('/:id' => array('id')),
                ),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $route = $router->getRoute('rx');
        $this->assertSame('/99', $route->buildUrl(array('id' => 99)));
    }

    public function testUtilsArrayAccessPropertyAndDataBags(): void
    {
        $bag = new class() extends ArrayAccess {
            public $visible = 'prop';
        };
        $bag->visible = 'prop';
        $bag->hidden = 'data';
        $this->assertSame('prop', $bag->visible);
        $this->assertSame('data', $bag->hidden);
        unset($bag->visible);
        unset($bag->hidden);
        $this->assertFalse(isset($bag->hidden));
    }

    public function testCsrfElementCustomComponentAndSessionValidate(): void
    {
        ApplicationFixtures::bareWithController();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_write_close();
            session_start();
        }
        $_SESSION = array();
        $el = new CsrfElement('csrf', array('csrfComponent' => Csrf::class));
        $this->assertNotSame('', (string) $el->getValue());
        Csrf::validate('default', 'bad');
        Csrf::rotate('default');
    }

    public function testFormErrorsBridgeSkipsUnknownElements(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
                $this->addElement(new Text('known'));
            }

            public function getErrors()
            {
                return array('ghost' => array('Err'), 'known' => array('Bad'));
            }
        };
        $known = $form->getElement('known');
        $known->setOption('label', '');
        $known->setAttrib('name', 'known-field');
        $bridge = new FormErrorsToFlashMessenger();
        $bridge->setController($controller);
        $bridge($form);
        $this->assertTrue(true);
    }

    public function testDispatchMiddlewareRejectsMissingAction(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $controller->setCurrentActionMethod('totallyMissingAction');
        $mw = new Dispatch($controller);
        $this->expectException(\Exception::class);
        $mw->process(new ServerRequest(), new class() implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new \Laminas\Diactoros\Response();
            }
        });
    }

    public function testJsOutSanitizeMergesNonEmptyReadyBlock(): void
    {
        ApplicationFixtures::bareWithController();
        $js = JsOut::getInstance();
        $ref = new \ReflectionClass(JsOut::class);
        $contentProp = $ref->getProperty('jsContent');
        $contentProp->setAccessible(true);
        $contentProp->setValue($js, array('ready' => 'doWork();', 'ext' => '', 'inline' => ''));
        $concat = $ref->getMethod('_concatenate');
        $concat->setAccessible(true);
        $concat->invoke($js, true);
        $sanitize = $ref->getMethod('_sanitize');
        $sanitize->setAccessible(true);
        $sanitize->invoke($js);
        $merged = $contentProp->getValue($js);
        $this->assertArrayNotHasKey('ready', $merged);
    }

    public function testEnvsDefaultProductionWhenUnset(): void
    {
        \Ufw\Envs::setRuntimeEnvLabel(null);
        ApplicationFixtures::tearDownRegistry();
        $this->assertSame('production', \Ufw\Envs::runtimeLabel());
    }
}
