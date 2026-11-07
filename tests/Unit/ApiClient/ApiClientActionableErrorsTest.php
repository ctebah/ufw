<?php
namespace Ufw\Tests\Unit\ApiClient;

use Ufw\Tests\Support\TestAppPaths;

require_once dirname(__DIR__, 2) . '/Support/ApiClientMagicErrorProbe.php';

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Exception as ApiException;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Form\Element\DateRange;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Form\Validator\Csrf;
use Ufw\Form\Validator\Email;
use Ufw\Form\Validator\FileUpload;
use Ufw\Form\Validator\InArray;
use Ufw\Form\Validator\Required;
use Ufw\Helper\Assets;
use Ufw\Helper\FlashMessenger;
use Ufw\JsOut;
use Ufw\Middleware\Base as MiddlewareBase;
use Ufw\Paths;
use Ufw\Placeholder;
use Ufw\Registry;
use Ufw\Request;
use Ufw\ServerRequest;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientStubRequester;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\ApiClientMagicBrokenChildProbe;
use Ufw\Tests\Support\ApiClientMagicWrongResponseProbe;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\ApiClientRequesterProbe;
use Ufw\Utils\Asset\Js;
use Laminas\Diactoros\UploadedFile;

/**
 * Second-pass actionable framework line coverage (validators, Menu-adjacent helpers, ApiClient edges, Record, assets).
 */
class ApiClientActionableErrorsTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientStubRequester::clearNextResponse();
        ApiClientRequesterProbe::resetInstance();
        \Ufw\Form\Element\Base::setRenderHelperName(null);
        \Ufw\Helper\Menu::setRenderHelperName(null);
        $this->resetFlashMessengerStatics();
        $_GET = array();
        $_POST = array();
        $_SERVER = array();
        $_SESSION = array();
        parent::tearDown();
    }

    private function resetFlashMessengerStatics()
    {
        $ref = new \ReflectionClass(FlashMessenger::class);
        foreach (array('_messages', '_session') as $property) {
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);
            if ($property === '_messages') {
                $prop->setValue(null, array());
            } else {
                $prop->setValue(null, null);
            }
        }
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

    public function testFormValidatorsNamedConstructorOptionsAndInstanceAdd()
    {
        ApplicationFixtures::bareWithController(array());
        $el = new Text('csrf');
        $el->addValidator(array(
            'name' => 'Csrf',
            'options' => array('gate-test'),
        ));
        $this->assertInstanceOf(Csrf::class, $el->getValidator('Csrf'));

        $email = new Email();
        $el2 = new Text('mail');
        $el2->addValidator($email);
        $this->assertSame($email, $el2->getValidator('Ufw_Form_Validator_Email'));

        $el3 = new Text('missing');
        $el3->setValidator('bad', 123);
        $this->assertNull($el3->getValidator('bad'));
    }

    public function testFormValidatorsResolveUnknownShortNameThrows()
    {
        ApplicationFixtures::bareWithController(array());
        $el = new Text('x');
        $el->setValidator('nope', 'NotARealValidator');
        $this->expectException(\InvalidArgumentException::class);
        $el->getValidator('nope');
    }

    public function testFormFiltersInstanceAddAndArrayDefinition()
    {
        ApplicationFixtures::bareWithController(array());
        $el = new Text('t');
        $filter = new \Ufw\Form\Filter\Singleline();
        $el->addFilter($filter);
        $this->assertSame($filter, $el->getFilter('Ufw_Form_Filter_Singleline'));

        $el2 = new Text('t2');
        $el2->addFilter(array('name' => 'Singleline'));
        $this->assertInstanceOf(\Ufw\Form\Filter\Singleline::class, $el2->getFilter('Singleline'));
    }

    public function testFormFilterResolveUnknownThrows()
    {
        ApplicationFixtures::bareWithController(array());
        $el = new Text('x');
        $el->setFilter('bad', 'Nope');
        $this->expectException(\InvalidArgumentException::class);
        $el->getFilter('bad');
    }

    public function testFormElementMagicGetOptionAndMultiScalarReject()
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new Text('t');
        $el->setOption('label', 'Lbl');
        $this->assertSame('Lbl', $el->label);

        $multi = new class('c') extends Select {
            public function getAllowMultiple()
            {
                return true;
            }

            public function getMultiOptionValues()
            {
                return array('a', 'b');
            }
        };
        $multi->setMultiOptions(array('a' => 'A', 'b' => 'B'));
        $multi->setValue('z');
        $this->assertNull($multi->getValue());
    }

    public function testFormElementRenderHelperNameFromConfig()
    {
        \Ufw\Form\Element\Base::setRenderHelperName(null);
        ApplicationFixtures::bareWithController(array('form' => array('helper' => 'bootstrap4')));
        $el = new Text('t');
        $this->assertSame('bootstrap4', $el->getRenderHelperName());
    }

    public function testFormElementSubElementBracketContextPath()
    {
        ApplicationFixtures::bareWithController(array());
        $composite = new class('group') extends \Ufw\Form\Element\SubElementsAbstract {
            public function init()
            {
                $this->subEl[] = new Text('part[inner]');
            }
        };
        $composite->setValue(null, array('part' => array('inner' => 'deep')));
        $values = iterator_to_array($composite->yieldSubValues());
        $this->assertSame('deep', $values['part[inner]']);
    }

    public function testFormMultiAddOptionsStructuredEntries()
    {
        ApplicationFixtures::bareWithController(array());
        $select = new Select('choice');
        $select->addMultiOptions(array(
            array('key' => 'k1', 'value' => 'Label 1'),
        ));
        $this->assertArrayHasKey('k1', $select->getMultiOptions());
    }

    public function testFormDateRangeScalarValueBranches()
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $el = new DateRange('period');
        $this->assertNull($el->getValueFrom());
        $raw = $el->getValueRaw();
        $this->assertSame('', $raw['from']);
        $el->setValue(array('from' => '01. 01. 2020.', 'to' => '31. 01. 2020.'));
        $this->assertSame('2020-01-01', $el->getValueRaw()['from']);
    }

    public function testFormValidatorBaseErrorAndMessagePaths()
    {
        ApplicationFixtures::bareWithController(array());
        $required = new Required();
        $error = new \ReflectionMethod(\Ufw\Form\Validator\Base::class, 'error');
        $error->setAccessible(true);
        $error->invoke($required, '');
        $this->assertSame(array(), $required->getMessages());

        $create = new \ReflectionMethod(\Ufw\Form\Validator\Base::class, 'createMessage');
        $create->setAccessible(true);
        $identical = new class extends Required {
            protected $messageTemplates = array(
                Required::REQUIRED => 'Bad %value%',
            );
        };
        $identical->setObscureValue(true);
        $msg = $create->invoke($identical, Required::REQUIRED, 'secret');
        $this->assertStringContainsString('******', (string) $msg);

        $required2 = new class extends Required {
            protected $messageTemplates = array(
                Required::REQUIRED => 'Saw %value%',
            );
        };
        $objectMsg = $create->invoke($required2, Required::REQUIRED, new class {
            public function __toString()
            {
                return 'obj';
            }
        });
        $this->assertStringContainsString('obj', (string) $objectMsg);

        $plainObject = $create->invoke($required2, Required::REQUIRED, new class {
        });
        $this->assertStringContainsString('object', (string) $plainObject);

        ApplicationFixtures::tearDownRegistry();
        $appValidator = new class extends Required {
            public function probeTranslate()
            {
                return $this->translate('plain');
            }
        };
        $this->expectException(\RuntimeException::class);
        $appValidator->probeTranslate();
    }

    public function testFormValidatorInArrayAndSinglelineFilterScalars()
    {
        ApplicationFixtures::bareWithController(array());
        $inArray = new InArray(array('a'));
        $this->assertFalse($inArray->isValid('b'));

        $filter = new \Ufw\Form\Filter\Singleline();
        $this->assertSame('1 2', preg_replace('~\\s+~', ' ', $filter->filter("1\r\n2")));
        $this->assertSame('x', $filter->filter('x'));
    }

    public function testFormValidatorFileUploadArrayAndNonUploadPaths()
    {
        ApplicationFixtures::bareWithController(array());
        $validator = new FileUpload();
        $this->assertTrue($validator->isValid(array('not-a-file')));
        $this->assertTrue($validator->isValid(new \stdClass()));
    }

    public function testMagicTraitUnexpectedResponseFromRequester()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $this->expectException(\ErrorException::class);
        $this->withUserErrorAsException(function () {
            ApiClientMagicWrongResponseProbe::getInstance(Client::getInstance())->get('/x');
        });
    }

    public function testMagicTraitBrokenChildIsNotCallable()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $this->expectException(\ErrorException::class);
        $this->withUserErrorAsException(function () {
            ApiClientMagicBrokenChildProbe::getInstance(Client::getInstance())->broken();
        });
    }

    public function testMagicTraitRaisesApiExceptionWhenConfigured()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        Client::getInstance()->setRequester(ApiClientStubRequester::getInstance());
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $api->setRaiseExceptionOnApiError(true);
        $bad = new ApiResponse();
        $bad->setException(new \RuntimeException('api fail'));
        ApiClientStubRequester::setNextResponse($bad);
        $this->expectException(ApiException::class);
        $api->get('/fail');
    }

    public function testApiExceptionLongHtmlMessageFallsBackToReasonPhrase()
    {
        $body = '<html>' . str_repeat('x', 2100) . '</html>';
        $http = new PsrResponse(502, array(), $body);
        $req = new PsrRequest('GET', '/');
        $ex = new RequestException('fail', $req, $http);
        $wrapped = new ApiException(null, null, $ex);
        $this->assertSame('Bad Gateway', $wrapped->getMessage());
    }

    public function testRequestProxiesServerRequestMethodAndAcceptNegotiationFallback()
    {
        $psr = new ServerRequest(
            array('HTTP_ACCEPT' => 'text/plain'),
            array(),
            '/',
            'GET',
            'php://input',
            array('Accept' => 'text/plain')
        );
        $request = new Request(array(), array(), array(), null, $psr);
        $this->assertSame('GET', $request->getMethod());
        $this->assertFalse(Request::acceptPrefersApplicationJson($psr));
    }

    public function testMiddlewareInvokeDelegatesToProcess()
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
        };
        $request = TestHttpRequest::withAttributes(array(), 'GET', '/');
        $response = $mw($request, function ($req) use ($app) {
            return $app->getResponse();
        });
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
    }

    public function testJsOutFetchPlaceholderCoercesTypes()
    {
        list($app, $controller) = ApplicationFixtures::bareWithController(array());
        $stub = new class extends \Ufw\Helper\Base {
            public function __invoke($name)
            {
                if ($name === 'obj') {
                    $ph = new Placeholder();
                    $ph->set('ph');

                    return $ph;
                }
                if ($name === 'num') {
                    return 42;
                }
                if ($name === 'bool') {
                    return true;
                }

                return 'text-' . $name;
            }
        };
        $ref = new \ReflectionClass($app);
        $prop = $ref->getProperty('helpersInstantiated');
        $prop->setAccessible(true);
        $helpers = $prop->getValue($app);
        $helpers['Placeholder'] = $stub;
        $prop->setValue($app, $helpers);

        $out = JsOut::getInstance();
        $fetch = new \ReflectionMethod(JsOut::class, 'fetchPlaceholder');
        $fetch->setAccessible(true);
        $this->assertStringContainsString('ph', $fetch->invoke($out, 'obj'));
        $this->assertSame('text-inline', $fetch->invoke($out, 'inline'));
        $this->assertSame('42', $fetch->invoke($out, 'num'));
        $this->assertSame('1', $fetch->invoke($out, 'bool'));
    }

    public function testAssetsMinifiedBundleAndJsCompiledBranches()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app) = ApplicationFixtures::bareWithController(array(
            'assets' => array('serve-minified' => true),
        ));
        $controller = new class($app) extends ControllerBase {
            public function indexAction()
            {
            }
        };
        $assets = $app->getHelper('Assets', $controller);
        $this->assertInstanceOf(Assets::class, $assets);
        $assets('site');
        $html = (string) $assets->js(Js::POS_BODY_BOTTOM);
        $this->assertStringContainsString('minified-gate.js', $html);

        $normalize = new \ReflectionMethod(Assets::class, 'normalizeAssetInitData');
        $normalize->setAccessible(true);
        $this->assertSame(array(), $normalize->invoke($assets, 'not-array'));
    }

    public function testUtilsAssetJsSkipsInvalidResourcesAndNestedLocal()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app) = ApplicationFixtures::bareWithController(array('assets' => array('serve-minified' => false)));
        $js = new Js();
        $js->setApplication($app);
        $js->init(array(
            'cdn' => array(null, array('src' => '/cdn.js', 'position' => Js::POS_BODY_BOTTOM)),
            'local' => array(
                array(
                    array('src' => '/nested.js', 'position' => Js::POS_BODY_BOTTOM),
                    99,
                ),
            ),
        ));
        $html = (string) $js;
        $this->assertStringContainsString('cdn.js', $html);
        $this->assertStringContainsString('nested.js', $html);
    }

    public function testFlashMessengerRestoresPreviousSessionAndAddsMessages()
    {
        ApplicationFixtures::bareWithController(array());
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = array(
            'ufw__fM' => array(
                0 => array('skip'),
                'info' => array('prev'),
            ),
        );
        $this->resetFlashMessengerStatics();
        $flash = new FlashMessenger();
        $this->assertContains('prev', $flash->getMessages(FlashMessenger::NAMESPACE_INFO));
        $flash->addMessage('next', null, false);
        $this->assertContains('next', $flash->getCurrentMessages(FlashMessenger::NAMESPACE_INFO));

        $normalize = new \ReflectionMethod(FlashMessenger::class, 'normalizeMessageList');
        $normalize->setAccessible(true);
        $this->assertSame(array(), $normalize->invoke(null, 'not-array'));
    }

    public function testRecordBaseBehaviourOrderStringAndReReadNonArrayPk()
    {
        if (\Ufw\Registry::getInstance()->has('___model_behaviours')) {
            \Ufw\Registry::getInstance()->remove('___model_behaviours');
        }
        $record = new class(array('id' => 1, 'code' => 'ord', 'data' => '{}')) extends WidgetPlain {
            public function behaviours()
            {
                return array(
                    'slow' => array(
                        'class' => \Ufw\Model\Behaviour\DefaultFieldValues::class,
                        'order' => '600',
                    ),
                    'fast' => array(
                        'class' => \Ufw\Model\Behaviour\DefaultFieldValues::class,
                        'order' => 100,
                    ),
                );
            }

            public function getPrimaryKeyField()
            {
                return false;
            }
        };
        $list = iterator_to_array($record->getBehaviours('beforeSave'));
        $keys = array_keys($list);
        $this->assertSame('fast', $keys[0]);
    }

    public function testRecordSimpleNormalizesNonArrayPrimaryKeyOnDelete()
    {
        ApplicationFixtures::bareWithController(array());
        $record = new class(array('id' => 9, 'code' => 'pk', 'data' => '{}')) extends WidgetPlain {
            public function getPrimaryKeyField()
            {
                return 123;
            }

            public function getDb()
            {
                return new class {
                    public function simple_delete($table, $criteria, $ident, $file, $line)
                    {
                        return $criteria;
                    }
                };
            }
        };
        $record->setIsNewRecord(false);
        $delete = new \ReflectionMethod($record, '_delete');
        $delete->setAccessible(true);
        $this->assertSame(array(), $delete->invoke($record));
    }
}
