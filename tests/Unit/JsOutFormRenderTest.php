<?php
namespace Ufw\Tests\Unit;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Form\Element\Csrf as CsrfElement;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Form\Element\Textarea;
use Ufw\Helper\Bootstrap3plain;
use Ufw\Helper\Bootstrap4;
use Ufw\Helper\FlashMessenger;
use Ufw\InfoHash;
use Ufw\JsOut;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Request;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\ApiClientRequesterProbe;

class JsOutFormRenderTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientRequesterProbe::resetInstance();
        Paths::setApplicationPath(TestAppPaths::app());
        $ref = new \ReflectionClass(JsOut::class);
        $prop = $ref->getProperty('_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
        $ph = new \ReflectionClass(\Ufw\Helper\Placeholder::class);
        $container = $ph->getProperty('container');
        $container->setAccessible(true);
        $container->setValue(null, null);
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

    public function testFormRenderHelperBaseUsesCustomViewScriptAndAutoFieldScripts(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $helper = new Bootstrap4();
        $helper->setController($controller);

        $form = new class(array('action' => '/save')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $form->setViewScript('bootstrap4/form/form.php');
        $this->assertNotSame('', trim($helper->form($form)));

        $emptyScript = function ($class, array $opts = array()) {
            if ($class === Select::class) {
                return new class('roles', array('multiOptions' => array('a' => 'A'))) extends Select {
                    public function getRenderScript()
                    {
                        return '';
                    }
                };
            }
            if ($class === CsrfElement::class) {
                return new class('token') extends CsrfElement {
                    public function getRenderScript()
                    {
                        return '';
                    }
                };
            }
            if ($class === Textarea::class) {
                return new class('body') extends Textarea {
                    public function getRenderScript()
                    {
                        return '';
                    }
                };
            }
            return new class('n') extends Text {
                public function getRenderScript()
                {
                    return '';
                }
            };
        };

        $csrf = $emptyScript(CsrfElement::class);
        $this->assertNotSame('', trim($helper->field($csrf)));

        $input = $emptyScript(Text::class);
        $input->setMetadata(array('tip' => 'x'));
        $this->assertNotSame('', trim($helper->field($input)));

        $multi = $emptyScript(Select::class);
        $multi->setMetadata(array('tip' => 'y'));
        $this->assertNotSame('', trim($helper->field($multi)));

        $area = $emptyScript(Textarea::class);
        $this->assertIsString($helper->field($area));
    }

    public function testBootstrap3plainCheckboxSetAndMetadata(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $helper = new Bootstrap3plain();
        $helper->setController($controller);
        $set = new \Ufw\Form\Element\CheckboxSet('tags', array('multiOptions' => array('a' => 'A')));
        $set->setMetadata(array('group' => 'g'));
        $this->assertStringContainsString('checkbox', $helper->field($set));
    }

    public function testJsOutReuseBranchAndSanitizeReadyMerge(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-jsout-' . uniqid('', true);
        $gc = $tmp . DIRECTORY_SEPARATOR . 'gc';
        mkdir($gc, 0777, true);
        list($app) = ApplicationFixtures::bareWithController(array(
            'jsOutStorage' => array(
                'webpath' => '/gc',
                'absrootpath' => $tmp,
                'serve-minified' => true,
            ),
        ));
        $app->getHelper('placeholder')->placeholder('jsReady')->append('readyFn();');
        $app->getHelper('placeholder')->placeholder('jsExt')->append('extFn();');

        $sig = md5('readyFn();~extFn();');
        file_put_contents($gc . DIRECTORY_SEPARATOR . $sig . '.min.js', 'cached');

        $js = JsOut::getInstance();
        $out = $js->readyPlaceholder();
        $this->assertStringContainsString('.min.js', $out);

        $ref = new \ReflectionClass(JsOut::class);
        $contentProp = $ref->getProperty('jsContent');
        $contentProp->setAccessible(true);
        $contentProp->setValue($js, array('ready' => 'inner();', 'ext' => '', 'inline' => ''));
        $concat = $ref->getMethod('_concatenate');
        $concat->setAccessible(true);
        $concat->invoke($js, true);
        $sanitize = $ref->getMethod('_sanitize');
        $sanitize->setAccessible(true);
        $sanitize->invoke($js);
        $merged = $contentProp->getValue($js);
        $this->assertArrayNotHasKey('ready', $merged);
    }

    public function testJsOutWrapperConfigEdgeCasesAndPlaceholderFetch(): void
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'jsOutStorage' => array(
                'readyWrapper' => '',
            ),
        ));
        $js = JsOut::getInstance();
        $mode = new \ReflectionMethod(JsOut::class, 'getReadyWrapperMode');
        $mode->setAccessible(true);
        $this->assertSame('module', $mode->invoke($js));

        $app->setConfig(array('jsOutStorage' => array('readyWrapper' => 'invalid-mode')));
        $this->assertSame('module', $mode->invoke($js));

        $fetch = new \ReflectionMethod(JsOut::class, 'fetchPlaceholder');
        $fetch->setAccessible(true);
        $wrap = new \ReflectionMethod(JsOut::class, 'wrapJsReadyFragments');
        $wrap->setAccessible(true);
        $this->assertSame('', $wrap->invoke($js, '   '));

        list($app2) = ApplicationFixtures::bareWithController();
        $app2->getHelper('placeholder')->placeholder('jsCount')->set(42);
        $this->assertSame('42', $fetch->invoke(JsOut::getInstance(), 'jsCount'));
    }

    public function testApplicationHasActionApiEarlyReturnWithoutHttpVerb(): void
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'ping', 'index', null, null);
        $this->assertIsArray($result);
        $this->assertSame('api', $result['kind']);
        $this->assertNull($result['action']);
    }

    public function testInfoHashDumpOptListAndWriteArrayKeyBool(): void
    {
        $hash = new InfoHash('id', 'name');
        $hash->info[1] = (object) array('id' => 1, 'name' => 'One');
        $html = $hash->dump_opt_list(null);
        $this->assertStringContainsString('One', $html);

        $write = new \ReflectionMethod(InfoHash::class, 'writeArrayKey');
        $write->setAccessible(true);
        $this->assertSame(1, $write->invoke($hash, true));
        $this->assertSame(0, $write->invoke($hash, false));

        $xml = new \ReflectionMethod(InfoHash::class, '__xml_serialize_recursive');
        $xml->setAccessible(true);
        $root = new \SimpleXMLElement('<root/>');
        $xml->invoke(null, 'key', null, $root);
        $this->assertNotNull($root);
    }

    public function testRequestMagicCallAndAttributeParam(): void
    {
        $sr = new ServerRequest(
            array(),
            array(),
            '/',
            'GET',
            'php://input',
            array(),
            array(),
            array('q' => '1'),
            null,
            '1.1',
            array('token' => 'abc')
        );
        $req = new Request(array(), array(), array(), null, $sr);
        $this->assertSame('abc', $req->getParam('token'));

        $call = new \ReflectionMethod(Request::class, '__call');
        $call->setAccessible(true);
        $params = $call->invoke($req, 'getQueryParams', array());
        $this->assertSame(array('q' => '1'), $params);
    }

    public function testFlashMessengerNamespaceFallbackBranches(): void
    {
        ApplicationFixtures::bareWithController();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array();
        $this->resetFlashMessengerStatics();
        $fm = new FlashMessenger();
        $fm->addMessage('x');
        $this->assertContains('x', $fm->getCurrentMessages(''));
        $this->assertFalse($fm->hasMessages('missing-ns'));
        $this->assertFalse($fm->clearCurrentMessages('missing-ns'));
        $this->assertFalse($fm->hasCurrentMessages('missing-ns'));
    }

    public function testApiClientRequesterServerExceptionOnPost(): void
    {
        ApplicationFixtures::bareWithController();
        $requester = ApiClientRequesterProbe::getInstance();
        $requester->disableDebugLog();
        $handler = new MockHandler(array(
            new ServerException('down', new PsrRequest('POST', '/x'), new PsrResponse(500)),
        ));
        $requester->injectHttpClient(new GuzzleClient(array(
            'handler' => HandlerStack::create($handler),
            'base_uri' => 'http://api.test',
        )));
        $requester->post('/x', array('a' => 1));
        $this->assertTrue($requester->getLastResponse()->hasException());
    }

    public function testFormBaseMagicGetUnknownReturnsNull(): void
    {
        $form = new class(array('action' => '/z')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $this->assertNull($form->missingOptionName);
    }
}
