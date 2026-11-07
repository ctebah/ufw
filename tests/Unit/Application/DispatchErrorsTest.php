<?php
namespace Ufw\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use Ufw\Component\Csrf as CsrfComponent;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Form\Element\DateRange;
use Ufw\Form\Element\SubElementsAbstract;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\Trim;
use Ufw\Form\Validator\Required;
use Ufw\Helper\Partial;
use Ufw\Model\Base as ModelBase;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\Route\Literal;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Utils\ArrayAccess;

/**
 * Extra coverage for dashboard CRAP / partial-method gaps that are cheap to hit in unit tests.
 */
class DispatchErrorsTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        $_SESSION = array();
        parent::tearDown();
    }

    public function testArrayAccessMagicUsesDeclaredProperties(): void
    {
        $bag = new ArrayAccessPropertyFixture();
        $bag->realProp = 'stored';
        $this->assertSame('stored', $bag->realProp);
        unset($bag->realProp);
        $this->assertFalse(isset($bag->realProp));
        $bag->realProp = 'again';
        $this->assertSame('again', $bag->realProp);
    }

    public function testCsrfSessionKeyFallsBackWhenPrefixIsNotString(): void
    {
        $probe = new class extends CsrfComponent {
            protected const SESSION_PREFIX = null;

            public static function exposeKey($purpose)
            {
                return parent::sessionKey($purpose);
            }
        };
        $this->assertSame('ufw.csrf.probe', $probe::exposeKey('probe'));
    }

    public function testCsrfRotateStartsSessionWhenInactive(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        CsrfComponent::rotate('after-post');
        $this->assertTrue(session_status() === PHP_SESSION_ACTIVE);
        $this->assertIsString(CsrfComponent::token('after-post'));
    }

    public function testSubElementTraitSkipsMissingNestedContext(): void
    {
        ApplicationFixtures::bareWithController();
        $composite = new class() extends SubElementsAbstract {
            protected $type = 'text';

            public function __construct()
            {
                parent::__construct('group');
                $this->subEl = array(new Text('group[missing]'));
            }

            public function childValue()
            {
                return $this->subEl[0]->getValue();
            }
        };
        $composite->setValue(null, array('group' => array('other' => 'x')));
        $this->assertNull($composite->childValue());
    }

    public function testSubElementTraitAssignsFlatContextKeys(): void
    {
        ApplicationFixtures::bareWithController();
        $composite = new class() extends SubElementsAbstract {
            protected $type = 'text';

            public function __construct()
            {
                parent::__construct('group');
                $this->subEl = array(new Text('flat'));
            }

            public function childValue()
            {
                return $this->subEl[0]->getValue();
            }
        };
        $composite->setValue(null, array('flat' => 'direct'));
        $this->assertSame('direct', $composite->childValue());
    }

    public function testSubElementTraitTreatsFailedPregSplitAsEmptyParts(): void
    {
        ApplicationFixtures::bareWithController();
        $composite = new class() extends SubElementsAbstract {
            /** @var bool */
            public $failNextSplit = false;

            protected $type = 'text';

            public function __construct()
            {
                parent::__construct('group');
                $this->subEl = array(new Text('group[field]'));
            }

            protected function pregSplitBracketElementName($elementName)
            {
                if ($this->failNextSplit) {
                    $this->failNextSplit = false;

                    return false;
                }

                return parent::pregSplitBracketElementName($elementName);
            }
        };
        $method = new \ReflectionMethod($composite, 'splitBracketElementNameParts');
        $method->setAccessible(true);
        $composite->failNextSplit = true;
        $this->assertSame(array(), $method->invoke($composite, 'group[field]'));
    }

    public function testControllerMagicCallThrowsForUnknownHelper(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $this->expectException(\Exception::class);
        $controller->totallyUnknownHelperMethod();
    }

    public function testControllerProcessMiddlewaresPipesMiddlewareInstance(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $middleware = new class($controller) extends \Ufw\Middleware\Base {
            public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $pipe = new \ReflectionMethod(ControllerBase::class, 'processMiddlewares');
        $pipe->setAccessible(true);
        $pipe->invoke($controller, array($middleware));
        $this->assertSame(200, $app->getResponse()->getStatusCode());
    }

    public function testHelperGetRequestThrowsWhenMissing(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setRequest(null);
        $partial = new Partial();
        $partial->setController($controller);
        $this->expectException(\RuntimeException::class);
        $partial->getRequest();
    }

    public function testHelperResolvesControllerFromApplicationWhenUnset(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setCurrentController($controller);
        $partial = new Partial();
        $this->assertSame($controller, $partial->getController());
    }

    public function testModelGetUserDelegatesToApplicationHelper(): void
    {
        ApplicationFixtures::bareWithController();
        $model = new class() extends ModelBase {
        };
        $this->assertInstanceOf(\Ufw\Helper\User::class, $model->getUser());
    }

    public function testTrimFilterLeavesNonScalarValuesUntouched(): void
    {
        $filter = new Trim();
        $obj = new \stdClass();
        $this->assertSame($obj, $filter->filter($obj));
    }

    public function testFormValidatorCreateMessageHandlesObjectsAndNestedArrays(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $element = new Text('t', array('label' => 'T'));
        $validator = new Required();
        $validator->setElement($element);
        $msg = new \ReflectionMethod(Required::class, 'createMessage');
        $msg->setAccessible(true);
        $nested = $msg->invoke($validator, Required::REQUIRED, array('a', array('b', 'c')));
        $this->assertIsString($nested);
        $objectMsg = $msg->invoke($validator, Required::REQUIRED, new \stdClass());
        $this->assertIsString($objectMsg);
        $this->assertNotSame('', $objectMsg);
    }

    public function testFormAddValidatorRejectsDefinitionWithoutName(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $element = new Text('field', array('label' => 'Field'));
        $this->expectException(\InvalidArgumentException::class);
        $element->addValidator(array('options' => array()));
    }

    public function testDateRangeGetValueToReadsNestedArray(): void
    {
        $range = new DateRange('period', array('label' => 'Period'));
        $range->setValue(array('from' => '2024-01-01', 'to' => '2024-01-31'));
        $this->assertStringContainsString('2024', $range->getValueTo());
    }

    public function testRegExBuildUrlSkipsNonStringPlaceholderNames(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $router = new Router($app, TestHttpRequest::withAttributes(array()), array(
            'default' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $route = new RegEx($router, array(
            'urlBuilder' => array(
                'compiled' => array(
                    '/item/:id' => array('id', 99, null),
                ),
                '/item/:id',
            ),
        ));
        $url = $route->buildUrl(array('id' => 7));
        $this->assertStringContainsString('7', $url);
    }

    public function testRegExUrlBuilderIgnoresNonStringCompiledKeys(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $router = new Router($app, TestHttpRequest::withAttributes(array()), array(
            'default' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $route = new RegEx($router, array(
            'urlBuilder' => array(
                'compiled' => array(
                    123 => array('id'),
                    '/ok/:id' => array('id'),
                ),
                '/ok/:id',
            ),
        ));
        $method = new \ReflectionMethod(RegEx::class, 'getUrlBuilderString');
        $method->setAccessible(true);
        $pattern = $method->invoke($route, array('id' => 1));
        $this->assertSame('/ok/:id', $pattern);
    }

    public function testFormElementGetAttribsTranslatesTitle(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $element = new Text('t', array('label' => 'T', 'title' => 'Tip'));
        $attribs = $element->getAttribs();
        $this->assertArrayHasKey('title', $attribs);
        $this->assertNotSame('', (string) $attribs['title']);
    }

    public function testFormGetValidatorBuildsFromArrayDefinitionWithNumericOptions(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $element = new Text('len', array('label' => 'Len'));
        $element->addValidator(array(
            'name' => 'StringLength',
            'options' => array('min' => 2, 'max' => 10),
        ));
        $validator = $element->getValidator('StringLength');
        $this->assertInstanceOf(\Ufw\Form\Validator\StringLength::class, $validator);
    }

    public function testControllerCallSurvivesWhenApplicationRemovedFromRegistry(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        \Ufw\Registry::getInstance()->remove('application');
        $this->expectException(\Exception::class);
        $controller->partial('x.php');
    }
}

class ArrayAccessPropertyFixture extends ArrayAccess
{
    /** @var string */
    public $realProp = '';
}
