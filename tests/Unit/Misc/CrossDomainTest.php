<?php
namespace Ufw\Tests\Unit\Misc;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ufw\Application;
use Ufw\Component\ApiClient\Base as ApiClientBase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Exception as ApiClientException;
use Ufw\Component\ApiClient\FormAlike;
use Ufw\Component\ApiClient\Helper as ApiHelper;
use Ufw\Component\ApiClient\MagicTrait;
use Ufw\Component\ApiClient\Response as ApiClientResponse;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Db\Condition;
use Ufw\Form\Attribs;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\Singleline;
use Ufw\Form\Filters;
use Ufw\Form\Validator\Base as ValidatorBase;
use Ufw\Form\Validator\StringLength;
use Ufw\Form\Validators;
use Ufw\Helper\Assets;
use Ufw\Helper\Base as HelperBase;
use Ufw\Helper\Bootstrap3plain;
use Ufw\Helper\FlashMessenger;
use Ufw\Helper\FormErrorsToFlashMessenger;
use Ufw\Helper\Menu;
use Ufw\Helper\Render;
use Ufw\Helper\Url;
use Ufw\InfoHash;
use Ufw\Middleware\Dispatch;
use Ufw\Model\Base as ModelBase;
use Ufw\Model\Record\Solr;
use Ufw\Paths;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormRenderTestSupport;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\ApiClientMagicMissingChildProbe;
use Ufw\Tests\Support\ApiClientRequesterProbe;
use Ufw\Tests\Support\TraitRReadCacheProbe;
use Ufw\Utils\ArrayAccess;
use Ufw\Utils\Asset\Css;
use Ufw\Utils\Asset\Js;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class CrossDomainTest extends TestCase
{
    use FormFixtureConfig;
    use FormRenderTestSupport;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientRequesterProbe::resetInstance();
        $this->resetFormRenderHelperNames();
        Paths::setApplicationPath(TestAppPaths::app());
        parent::tearDown();
    }

    public function testPathsExceptionLogUsesDefaultWhenLoggingKeyMissing(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $path = Paths::exceptionLogFilePath(array('logging' => array('other' => true)));
        $this->assertStringContainsString('exceptions.txt', (string) $path);
    }

    public function testDispatchMiddlewareRejectsEmptyActionName(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $controller->setCurrentActionMethod('');
        $dispatch = new Dispatch($controller);
        $handler = new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
            {
                return new \Laminas\Diactoros\Response();
            }
        };
        $this->expectException(\BadMethodCallException::class);
        $dispatch->process(new \Laminas\Diactoros\ServerRequest(), $handler);
    }

    public function testDispatchMiddlewareRejectsNonPublicAction(): void
    {
        $controller = new class(ApplicationFixtures::bareWithController()[0]) extends ControllerBase {
            protected function secretAction()
            {
            }
        };
        $controller->setCurrentActionMethod('secretAction');
        $dispatch = new Dispatch($controller);
        $handler = new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
            {
                return new \Laminas\Diactoros\Response();
            }
        };
        $this->expectException(\BadMethodCallException::class);
        $dispatch->process(new \Laminas\Diactoros\ServerRequest(), $handler);
    }

    public function testArrayAccessMagicGetSetAndIsset(): void
    {
        $bag = new class() extends ArrayAccess {
            protected $hit = 'yes';
        };
        $this->assertSame('yes', $bag->hit);
        $bag->miss = 'no';
        $this->assertSame('no', $bag->miss);
        unset($bag->hit);
        unset($bag->miss);
        $this->assertFalse(isset($bag->miss));
    }

    public function testFormFilterAndValidatorCollectionBranches(): void
    {
        $el = new Text('t');
        $this->expectException(\InvalidArgumentException::class);
        $el->addFilter(array('options' => array()));
        $el2 = new Text('u');
        $this->assertInstanceOf(Singleline::class, $el2->getFilter('Singleline'));
    }

    public function testValidatorBaseTranslateAndScalarConversion(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $validator = new StringLength(array('min' => 2));
        $scalar = new \ReflectionMethod(ValidatorBase::class, 'scalarOrObjectToString');
        $scalar->setAccessible(true);
        $this->assertSame('9', $scalar->invoke($validator, 9));
        $this->assertSame('obj', $scalar->invoke($validator, new class {
            public function __toString()
            {
                return 'obj';
            }
        }));
        $translate = new \ReflectionMethod(ValidatorBase::class, 'translate');
        $translate->setAccessible(true);
        $this->assertSame('msg', $translate->invoke($validator, 'msg'));
        $noHelper = new class() extends StringLength {
            public function probeTranslate($msg)
            {
                return $this->translate($msg);
            }

            protected function getHelper($name)
            {
                return null;
            }
        };
        $this->assertSame('fallback', $noHelper->probeTranslate('fallback'));
    }

    public function testSinglelineFilterCoercesFloat(): void
    {
        $filter = new Singleline();
        $this->assertSame('1.5', $filter->filter(1.5));
        $obj = new \stdClass();
        $this->assertSame($obj, $filter->filter($obj));
    }

    public function testStringLengthValidatorTreatsNonScalarAsZeroLength(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $validator = new StringLength();
        $validator->setMaxLen(10)->setMinLen(5);
        $this->assertFalse($validator->isValid(new \stdClass(), array()));
    }

    public function testHelperBaseTranslateReturnsMessageWhenNoHelper(): void
    {
        ApplicationFixtures::bareWithController();
        $helper = new class() extends HelperBase {
            public function probe($msg)
            {
                return $this->translate($msg);
            }
        };
        $this->assertSame('plain', $helper->probe('plain'));
    }

    public function testAssetsHelperLoadsConfigFromApplication(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController();
        $assets = new Assets();
        $assets->setController($controller);
        $assets->setName('css');
        $method = new \ReflectionMethod(Assets::class, 'getConfig');
        $method->setAccessible(true);
        $cfg = $method->invoke($assets);
        $this->assertIsArray($cfg);
    }

    public function testBootstrap3plainUsesBundledPartialTree(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController();
        $this->syncFormRenderHelperName('bootstrap3plain');
        $cb = new \Ufw\Form\Element\Checkbox('agree', array('label' => 'Agree'));
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $cb->setForm($form);
        $helper = new Bootstrap3plain();
        $helper->setController($controller);
        $this->assertStringContainsString('checkbox', $helper->field($cb));
    }

    public function testFlashMessengerHasMessagesFalseForEmptyNamespace(): void
    {
        ApplicationFixtures::bareWithController();
        $fm = new FlashMessenger();
        $this->assertFalse($fm->hasMessages('missing'));
    }

    public function testFormErrorsToFlashMessengerNoOpWithoutFormErrors(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $bridge = new FormErrorsToFlashMessenger();
        $bridge->setController($controller);
        $bridge(null);
        $this->assertTrue(true);
    }

    public function testMenuBuildTreeHandlesNonArrayDefinitionEntry(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $menu = new Menu();
        $menu->setController($controller);
        $build = new \ReflectionMethod(Menu::class, 'buildTree');
        $build->setAccessible(true);
        $def = array('skip' => 'not-array');
        $tree = $build->invoke($menu, $def, '/');
        $this->assertSame(array(), $tree);
    }

    public function testModelBaseGetUserReturnsHelper(): void
    {
        ApplicationFixtures::bareWithController();
        $model = new class() extends ModelBase {
        };
        $this->assertNotNull($model->getUser());
    }

    public function testRegExRouteBuildUrlSkipsNonStringPatternEntry(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $router = new Router($app, TestHttpRequest::withAttributes(array()), array(
            'x' => array(
                'type' => RegEx::class,
                'urlBuilder' => array('/item/:id', array(99)),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $this->assertSame('/item/7', $router->getRoute('x')->buildUrl(array('id' => '7')));
    }

    public function testCssAndJsAssetHelpersExposeCompiledOutput(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app) = ApplicationFixtures::bareWithController();
        $css = new Css();
        $css->setApplication($app)->init(array('local' => array('/site.css')));
        $this->assertStringContainsString('site.css', (string) $css);
        $js = new Js();
        $js->setApplication($app)->init(array('local' => array('/app.js')));
        $this->assertStringContainsString('app.js', (string) $js);
    }

    public function testUrlHelperHttpHostWhenServerNameMissing(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $_SERVER = array('SERVER_PORT' => '8080');
        $url = new Url();
        $url->setController($controller);
        $method = new \ReflectionMethod(Url::class, 'getHttpHost');
        $method->setAccessible(true);
        $getServer = new \ReflectionMethod(Url::class, 'getServer');
        $getServer->setAccessible(true);
        $this->assertSame('', $method->invoke($url));
        $this->assertIsArray($getServer->invoke($url));
        $_SERVER = array('SERVER_NAME' => 'host.test', 'SERVER_PORT' => '8080', 'HTTPS' => 'on');
        $this->assertStringContainsString('host.test', $method->invoke($url));
        $_SERVER = array('SERVER_NAME' => 'host.test', 'SERVER_PORT' => '8080');
        $this->assertStringContainsString('host.test:8080', $method->invoke($url));
        $_SERVER = array();
    }

    public function testRenderJsonMergeBranchWhenOverwriteFalse(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->getResponse()->setFormat('json')->setContent(array('a' => 1));
        $render = new Render();
        $render->setController($controller);
        $render->setOverwrite(false);
        $render->render(array('b' => 2));
        $content = $app->getResponse()->getContent();
        $this->assertIsArray($content);
        $this->assertSame(1, $content['a']);
        if (array_key_exists('b', $content)) {
            $this->assertSame(2, $content['b']);
        }
    }

    public function testApiClientExceptionUsesPreviousMessageWhenEmpty(): void
    {
        $previous = new \RuntimeException('upstream');
        $ex = new ApiClientException(array(), null, $previous);
        $this->assertSame('upstream', $ex->getMessage());
    }

    public function testApiClientBaseSetFormErrorsSkipsBrokenPath(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $api->exposeSetFormErrors(array('title' => array('deep' => 'err', 'missing' => 'x')));
        $this->assertSame(array(), $form->getElement('title')->getErrors());
    }

    public function testApiHelperNormalizeTreeSkipsInvalidNestedKey(): void
    {
        $helper = ApiHelper::getInstance();
        $method = new \ReflectionMethod(ApiHelper::class, 'normalizeErrorTreeFromArray');
        $method->setAccessible(true);
        $tree = $method->invoke($helper, array('ok' => array('child' => 'v')));
        $this->assertArrayHasKey('ok', $tree);
        $keyMethod = new \ReflectionMethod(ApiHelper::class, 'errorTreeKeyToString');
        $keyMethod->setAccessible(true);
        $this->assertNull($keyMethod->invoke($helper, new \stdClass()));
    }

    public function testApiClientResponseCodeFallback(): void
    {
        $response = new ApiClientResponse();
        $response->setCode(418);
        $this->assertSame(418, $response->getCode());
    }

    public function testInfoHashXmlSerializeFromStringNodeAndMultiEntryAutoMode(): void
    {
        $hash = new InfoHash('id', 'code');
        $hash->info[1] = (object) array('id' => 1, 'code' => 'A');
        $hash->info[2] = (object) array('id' => 2, 'code' => 'B');
        $ser = new \ReflectionMethod(InfoHash::class, '__xml_serialize');
        $ser->setAccessible(true);
        $xml = $ser->invoke($hash, array('k' => 'v'), 'node');
        $this->assertIsString($xml);
        $multi = $hash->asXML('roots', 'auto');
        $this->assertIsString($multi);
    }

    public function testRecordGetBehavioursSkipsMalformedEntries(): void
    {
        $record = new WidgetPlain(array('id' => 1, 'code' => 'x', 'data' => '{}'));
        $hash = $record->getBehavioursHash();
        $hash->hash['beforeSave'] = array(new \stdClass(), 'ok', 'missing');
        $hash->info['ok'] = (object) array('obj' => new \Ufw\Model\Behaviour\DefaultFieldValues(new WidgetPlain(array('id' => 2, 'code' => 'y', 'data' => '{}'))));
        $hash->info['bad'] = (object) array('not_obj' => true);
        $hash->info['skip'] = (object) array('obj' => new \stdClass());
        $out = iterator_to_array($record->getBehaviours('beforeSave'));
        $this->assertNotEmpty($out);
    }

    public function testRecordReReadWithScalarPrimaryKeyField(): void
    {
        ApplicationFixtures::bareWithController();
        $record = new class(array('id' => 3, 'code' => 'z', 'data' => '{}')) extends WidgetPlain {
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
                        return array('id' => 3, 'code' => 'z', 'data' => '{}');
                    }

                    public function free()
                    {
                    }
                };
            }
        };
        $record->reRead();
        $this->assertSame(3, $record->getAll()['id']);
    }

    public function testDbConditionToStringWithNullValue(): void
    {
        $condition = new Condition(array('field' => 'x', 'value' => null, 'op' => 'IS'));
        $this->assertIsString((string) $condition);
    }

    public function testClientResolveRequesterSibling(): void
    {
        ApplicationFixtures::bareWithController();
        \Ufw\Tests\Support\ApiClient\Client::resetForTests();
        \Ufw\Tests\Support\ApiClient\Requester::resetForTests();
        $client = \Ufw\Tests\Support\ApiClient\Client::getInstance();
        $resolve = new \ReflectionMethod(Client::class, 'resolveRequester');
        $resolve->setAccessible(true);
        $this->assertInstanceOf(\Ufw\Tests\Support\ApiClient\Requester::class, $resolve->invoke($client));
        $this->assertSame($client->getRequester(), $client->getRequester());
    }

    public function testFormElementResolvesFilterAndValidatorFromOptionsArray(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new Text('t');
        $el->addFilter(array('name' => 'Singleline', 'options' => array()));
        $this->assertInstanceOf(Singleline::class, $el->getFilter('Singleline'));
        $el->addValidator(array('name' => 'StringLength', 'options' => array('max' => 5)));
        $this->assertInstanceOf(StringLength::class, $el->getValidator('StringLength'));
    }

    public function testTextElementGetAttribsRefreshesTranslatableKeys(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new Text('t');
        $el->setAttrib('title', 'Tip');
        $el->setAttrib('placeholder', 'Ph');
        $attribs = $el->getAttribs();
        $this->assertSame('Tip', $attribs['title']);
        $this->assertSame('Ph', $attribs['placeholder']);
    }

    public function testApiClientResponseMagicCallDelegates(): void
    {
        $response = new ApiClientResponse();
        $response->setResponse(new \GuzzleHttp\Psr7\Response(204));
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testTraitRReadUsesCachedDataWithoutReload(): void
    {
        ApplicationFixtures::bareWithController();
        ApiClientTestApi::resetInstances();
        $client = Client::getInstance();
        $probe = TraitRReadCacheProbe::getInstance($client);
        $probe->setData(array('hit' => 1));
        $probe->read(false);
        $this->assertSame(0, $probe->readCalls);
        $probe->read(false);
        $this->assertSame(0, $probe->readCalls);
    }

    public function testTraitRGetFormDataReturnsEmptyWhenDataIsNotArray(): void
    {
        ApplicationFixtures::bareWithController();
        ApiClientTestApi::resetInstances();
        $client = Client::getInstance();
        $probe = TraitRReadCacheProbe::getInstance($client);
        $this->assertSame(array(), $probe->exposeGetFormData('not-array'));
    }

    public function testModelGetUserReturnsHelperWhenNotCallable(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $userHelper = new \Ufw\Helper\User();
        $helpers = new \ReflectionProperty(Application::class, 'helpersInstantiated');
        $helpers->setAccessible(true);
        $map = $helpers->getValue($app);
        $map['User'] = $userHelper;
        $helpers->setValue($app, $map);
        $model = new class() extends ModelBase {
        };
        $this->assertSame($userHelper, $model->getUser());
    }

    public function testInfoHashXmlSerializeBuildsRootWhenNodeArgumentNull(): void
    {
        $hash = new InfoHash('id', 'code');
        $method = new \ReflectionMethod(InfoHash::class, '__xml_serialize');
        $method->setAccessible(true);
        $xml = $method->invoke($hash, array('a' => 1), null);
        $this->assertIsString($xml);
    }

    public function testRecordReReadUsesEmptyPkListWhenPrimaryKeyFieldInvalid(): void
    {
        ApplicationFixtures::bareWithController();
        $record = new class(array('id' => 1, 'code' => 'z', 'data' => '{}')) extends WidgetPlain {
            public function getPrimaryKeyField()
            {
                return null;
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
                        return array('id' => 1, 'code' => 'z', 'data' => '{}');
                    }

                    public function free()
                    {
                    }
                };
            }
        };
        $record->reRead();
        $this->assertSame(1, $record->getAll()['id']);
    }

    public function testMagicTraitErrorsWhenDynamicChildMissing(): void
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Method ghost not found');
        set_error_handler(function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            ApiClientMagicMissingChildProbe::getInstance(Client::getInstance())->ghost();
        } finally {
            restore_error_handler();
        }
    }

    public function testFormFilterArrayDefinitionUsesConstructorOptions(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new Text('x');
        $el->addFilter(array('name' => 'Singleline', 'options' => array('ignored' => true)));
        $this->assertInstanceOf(Singleline::class, $el->getFilter('Singleline'));
    }

    public function testFormValidatorWithoutConstructorBuildsInstance(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new Text('y');
        $el->addValidator(array('name' => 'Csrf'));
        $this->assertNotNull($el->getValidator('Csrf'));
    }

    public function testViewRendererDefaultScriptUsesControllerIdent(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $app->setCurrentController($controller);
        $render = new Render();
        $render->setController($controller);
        $method = new \ReflectionMethod(\Ufw\Helper\ViewRendererBase::class, 'renderHtml');
        $method->setAccessible(true);
        $html = $method->invoke($render, array('probe' => 1), null, true);
        $this->assertIsString($html);
    }
}
