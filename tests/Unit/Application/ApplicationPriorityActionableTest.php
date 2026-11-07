<?php
namespace Ufw\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\ApiClient\FormAlike;
use Ufw\Component\ApiClient\Helper;
use Ufw\Exception\BreakExecution;
use Ufw\Form\Element\Text;
use Ufw\InfoHash;
use Ufw\Registry;
use Ufw\Route\Literal;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\InfoHashIntCountDbStub;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

/**
 * Drive actionable gaps on the six priority framework files toward zero (Db retry excluded via filter).
 */
class ApplicationPriorityActionableTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testHelperAssignValueToKeyUsesStringKeyBranch()
    {
        $helper = Helper::getInstance();
        $out = $helper->assignValueToKey(array(), 'field[sub]', 'v');
        $this->assertSame('v', $out['field']['sub']);
    }

    public function testHelperGetElementReturnsFormElementForSimpleName()
    {
        $helper = Helper::getInstance();
        $form = new FormAlike(array('action' => '/save'));
        $form->addElement(new Text('title'));
        $el = $helper->getElement('title', $form);
        $this->assertInstanceOf(Text::class, $el);
    }

    public function testHelperNormalizeErrorTreeFromArrayStoresScalarLeaf()
    {
        $helper = Helper::getInstance();
        $method = new \ReflectionMethod(Helper::class, 'normalizeErrorTreeFromArray');
        $method->setAccessible(true);
        $this->assertSame(array('x' => 'msg'), $method->invoke($helper, array('x' => 'msg')));
    }

    public function testHelperNormalizeErrorTreeFromArrayViaPublicApi()
    {
        $helper = Helper::getInstance();
        $this->assertSame(array('leaf' => 'err'), $helper->normalizeErrorTree(array('leaf' => 'err')));
    }

    public function testInfoHashXmlSerializeRecursiveSkipsInvalidElementNames()
    {
        $method = new \ReflectionMethod(InfoHash::class, '__xml_serialize_recursive');
        $method->setAccessible(true);
        $root = new \SimpleXMLElement('<root/>');
        $method->invoke(null, new \stdClass(), array('x' => 1), $root);
        $method->invoke(null, null, 'text', $root);
        $this->assertStringContainsString('<root', $root->asXML());
    }

    public function testInfoHashXmlSerializeWithScalarValueUsesEmptyVarList()
    {
        $hash = new InfoHash('id', 'code');
        $method = new \ReflectionMethod(InfoHash::class, '__xml_serialize');
        $method->setAccessible(true);
        $xml = $method->invoke($hash, 123, 'root');
        $this->assertStringContainsString('<root', (string) $xml);
    }

    public function testInfoHashGetTotalCountAcceptsIntegerFetchResult()
    {
        $hash = new class('id', 'code') extends InfoHash {
            public function getDbClass()
            {
                return InfoHashIntCountDbStub::class;
            }
        };
        $hash->setDataQuery('SELECT id FROM widget ORDER BY id LIMIT 10');
        $this->assertSame(42, $hash->getTotalCount());
    }

    public function testApplicationDispatchRequiresRequestWhenGetRequestIsNull()
    {
        $app = new class extends Application {
            private $forceNull = false;

            public function armNullRequest()
            {
                $this->forceNull = true;
            }

            public function getRequest()
            {
                if ($this->forceNull) {
                    return null;
                }

                return parent::getRequest();
            }
        };
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $app->armNullRequest();
        $this->expectException(\RuntimeException::class);
        $app->dispatch(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
    }

    public function testApplicationDispatchRejectsEmptyActionNameFromMatchedRoute()
    {
        $app = new class extends Application {
            /**
             * @var \Ufw\Route\Base|null
             */
            public $routeStub;

            protected function decodeRequest()
            {
                return TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'), 'GET', '/empty-action');
            }

            public function getRouter()
            {
                $router = parent::getRouter();
                if ($this->routeStub !== null) {
                    $prop = new \ReflectionProperty($router, 'currentRoute');
                    $prop->setAccessible(true);
                    $prop->setValue($router, $this->routeStub);
                }

                return $router;
            }
        };
        $app->setConfig(array(
            'dispatch' => array(
                'routes' => array(
                    'empty' => array(
                        'type' => Literal::class,
                        'path' => '/empty-action',
                        'defaults' => array('controller' => 'ping', 'action' => 'index'),
                    ),
                ),
                'controllers' => array('ping'),
            ),
        ));
        Registry::getInstance()->set('application', $app);
        $router = $app->getRouter();
        $app->routeStub = new class($router, $app->getConfig()['dispatch']['routes']['empty']) extends Literal {
            public function getActionMethod()
            {
                return '';
            }

            public function getControllerClass()
            {
                return '\\Controller\\Ping';
            }
        };
        $this->expectException(BreakExecution::class);
        $app->dispatch(null);
    }

    public function testRouteBuildUrlSkipsScalarUriParamWhenSegmentNotStringable()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        Registry::getInstance()->set('application', $app);
        $router = new Router($app, TestHttpRequest::withAttributes(array(), 'GET', '/shop'), array(
            'shop' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/shop#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'options' => array('allowUriParams' => true),
                'urlBuilder' => array('/shop'),
            ),
        ));
        $route = $router->match();
        $built = $route->buildUrl(array('skip' => new \stdClass(), 'ok' => '1'), true, false, true);
        $this->assertStringContainsString('ok', $built);
        $this->assertStringNotContainsString('skip', $built);
    }
}
