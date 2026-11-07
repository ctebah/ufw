<?php
namespace Ufw\Tests\Unit\Misc;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Exception as ApiClientException;
use Ufw\Component\ApiClient\Response as ApiClientResponse;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Db\Db;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\ConstructibleForTests;
use Ufw\Form\Validator\PlainForTests;
use Ufw\Helper\Assets;
use Ufw\Helper\Base as HelperBase;
use Ufw\Helper\FormErrorsToFlashMessenger;
use Ufw\Helper\FlashMessenger;
use Ufw\Helper\Menu;
use Ufw\Helper\Url;
use Ufw\Model\Base as ModelBase;
use Ufw\Model\Record\Solr;
use Ufw\Paths;
use Ufw\Route\Literal;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientStubRequester;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\ApiClientChildInvokeProbe;
use Ufw\Tests\Support\ApiClientMagicArrayChildProbe;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\ApiClientRequesterProbe;
use Ufw\Utils\Asset\Css;
use Ufw\Utils\Asset\Js;

class ResidualSourcesTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApiClientTestApi::resetInstances();
        ApplicationFixtures::tearDownRegistry();
        Paths::setApplicationPath(TestAppPaths::app());
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            session_write_close();
        }
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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function routerConfig()
    {
        return array(
            'default' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        );
    }

    public function testModelGetUserReturnsNonCallableHelperObject(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $helpers = new \ReflectionProperty(Application::class, 'helpersInstantiated');
        $helpers->setAccessible(true);
        $map = $helpers->getValue($app);
        $map['User'] = new \stdClass();
        $helpers->setValue($app, $map);
        $model = new class() extends ModelBase {
        };
        $this->assertInstanceOf(\stdClass::class, $model->getUser());
    }

    public function testHelperBaseThrowsWhenApplicationHasNoCurrentController(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $app->setCurrentController(null);
        $assets = new Assets();
        $getController = new \ReflectionMethod(HelperBase::class, 'getController');
        $getController->setAccessible(true);
        $this->expectException(\RuntimeException::class);
        $getController->invoke($assets);
    }

    public function testAssetsGetConfigWhenAssetsConfigIsNotArray(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(array(
            'assets' => 'broken',
        ));
        $assets = new Assets();
        $assets->setController($controller);
        $assets->setName('css');
        $method = new \ReflectionMethod(Assets::class, 'getConfig');
        $method->setAccessible(true);
        $cfg = $method->invoke($assets);
        $this->assertIsArray($cfg);
    }

    public function testFlashMessengerStartsSessionWhenNoneActive(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = array();
        $ref = new \ReflectionClass(FlashMessenger::class);
        foreach (array('_session', '_messages') as $name) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
        new FlashMessenger();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function testFormErrorsToFlashMessengerUsesElementLabelOption(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array();
        $ref = new \ReflectionClass(FlashMessenger::class);
        foreach (array('_session', '_messages') as $name) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
                $this->addElement(new Text('title', array('label' => 'Title label')));
            }
        };
        $form->getElement('title')->addError('Required');
        $bridge = new FormErrorsToFlashMessenger();
        $bridge->setController($controller);
        $bridge($form);
        $fm = new FlashMessenger();
        $this->assertNotEmpty($fm->getCurrentMessages(FlashMessenger::NAMESPACE_ERROR));
    }

    public function testMenuBuildTreeSkipsRbacArrayWhenUserLacksRoles(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $controller->user = new class {
            public function hasRole($role)
            {
                return false;
            }

            public function hasAnyOfRoles(array $roles)
            {
                return false;
            }
        };
        $menu = new Menu();
        $menu->setController($controller);
        $build = new \ReflectionMethod(Menu::class, 'buildTree');
        $build->setAccessible(true);
        $tree = $build->invoke($menu, array(
            array('caption' => 'Skip', 'rbac' => array('admin', 99), 'content' => array('href' => '/x')),
        ), '/');
        $this->assertSame(array(), $tree);
    }

    public function testUrlHelperHttpHostIncludesExplicitPortOnHttp(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $_SERVER = array('SERVER_NAME' => 'host.test', 'SERVER_PORT' => '8080');
        $url = new Url();
        $url->setController($controller);
        $method = new \ReflectionMethod(Url::class, 'getHttpHost');
        $method->setAccessible(true);
        $this->assertSame('host.test:8080', $method->invoke($url));
        $_SERVER = array();
    }

    public function testUrlHelperHttpHostOmitsDefaultHttpPort(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $_SERVER = array('SERVER_NAME' => 'host.test', 'SERVER_PORT' => '80');
        $url = new Url();
        $url->setController($controller);
        $method = new \ReflectionMethod(Url::class, 'getHttpHost');
        $method->setAccessible(true);
        $this->assertSame('host.test', $method->invoke($url));
        $_SERVER = array();
    }

    public function testRegExRouteInitializesUrlBuilderWhenMissing(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $router = new Router($app, TestHttpRequest::withAttributes(array()), array());
        $route = new RegEx($router, array(
            'urlBuilder' => '/item/:id',
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
        ));
        $method = new \ReflectionMethod(RegEx::class, 'getUrlBuilderString');
        $method->setAccessible(true);
        $this->assertNull($method->invoke($route, array('id' => '5')));
    }

    public function testFormElementResolvesFilterWithConstructorOptions(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new Text('t');
        $el->addFilter(array('name' => 'ConstructibleForTests', 'options' => array('-')));
        $filter = $el->getFilter('ConstructibleForTests');
        $this->assertInstanceOf(ConstructibleForTests::class, $filter);
        $this->assertSame('a--b', $filter->filter("a\r\nb"));
    }

    public function testFormElementResolvesPlainValidatorFromArrayDefinition(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new Text('t');
        $el->addValidator(array('name' => 'PlainForTests'));
        $this->assertInstanceOf(PlainForTests::class, $el->getValidator('PlainForTests'));
    }

    public function testFormBaseGetAttribsRefreshesTranslatableKeys(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            protected function init()
            {
            }
        };
        $form->setAttrib('title', 'Tip');
        $form->setAttrib('placeholder', 'Ph');
        $attribs = $form->getAttribs();
        $this->assertSame('Tip', $attribs['title']);
        $this->assertSame('Ph', $attribs['placeholder']);
    }

    public function testCssCompiledReturnsEmptyWhenDataUnset(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $css = new Css();
        $css->setApplication($app);
        $method = new \ReflectionMethod(Css::class, 'getCompiled');
        $method->setAccessible(true);
        $this->assertSame('', $method->invoke($css));
    }

    public function testJsCompiledSkipsNonStringLocalResourceEntries(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $js = new Js();
        $js->setApplication($app);
        $js->init(array('local' => array(99, '/ok.js')));
        $this->assertStringContainsString('ok.js', (string) $js);
    }

    public function testAssetBaseThrowsWhenHtml5TagHelperMissing(): void
    {
        $app = new class() extends Application {
            public function getHelper($name, $controller = null)
            {
                return new \stdClass();
            }
        };
        $app->setConfig(array());
        \Ufw\Registry::getInstance()->set('application', $app);
        $css = new Css();
        $css->setApplication($app);
        $css->init(array('cdn' => array('https://cdn.example/app.css')));
        $method = new \ReflectionMethod(Css::class, 'getHtml5TagHelper');
        $method->setAccessible(true);
        $this->expectException(\RuntimeException::class);
        $method->invoke($css);
    }

    public function testApiClientResponseCallDelegatesToWrappedResponse(): void
    {
        $response = new ApiClientResponse();
        $response->setResponse(new \GuzzleHttp\Psr7\Response(204));
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testApiClientResponseCallErrorsWhenMethodMissingOnTarget(): void
    {
        $response = new ApiClientResponse();
        $response->setResponse(new \GuzzleHttp\Psr7\Response(200));
        $this->expectException(\ErrorException::class);
        set_error_handler(function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            $response->notARealPsrMethod();
        } finally {
            restore_error_handler();
        }
    }

    public function testApiClientExceptionUsesReasonPhraseForLongHtmlBody(): void
    {
        $body = '<html>' . str_repeat('x', 2100) . '</html>';
        $http = new \GuzzleHttp\Psr7\Response(502, array(), $body);
        $req = new \GuzzleHttp\Psr7\Request('GET', '/');
        $ex = new \GuzzleHttp\Exception\RequestException('fail', $req, $http);
        $wrapped = new ApiClientException(null, null, $ex);
        $this->assertSame('Bad Gateway', $wrapped->getMessage());
    }

    public function testApiClientHelperNormalizeErrorTreeSkipsInvalidNestedKeys(): void
    {
        $helper = \Ufw\Component\ApiClient\Helper::getInstance();
        $tree = $helper->normalizeErrorTree(array(
            'field' => array(0 => 'ok', '' => 'empty-key'),
        ));
        $method = new \ReflectionMethod(\Ufw\Component\ApiClient\Helper::class, 'normalizeErrorTreeFromArray');
        $method->setAccessible(true);
        $out = $method->invoke($helper, array('a' => 1, 2 => 'b'));
        $this->assertSame(array('a' => 1, '2' => 'b'), $out);
        $this->assertIsArray($tree);
    }

    public function testApiClientSetFormErrorsSkipsWhenElementMissing(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $api->setForm($form);
        $api->setFieldsMap(array('title' => 'title'));
        $api->exposeSetFormErrors(array('wrapper' => array('field' => 'err')));
        $this->assertSame(array(), $form->getElement('title')->getErrors());
    }

    public function testApiClientSetFormErrorsBreaksWhenNestedPathKeyMissing(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $api->setForm($form);
        $api->setFieldsMap(array('title' => 'title'));
        $api->helperOverride = new class() extends \Ufw\Component\ApiClient\Helper {
            public function getErrorPaths($map, $errors, $path = array(), $level = 0)
            {
                return array(array('title', 'missingSegment'));
            }
        };
        $api->exposeSetFormErrors(array('title' => array('ok' => 'fine')));
        $this->assertSame(array(), $form->getElement('title')->getErrors());
    }

    public function testApiClientSetFormErrorsAppliesNestedMessages(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $api->setForm($form);
        $api->setFieldsMap(array('title' => 'title'));
        $api->exposeSetFormErrors(array('title' => 'Plain error'));
        $this->assertSame(array('Plain error'), $form->getElement('title')->getErrors());
    }

    public function testMagicTraitArrayChildPopulatesFormFromArrayPayload(): void
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array('routes' => $this->routerConfig(), 'controllers' => array('ping')),
        ));
        $app->setRouter(new Router($app, TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'), 'GET', '/'), $this->routerConfig()));
        Client::getInstance()->setRequester(ApiClientStubRequester::getInstance());
        ApiClientTestApi::resetInstances();
        $child = ApiClientMagicArrayChildProbe::getInstance(Client::getInstance());
        try {
            $this->withUserErrorAsException(function () use ($child) {
                $child->items(array('title' => 'From list'));
            });
            $this->fail('Expected user error when list sub-resource is not callable.');
        } catch (\ErrorException $e) {
            $this->assertSame('From list', $child->getForm()->getValues()['title']);
        }
    }

    public function testMagicTraitSingleChildPopulatesFormFromArrayPayload(): void
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array('routes' => $this->routerConfig(), 'controllers' => array('ping')),
        ));
        $app->setRouter(new Router($app, TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'), 'GET', '/'), $this->routerConfig()));
        Client::getInstance()->setRequester(ApiClientStubRequester::getInstance());
        ApiClientTestApi::resetInstances();
        $parent = ApiClientChildInvokeProbe::getInstance(Client::getInstance());
        $child = ApiClientChildInvokeProbe::getInstance(Client::getInstance());
        $parent->child(array('title' => 'From array'));
        $this->assertSame('From array', $child->getForm()->getValues()['title']);
    }

    public function testRecordGetBehavioursSkipsInvalidBehaviourObjects(): void
    {
        $record = new WidgetPlain(array('id' => 1, 'code' => 'x', 'data' => '{}'));
        $hash = $record->getBehavioursHash();
        $hash->hash['beforeSave'] = array('noObj', 'badObj');
        $hash->info['noObj'] = (object) array('wrong' => true);
        $hash->info['badObj'] = (object) array('obj' => new \stdClass());
        $out = iterator_to_array($record->getBehaviours('beforeSave'));
        $this->assertSame(array(), $out);
    }

    public function testSolrDeleteReturnsFalseWhenIdIsInvalid(): void
    {
        $record = new SolrDeleteCoverageProbe(array('id' => null));
        $this->assertFalse($record->exposeDelete());
    }
}

class SolrDeleteCoverageProbe extends Solr
{
    public function getSolrJson()
    {
        return array();
    }

    public function getSolrClient()
    {
        return new FakeSolrClientForCoverageDelete();
    }

    public function exposeDelete()
    {
        return $this->_delete();
    }
}

class FakeSolrClientForCoverageDelete
{
    public function createUpdate()
    {
        return new \stdClass();
    }

    public function update($update, $connection)
    {
        return true;
    }
}
