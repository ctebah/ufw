<?php
namespace Ufw\Tests\Unit\Integration;

use Ufw\Tests\Support\TestAppPaths;

use Laminas\Diactoros\Response as DiactorosResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Component\Csrf as CsrfComponent;
use Ufw\Form\Element\Checkbox;
use Ufw\Form\Element\Html;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\Singleline;
use Ufw\Helper\Bootstrap3plain;
use Ufw\Helper\Bootstrap4;
use Ufw\Helper\Reload;
use Ufw\Middleware\JsonResponse;
use Ufw\Middleware\Layout;
use Ufw\Paths;
use Ufw\Response;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\ServerRequestFactory;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\UploadedTmpFile;

/**
 * Targets classes/methods that PHPUnit summary often leaves below full coverage.
 */
class PeripheralClassesTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testCheckboxValueMappingAndCheckedState()
    {
        ApplicationFixtures::bareWithController();
        $cb = new Checkbox('agree');
        $cb->setCheckedValue('yes')->setUncheckedValue('no');
        $this->assertSame('yes', $cb->getCheckedValue());
        $this->assertSame('no', $cb->getUncheckedValue());
        $cb->setValue('yes');
        $this->assertTrue($cb->getChecked());
        $cb->setValue('no');
        $this->assertFalse($cb->getChecked());
        $cb->setChecked(true);
        $this->assertTrue($cb->getChecked());
    }

    public function testHtmlElementRendersMarkup()
    {
        ApplicationFixtures::bareWithController();
        Paths::setApplicationPath(TestAppPaths::app());
        $html = new Html('note');
        $html->setValue('<p>hi</p>');
        $this->assertStringContainsString('hi', (string) $html);
    }

    public function testSinglelineFilterCollapsesWhitespace()
    {
        $filter = new Singleline();
        $this->assertSame("a  b", $filter->filter("a\n\nb"));
    }

    public function testUploadedTmpFileDeleteOnMoveFlag()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'payload');
        rewind($stream);
        $upload = new UploadedTmpFile($stream, 0, UPLOAD_ERR_OK);
        $this->assertTrue($upload->getDeleteOnMove());
        $upload->setDeleteOnMove(false);
        $this->assertFalse($upload->getDeleteOnMove());

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-upload-' . uniqid('', true) . '.txt';
        $upload->moveTo($target);
        $this->assertFileExists($target);
        $this->assertSame('payload', file_get_contents($target));
        @unlink($target);
    }

    public function testServerRequestFactoryBuildsUfwRequest()
    {
        $psr = ServerRequestFactory::fromGlobals(
            array('REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/ping'),
            array('q' => '1'),
            array(),
            array(),
            array()
        );
        $this->assertInstanceOf(\Ufw\ServerRequest::class, $psr);
        $this->assertSame('GET', $psr->getMethod());
    }

    public function testRegExRouteCompilesUrlBuilderPlaceholders()
    {
        $app = (new \ReflectionClass(\Ufw\Application::class))->newInstanceWithoutConstructor();
        $request = new \Ufw\Request(array(), array(), array(), null, new \Ufw\ServerRequest(array(), array(), '/x', 'GET', 'php://input'));
        $router = new Router($app, $request, array());
        $route = new RegEx($router, array(
            'patterns' => array('#^/item/(?P<id>\d+)$#'),
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'urlBuilder' => array('/item/:id'),
        ));
        $url = $route->buildUrl(array('id' => '42'));
        $this->assertStringContainsString('42', $url);
    }

    public function testLayoutMiddlewareDelegatesWhenLayoutDisabled()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $response = new Response();
        $response->setFormat('json');
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
        $controller->setDisableLayout(true);
        $middleware = new Layout($controller);
        $inner = new DiactorosResponse('ok');
        $handler = new class($inner) implements \Psr\Http\Server\RequestHandlerInterface {
            /** @var \Psr\Http\Message\ResponseInterface */
            private $inner;

            public function __construct(\Psr\Http\Message\ResponseInterface $inner)
            {
                $this->inner = $inner;
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->inner;
            }
        };
        $psr = new \Ufw\ServerRequest(array('REQUEST_METHOD' => 'GET'), array(), '/', 'GET');
        $out = $middleware->process($psr, $handler);
        $this->assertSame($inner, $out);
    }

    public function testJsonResponseMiddlewareSetsFormatWhenAcceptPrefersJson()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $response = new Response();
        $response->setFormat('html');
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
        $middleware = new JsonResponse($controller);
        $server = new \Ufw\ServerRequest(
            array('REQUEST_METHOD' => 'GET'),
            array(),
            '/api',
            'GET',
            'php://input',
            array('Accept' => 'application/json')
        );
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
        $middleware->process($server, $handler);
        $this->assertSame('json', $response->getFormat());
    }

    public function testFormTranslateUsesRegistryTranslator()
    {
        ApplicationFixtures::bareWithController();
        \Ufw\Registry::getInstance()->set('translator', function ($s) {
            return is_string($s) ? 't:' . $s : $s;
        });
        $text = new Text('label');
        $text->setOption('label', 'Save');
        $this->assertSame('t:Save', $text->getOption('label'));
    }

    public function testPathsApplicationAndExceptionLogDefaults()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $this->assertStringContainsString('fixture-app', Paths::application());
        $this->assertStringContainsString('exceptions.txt', Paths::exceptionLogFilePath());
    }

    public function testBootstrapFormHelpersRenderFieldPartial()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $text = new Text('title');
        $text->setValue('x');
        \Ufw\Form\Element\Base::setRenderHelperName('bootstrap4');
        $b4 = $app->getHelper('Bootstrap4', $controller);
        $this->assertInstanceOf(Bootstrap4::class, $b4);
        $html = $b4->field($text);
        $this->assertNotSame('', trim($html));
        $plain = $app->getHelper('Bootstrap3plain', $controller);
        \Ufw\Form\Element\Base::setRenderHelperName('bootstrap3plain');
        $this->assertInstanceOf(Bootstrap3plain::class, $plain);
        $this->assertStringContainsString('title', $plain->field($text));
    }

    public function testReloadHelperInvokesRedirect()
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array(
            'controller' => 'ping',
            'action' => 'index',
        )));
        $reload = $app->getHelper('Reload', $controller);
        $this->assertInstanceOf(Reload::class, $reload);
        try {
            $reload();
            $this->fail('Expected BreakExecution from redirect');
        } catch (\Ufw\Exception\BreakExecution $e) {
            $this->assertSame(302, $e->getCode());
        }
    }

    public function testCsrfComponentTokenValidateAndRotate()
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        @session_start();
        $_SESSION = array();
        $tok = CsrfComponent::token('unit');
        $this->assertTrue(CsrfComponent::validate('unit', $tok));
        $this->assertFalse(CsrfComponent::validate('unit', 'wrong'));
        CsrfComponent::rotate('unit');
        $this->assertFalse(CsrfComponent::validate('unit', $tok));
    }
}
