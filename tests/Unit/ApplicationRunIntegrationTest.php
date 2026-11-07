<?php
namespace Ufw\Tests\Unit;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Exception as ApiClientException;
use Ufw\Component\Csrf;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\Singleline;
use Ufw\Form\Validator\Identical;
use Ufw\Helper\FlashMessenger;
use Ufw\Helper\Menu;
use Ufw\Helper\Url;
use Ufw\JsOut;
use Ufw\Paths;
use Ufw\Response;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\ServerRequest;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Utils\ArrayAccess;

class ApplicationRunIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        Paths::setApplicationPath(TestAppPaths::app());
        if (\Ufw\Registry::getInstance()->has('___model_behaviours')) {
            \Ufw\Registry::getInstance()->remove('___model_behaviours');
        }
        $ref = new \ReflectionClass(JsOut::class);
        $prop = $ref->getProperty('_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
        parent::tearDown();
    }

    public function testPathsTreatsUncExceptionLogPathAsAbsolute(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $path = Paths::exceptionLogFilePath(array(
            'logging' => array('exception_log_path' => '\\\\server\\share\\log.txt'),
        ));
        $this->assertSame('\\\\server\\share\\log.txt', $path);
    }

    public function testApiClientExceptionTruncatesLongHtmlPreviousMessage(): void
    {
        $req = new PsrRequest('GET', '/api');
        $resp = new PsrResponse(503, array(), 'unavailable');
        $previous = new RequestException('<p>' . str_repeat('z', 2100) . '</p>', $req, $resp);
        $ex = new ApiClientException(null, null, $previous);
        $this->assertSame('Service Unavailable', $ex->getMessage());
    }

    public function testRegExUrlBuilderCompilesPlaceholderNames(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $router = new Router($app, TestHttpRequest::withAttributes(array()), array(
            'item' => array(
                'type' => RegEx::class,
                'urlBuilder' => array(
                    '/item/:id',
                    array('not-a-pattern'),
                ),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $route = $router->getRoute('item');
        $this->assertSame('/item/5', $route->buildUrl(array('id' => '5')));
    }

    public function testRecordGetBehavioursSkipsInvalidHashEntries(): void
    {
        $record = new WidgetPlain(array('id' => 1, 'code' => 'x', 'data' => '{}'));
        $hash = $record->getBehavioursHash();
        $hash->hash['beforeSave'] = array(new \stdClass(), 'ok');
        $hash->info['ok'] = (object) array('obj' => new \Ufw\Model\Behaviour\DefaultFieldValues(new WidgetPlain(array('id' => 2, 'code' => 'y', 'data' => '{}'))));
        $hash->info['bad'] = (object) array('not_obj' => true);
        $out = iterator_to_array($record->getBehaviours('beforeSave'));
        $this->assertNotEmpty($out);
    }

    public function testRecordReReadNormalizesScalarPrimaryKey(): void
    {
        ApplicationFixtures::bareWithController();
        $record = new class(array('id' => 9, 'code' => 'z', 'data' => '{}')) extends WidgetPlain {
            public function getPrimaryKeyField()
            {
                return 'id';
            }

            public function getDb()
            {
                return new class() {
                    public function simple_select_query($table, $where)
                    {
                        return 'SELECT 1';
                    }

                    public function query($q, $ident = null, $file = null, $line = null)
                    {
                        return true;
                    }

                    public function fetch_array($r)
                    {
                        return array('id' => 9, 'code' => 'z', 'data' => '{}');
                    }

                    public function free()
                    {
                    }
                };
            }
        };
        $record->reRead();
        $this->assertSame(9, $record->getAll()['id']);
    }

    public function testFormFilterSinglelineCoercesScalarTypes(): void
    {
        $f = new Singleline();
        $this->assertSame('1 2', $f->filter("1\n2"));
        $this->assertSame('9', $f->filter(9));
    }

    public function testFormAddFilterRejectsDefinitionWithoutName(): void
    {
        ApplicationFixtures::bareWithController();
        $el = new Text('t');
        $this->expectException(\InvalidArgumentException::class);
        $el->addFilter(array('options' => array()));
    }

    public function testUrlHelperBuildsHostFromServerName(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $_SERVER = array('SERVER_NAME' => 'gate.test', 'SERVER_PORT' => '8080');
        $url = new Url();
        $url->setController($controller);
        $ref = new \ReflectionMethod(Url::class, 'getHttpHost');
        $ref->setAccessible(true);
        $this->assertStringContainsString('gate.test', $ref->invoke($url));
    }

    public function testMenuBuildTreeSkipsFailedRbacCallable(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $menu = new Menu();
        $menu->setController($controller);
        $build = new \ReflectionMethod(Menu::class, 'buildTree');
        $build->setAccessible(true);
        $config = array(
            array(
                'caption' => 'Hidden',
                'rbac' => function () {
                    return false;
                },
                'content' => array('href' => '/h'),
            ),
        );
        $tree = $build->invoke($menu, $config, '/');
        $this->assertSame(array(), $tree);
    }

    public function testFlashMessengerLoadsPriorSessionWhenAlreadyActive(): void
    {
        ApplicationFixtures::bareWithController();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array('ufw__fM' => array('info' => array('stored')));
        $ref = new \ReflectionClass(FlashMessenger::class);
        foreach (array('_session', '_messages') as $name) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
        $fm = new FlashMessenger();
        $this->assertContains('stored', $fm->getMessages('info'));
        $this->assertFalse($fm->hasMessages('no-such-namespace'));
    }

    public function testCsrfValidateStartsSessionWhenNeeded(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $this->assertFalse(Csrf::validate('default', 'nope'));
        Csrf::rotate('fresh');
        $this->assertTrue(session_status() === PHP_SESSION_ACTIVE);
    }

    public function testFormElementMagicAccessPaths(): void
    {
        ApplicationFixtures::bareWithController();
        $el = new Text('title');
        $el->setOption('label', 'L');
        $this->assertSame('text', $el->type);
        $this->assertSame('L', $el->label);
        $v = new Identical('token');
        $this->assertFalse($v->isValid('a', array()));
    }

    public function testUtilsArrayAccessUsesDeclaredProperty(): void
    {
        $bag = new class() extends ArrayAccess {
            public $slot = 'here';
        };
        $this->assertSame('here', $bag->slot);
    }

    public function testJsOutReadyPlaceholderSanitizePathInIntegration(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-js7-' . uniqid('', true);
        mkdir($tmp);
        list($app) = ApplicationFixtures::bareWithController(array(
            'jsOutStorage' => array(
                'webpath' => '/gc7',
                'absrootpath' => $tmp,
                'serve-minified' => false,
            ),
        ));
        $app->getHelper('placeholder')->placeholder('jsReady')->append("line1();\nline2();");
        $out = JsOut::getInstance()->readyPlaceholder();
        $this->assertStringContainsString('script', $out);
    }

    public function testPlaceholderHelperRecreatesContainerWhenMissing(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $helper = new \Ufw\Helper\Placeholder();
        $helper->setController($controller);
        $ref = new \ReflectionClass(\Ufw\Helper\Placeholder::class);
        $container = $ref->getProperty('container');
        $container->setAccessible(true);
        $container->setValue(null, null);
        $slot = $helper->placeholder('jsInline');
        $this->assertInstanceOf(\Ufw\Placeholder::class, $slot);
    }
}
