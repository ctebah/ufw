<?php
namespace Ufw\Tests\Unit\Http;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\Csrf;
use Ufw\Exception\BreakExecution;
use Ufw\Form\Element\SubElementsAbstract;
use Ufw\Form\Element\Text;
use Ufw\Helper\FlashMessenger;
use Ufw\Helper\Render;
use Ufw\Model\Behaviour\ChangedOnly;
use Ufw\Paths;
use Ufw\Router;
use Ufw\ServerRequest;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\UploadedTmpFile;

class MiddlewareStackTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        if (\Ufw\Registry::getInstance()->has('___model_behaviours')) {
            \Ufw\Registry::getInstance()->remove('___model_behaviours');
        }
        Paths::setApplicationPath(TestAppPaths::app());
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            session_write_close();
        }
        parent::tearDown();
    }

    public function testGetBehavioursReturnsNothingForUnknownOrMalformedEvent(): void
    {
        $record = new WidgetPlain(array('id' => 1, 'code' => 'x', 'data' => '{}'));
        $this->assertSame(array(), iterator_to_array($record->getBehaviours('noSuchEvent')));

        $hash = $record->getBehavioursHash();
        $hash->hash['broken'] = 'not-an-array';
        $this->assertSame(array(), iterator_to_array($record->getBehaviours('broken')));
    }

    public function testSubElementTraitParsesBracketedNamesFromContext(): void
    {
        ApplicationFixtures::bareWithController();
        $composite = new class() extends SubElementsAbstract {
            protected $type = 'text';

            public function __construct()
            {
                parent::__construct('group');
                $this->subEl = array(new Text('group[part]'));
            }
        };
        $composite->setValue(null, array('group' => array('part' => 'nested-value')));
        $values = iterator_to_array($composite->yieldSubValues());
        $this->assertSame('nested-value', $values['group[part]']);
    }

    public function testChangedOnlyValuesEqualCoversScalarAndNullBranches(): void
    {
        $method = new \ReflectionMethod(ChangedOnly::class, 'valuesEqual');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke(null, null, null));
        $this->assertFalse($method->invoke(null, null, 1));
        $this->assertTrue($method->invoke(null, true, true));
        $this->assertTrue($method->invoke(null, '1', 1));
        $this->assertFalse($method->invoke(null, new \stdClass(), new \stdClass()));
    }

    public function testViewRendererReturnsEmptyWhenNoScriptExists(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController();
        $render = new Render();
        $render->setController($controller);
        $method = new \ReflectionMethod(\Ufw\Helper\ViewRendererBase::class, 'renderHtml');
        $method->setAccessible(true);
        $html = $method->invoke($render, array(), 'partials/does-not-exist-' . bin2hex(random_bytes(3)) . '.php', false);
        $this->assertSame('', $html);
    }

    public function testViewRendererUsesControllerIdentPrefixWhenRequested(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController();
        $identProp = new \ReflectionProperty($controller, 'ident');
        $identProp->setAccessible(true);
        $identProp->setValue($controller, 'probectrl');
        $dir = TestAppPaths::app() . '/views/probectrl';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $script = $dir . '/action-probe.php';
        file_put_contents($script, '<?php echo "ident-ok";');
        try {
            $render = new Render();
            $render->setController($controller);
            $method = new \ReflectionMethod(\Ufw\Helper\ViewRendererBase::class, 'renderHtml');
            $method->setAccessible(true);
            $html = $method->invoke($render, array(), 'action-probe.php', true);
            $this->assertStringContainsString('ident-ok', $html);
        } finally {
            if (is_file($script)) {
                unlink($script);
            }
        }
    }

    public function testRouterGetRouteRejectsInvalidTypeAndInstance(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $request = TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'));
        $router = new Router($app, $request, array(
            'bad-type' => array('type' => \stdClass::class),
        ));
        $this->expectException(\LogicException::class);
        $router->getRoute('bad-type');
    }

    public function testRouterGetRouteRejectsNonRouteInstance(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $request = TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'));
        $router = new Router($app, $request, array(
            'broken' => array('route' => new \stdClass()),
        ));
        $this->expectException(\LogicException::class);
        $router->getRoute('broken');
    }

    public function testApplicationRunHandlesForbiddenBreakExecution(): void
    {
        $app = new class() extends Application {
            public function dispatch($request = null)
            {
                throw new BreakExecution('forbidden', 403);
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
        $this->assertSame(403, $app->getResponse()->getStatusCode());
    }

    public function testApplicationRunHandlesGenericDispatchException(): void
    {
        $app = new class() extends Application {
            public function dispatch($request = null)
            {
                throw new \RuntimeException('boom');
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

    public function testFlashMessengerHasMessagesWhenNamespacePopulated(): void
    {
        ApplicationFixtures::bareWithController();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array('ufw__fM' => array('info' => array('prior')));
        $ref = new \ReflectionClass(FlashMessenger::class);
        foreach (array('_session', '_messages') as $name) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
        $fm = new FlashMessenger();
        $this->assertTrue($fm->hasMessages('info'));
        $this->assertTrue($fm->hasMessages(null));
    }

    public function testCsrfRotateIssuesNewToken(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array();
        $first = Csrf::token('rotate-me');
        Csrf::rotate('rotate-me');
        $second = Csrf::token('rotate-me');
        $this->assertNotSame($first, $second);
    }

    public function testServerRequestValidateUploadedFilesAcceptsNestedTree(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'x');
        rewind($stream);
        $file = new UploadedTmpFile($stream, 1, UPLOAD_ERR_OK);
        $method = new \ReflectionMethod(ServerRequest::class, 'validateUploadedFiles');
        $method->setAccessible(true);
        $req = new ServerRequest();
        $method->invoke($req, array('top' => array('leaf' => $file)));
        $this->addToAssertionCount(1);
    }
}
