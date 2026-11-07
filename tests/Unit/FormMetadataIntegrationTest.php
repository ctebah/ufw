<?php
namespace Ufw\Tests\Unit;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\ApiClient\Exception as ApiClientException;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Form\Element\SubElementsAbstract;
use Ufw\Form\Element\Text;
use Ufw\Helper\Bootstrap4;
use Ufw\Helper\Bootstrap4plain;
use Ufw\Helper\FlashMessenger;
use Ufw\InfoHash;
use Ufw\JsOut;
use Ufw\Paths;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;

class FormMetadataIntegrationTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
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

    public function testFormElementSetMetadataViaMagicCallAndFormRenderMetadataPartial(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $helper = new Bootstrap4();
        $helper->setController($controller);
        $el = new class('x') extends Text {
            public function getRenderScript()
            {
                return '';
            }
        };
        $el->setMetadata(array('hint' => 'pick'));
        $this->assertSame(array('hint' => 'pick'), $el->getMetadata());
        $html = $helper->field($el);
        $this->assertNotSame('', trim($html));

        $plain = new Bootstrap4plain();
        $plain->setController($controller);
        $cb = new \Ufw\Form\Element\Checkbox('agree');
        $cb->setMetadata(array('x' => 1));
        $this->assertNotSame('', trim($plain->field($cb)));
    }

    public function testFormGetValuesIncludesSubElementYields(): void
    {
        ApplicationFixtures::bareWithController();
        $form = new class(array('action' => '/save')) extends \Ufw\Form\Base {
            public function init()
            {
                $this->addElement(new class('group') extends SubElementsAbstract {
                    public function init()
                    {
                        $this->subEl[] = new Text('child');
                    }
                });
            }
        };
        $form->populate(array('group' => array(), 'child' => 'v'));
        $values = $form->getValues();
        $this->assertSame('v', $values['child']);
    }

    public function testFormMagicGetOptionAndAttribBranches(): void
    {
        ApplicationFixtures::bareWithController();
        $form = new class(array('action' => '/save')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $form->setOption('label', 'Title');
        $this->assertSame('Title', $form->label);
        $form->setAttrib('title', 'My form');
        $this->assertSame('My form', $form->title);
    }

    public function testInfoHashSetPropertyValueAndXmlSerializeBranches(): void
    {
        $hash = new InfoHash('id', 'code');
        $row = (object) array('id' => 1, 'code' => 'A', 'tags' => 'A');
        $hash->info[1] = $row;
        $method = new \ReflectionMethod(InfoHash::class, '__set_property_value');
        $method->setAccessible(true);
        $method->invoke($hash, $row, 'id', 'tags', false, 'code');
        $this->assertIsArray($row->tags);
    }

    public function testApiClientExceptionMessageFromPrevious(): void
    {
        $req = new PsrRequest('GET', '/x');
        $resp = new PsrResponse(502, array(), '<html>bad</html>');
        $previous = new RequestException('<html>' . str_repeat('x', 2100) . '</html>', $req, $resp);
        $ex = new ApiClientException(null, null, $previous);
        $this->assertSame('Bad Gateway', $ex->getMessage());
    }

    public function testControllerProcessMiddlewaresCallableAndEmptyList(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $pipe = new \ReflectionMethod(ControllerBase::class, 'processMiddlewares');
        $pipe->setAccessible(true);
        $pipe->invoke($controller, array());
        $pipe->invoke($controller, array(function ($req, $handler) {
            return $handler->handle($req);
        }));
        $this->assertSame(200, $app->getResponse()->getStatusCode());
    }

    public function testFlashMessengerSkipsSessionStartWhenAlreadyActive(): void
    {
        ApplicationFixtures::bareWithController();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array('ufw__fM' => array('info' => array('old')));
        $this->resetFlashMessengerStatics();
        new FlashMessenger();
        $fm = new FlashMessenger();
        $this->assertContains('old', $fm->getMessages('info'));
    }

    public function testJsOutSanitizeMergeAndFetchPlaceholderFallbacks(): void
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'jsOutStorage' => array(
                'webpath' => '/gc',
                'absrootpath' => sys_get_temp_dir(),
            ),
        ));
        $js = JsOut::getInstance();
        $ref = new \ReflectionClass(JsOut::class);
        $contentProp = $ref->getProperty('jsContent');
        $contentProp->setAccessible(true);
        $contentProp->setValue($js, array('ready' => 'ready();', 'ext' => 'ext();', 'inline' => ''));
        $concat = $ref->getMethod('_concatenate');
        $concat->setAccessible(true);
        $concat->invoke($js, true);
        $sanitize = $ref->getMethod('_sanitize');
        $sanitize->setAccessible(true);
        $sanitize->invoke($js);

        $appRef = new \ReflectionClass($app);
        $hProp = $appRef->getProperty('helpersInstantiated');
        $hProp->setAccessible(true);
        $saved = $hProp->getValue($app);
        $hProp->setValue($app, array('Placeholder' => 'not-callable'));
        $fetch = $ref->getMethod('fetchPlaceholder');
        $fetch->setAccessible(true);
        $this->assertSame('', $fetch->invoke($js, 'jsReady'));
        $hProp->setValue($app, $saved);

        $odd = new class() {
            public function __invoke($name)
            {
                return new \ArrayObject(array('a' => 1));
            }
        };
        $hProp->setValue($app, array('Placeholder' => $odd));
        $this->assertSame('', $fetch->invoke($js, 'anything'));
        $hProp->setValue($app, $saved);
    }
}
