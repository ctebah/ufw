<?php
namespace Ufw\Tests\Unit;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Ufw\Application;
use Ufw\Component\Csrf;
use Ufw\Db\Db;
use Ufw\Envs;
use Ufw\Form\Element\CheckboxSet;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Form\Validator\RegEx;
use Ufw\Helper\Bootstrap4;
use Ufw\Helper\Bootstrap4plain;
use Ufw\Helper\FlashMessenger;
use Ufw\InfoHash;
use Ufw\InfoHashArray;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Request;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\ApiClientRequesterProbe;

class FormAndInfraTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientRequesterProbe::resetInstance();
        Paths::setApplicationPath(TestAppPaths::app());
        $this->resetFlashMessengerStatics();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            session_write_close();
        }
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

    public function testDbRowFieldArrayKeyCoversLegacyOffsetBranches(): void
    {
        $method = new \ReflectionMethod(Db::class, 'rowFieldArrayKey');
        $method->setAccessible(true);
        $this->assertSame(7, $method->invoke(null, 7));
        $this->assertSame('', $method->invoke(null, ''));
        $this->assertSame(42, $method->invoke(null, '42'));
        $this->assertSame(-3, $method->invoke(null, '-3'));
        $this->assertSame('abc', $method->invoke(null, 'abc'));
        $this->assertSame(1, $method->invoke(null, 1.9));
        $this->assertSame(0, $method->invoke(null, false));
        $this->assertSame(1, $method->invoke(null, true));
        $this->assertSame('', $method->invoke(null, null));
        $this->assertSame('', $method->invoke(null, array()));
    }

    public function testInfoHashWriteArrayKeyCoversLegacyOffsetBranches(): void
    {
        $hash = new InfoHash('id', 'code');
        $method = new \ReflectionMethod(InfoHash::class, 'writeArrayKey');
        $method->setAccessible(true);
        $this->assertSame(2, $method->invoke($hash, 2));
        $this->assertSame('', $method->invoke($hash, ''));
        $this->assertSame(9, $method->invoke($hash, '9'));
        $this->assertSame(0, $method->invoke($hash, 0.5));
        $this->assertSame('', $method->invoke($hash, null));
        $this->assertSame('', $method->invoke($hash, new \stdClass()));
    }

    public function testBootstrap4FormRenderHelperBasePaths(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $helper = new Bootstrap4();
        $helper->setController($controller);

        $form = new class(array('action' => '/post')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $this->assertNotSame('', trim($helper->form($form)));

        $formCustom = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $formCustom->setViewScript('bootstrap4/form/form.php');
        $this->assertNotSame('', trim($helper->form($formCustom)));

        $multi = new Select('roles', array('multiOptions' => array('a' => 'A', 'b' => 'B')));
        $multi->setRenderScript(null);
        $multi->setMetadata(array('note' => 'pick one'));
        $this->assertNotSame('', trim($helper->field($multi)));

        $passwordLike = new Text('secret');
        $passwordLike->setRenderScript(null);
        $this->assertNotSame('', trim($helper->field($passwordLike)));
    }

    public function testBootstrap4plainCheckboxSetAndRenderScriptRewrite(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $helper = new Bootstrap4plain();
        $helper->setController($controller);

        $set = new CheckboxSet('opts', array('multiOptions' => array('a' => 'A')));
        $set->setMetadata(array('group' => 'g'));
        $html = $helper->field($set);
        $this->assertStringContainsString('checkbox', $html);

        $text = new Text('t');
        $text->setRenderScript('bootstrap4plain/form/input.php');
        $this->assertNotSame('', trim($helper->field($text)));
    }

    public function testFormMagicGetAndTranslateBranches(): void
    {
        ApplicationFixtures::bareWithController();
        $form = new class(array('action' => '/x', 'class' => 'frm')) extends \Ufw\Form\Base {
            public function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $this->assertSame('/x', $form->action);
        $this->assertInstanceOf(Text::class, $form->title);
        $this->assertSame('frm', $form->class);

        $translatorProp = new \ReflectionProperty($form, 'translator');
        $translatorProp->setAccessible(true);
        $translatorProp->setValue($form, array('not', 'callable'));
        $this->assertSame('Hello', $form->translate('Hello'));
        $this->assertSame(
            array('a' => 'one', 'b' => 'two'),
            $form->translate(array('a' => 'one', 'b' => 'two'))
        );

        $translatorProp->setValue($form, function ($s) {
            return 'T:' . $s;
        });
        $this->assertSame(array('a' => 'T:one'), $form->translate(array('a' => 'one')));
    }

    public function testCsrfValidateAndRotateSessionPaths(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array();
        $token = Csrf::token('gate');
        $this->assertTrue(Csrf::validate('gate', $token));
        $this->assertFalse(Csrf::validate('gate', ''));
        $this->assertFalse(Csrf::validate('missing', $token));
        Csrf::rotate('gate');
        $this->assertFalse(Csrf::validate('gate', $token));
    }

    public function testFlashMessengerSessionAndControlBranches(): void
    {
        ApplicationFixtures::bareWithController();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array(
            'ufw__fM' => array(
                99 => array('skip'),
                'info' => array('prev'),
            ),
        );
        $this->resetFlashMessengerStatics();
        new FlashMessenger();

        $fm = new FlashMessenger();
        $fm->addMessage(null);
        $fm->off()->addMessage('hidden');
        $fm->on()->addMessage('shown');
        $this->assertContains('shown', $fm->getCurrentMessages('info'));
    }

    public function testRequestServerParamsAndPsrDelegation(): void
    {
        $sr = new ServerRequest(
            array('HTTP_HOST' => 'h.test'),
            array(),
            '/',
            'GET',
            'php://input',
            array(),
            array(),
            array('q' => '1')
        );
        $req = new Request(array(), array(), array(), null, $sr);
        $this->assertIsArray($req->getServer());
        $this->assertSame('1', $req->getParam('q'));
        $this->assertSame('/', $req->getRequestTarget());
    }

    public function testApplicationHasActionEmptyActionWithHttpMethodReturnsFalse(): void
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $bothEmpty = $has->invoke($app, 'ping', '', null, null);
        $this->assertIsArray($bothEmpty);
        $this->assertNull($bothEmpty['action']);
        $this->assertFalse($has->invoke($app, 'ping', 'index', new \stdClass(), null));
    }

    public function testApiClientRequesterRawSuccessAndExceptionPaths(): void
    {
        ApplicationFixtures::bareWithController();
        $requester = ApiClientRequesterProbe::getInstance();
        $requester->disableDebugLog();

        $ok = new MockHandler(array(new PsrResponse(200, array(), 'ok')));
        $requester->injectHttpClient(new GuzzleClient(array(
            'handler' => HandlerStack::create($ok),
            'base_uri' => 'http://api.test',
        )));
        $requester->raw('GET', '/ok', array());
        $this->assertFalse($requester->getLastResponse()->hasException());

        $client = new MockHandler(array(
            new ClientException('bad', new PsrRequest('GET', '/c'), new PsrResponse(400)),
        ));
        $requester->injectHttpClient(new GuzzleClient(array(
            'handler' => HandlerStack::create($client),
            'base_uri' => 'http://api.test',
        )));
        $requester->raw('GET', '/c', array());
        $this->assertTrue($requester->getLastResponse()->isClientException());

        $connect = new MockHandler(array(
            new ConnectException('down', new PsrRequest('GET', '/d')),
        ));
        $requester->injectHttpClient(new GuzzleClient(array(
            'handler' => HandlerStack::create($connect),
            'base_uri' => 'http://api.test',
        )));
        $requester->raw('GET', '/d', array());
        $this->assertTrue($requester->getLastResponse()->hasException());

        ApplicationFixtures::tearDownRegistry();
        $this->assertNull($requester->exposeGetApplication());

        $require = new \ReflectionMethod(\Ufw\Component\ApiClient\Requester::class, 'requireResponse');
        $require->setAccessible(true);
        $this->expectException(\RuntimeException::class);
        $require->invoke($requester, null);
    }

    public function testApiClientRequesterDebugLoggerAndRequestExceptionOnPut(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        ApplicationFixtures::bareWithController();
        $requester = ApiClientRequesterProbe::getInstance()->enableDebugLog();
        $requester->exposeGetLogger();
        $requester->exposeGetHttpClient();

        $handler = new MockHandler(array(
            new ConnectException('lost', new PsrRequest('PUT', '/u')),
        ));
        $requester->injectHttpClient(new GuzzleClient(array(
            'handler' => HandlerStack::create($handler),
            'base_uri' => 'http://api.test',
        )));
        $requester->put('/u', array('x' => 1));
        $this->assertTrue($requester->getLastResponse()->hasException());
    }

    public function testPathsExceptionLogPathRejectsNonStringConfig(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $this->assertSame(
            Paths::application() . '/log/exceptions.txt',
            Paths::exceptionLogFilePath(array('logging' => array('exception_log_path' => array('bad'))))
        );
    }

    public function testEnvsReadsRegistryEnvLabel(): void
    {
        Envs::setRuntimeEnvLabel(null);
        Registry::getInstance()->set('env', 'staging');
        $this->assertSame('staging', Envs::runtimeLabel());
        Envs::setRuntimeEnvLabel(null);
        Registry::getInstance()->remove('env');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEnvsCastsNumericApplicationEnvConstant(): void
    {
        define('APPLICATION_ENV', 7);
        \Ufw\Envs::setRuntimeEnvLabel(null);
        $this->assertSame('7', \Ufw\Envs::runtimeLabel());
    }
}
