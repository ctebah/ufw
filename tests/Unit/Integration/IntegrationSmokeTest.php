<?php
namespace Ufw\Tests\Unit\Integration;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Db\Db;
use Ufw\Form\Element\File;
use Ufw\Form\Element\ReCaptcha;
use Ufw\Form\Validator\FileUpload;
use Ufw\Form\Validator\ReCaptcha as ReCaptchaValidator;
use Ufw\Helper\Partial;
use Ufw\InfoHash;
use Ufw\Model\Base as ModelBase;
use Ufw\Model\Behaviour\Base as BehaviourBase;
use Ufw\Model\Record\Base as RecordBase;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Form\Element\Text;
use Laminas\Diactoros\UploadedFile;

class IntegrationSmokeTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        $_SERVER = array();
        parent::tearDown();
    }

    public function testGlobalFunctionsAndPregGrepKeys()
    {
        $this->assertSame(array('a' => 1), \Ufw\filter_string_keyed_array(array('a' => 1, 0 => 'x')));
        $this->assertSame(array('foo' => 1), \Ufw\preg_grep_keys('/^foo/', array('foo' => 1, 'bar' => 2)));
        $this->assertSame(array(5 => 'v'), \Ufw\preg_grep_keys('/5/', array(5 => 'v', 6 => 'x')));
        $this->assertSame(array(), \Ufw\preg_grep_keys('~(~', array('a' => 1)));
    }

    public function testDbLoggingOptionsAndQueryLogTable()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $db = Db::instance();
        $db->setThrowOnError(true);
        $this->assertTrue($db->getThrowOnError());
        $db->setLogFile('/tmp/gate-db.log');
        $db->setLogPost(true);
        $db->query_log_data = array(array('sql' => 'SELECT 1', 'ms' => '1'));
        $_SERVER['HTTP_X_REQUESTED_WITH'] = '';
        ob_start();
        Db::query_log_table();
        $html = ob_get_clean();
        $this->assertStringContainsString('query-log-table', $html);
    }

    public function testRegistryDetachRemovesObserver()
    {
        $registry = Registry::getInstance();
        $observer = new class implements \SplObserver {
            public function update(\SplSubject $subject): void
            {
            }
        };
        $registry->attach($observer, 'gate');
        $registry->detach($observer, 'gate');
        $this->assertTrue(true);
    }

    public function testModelAndBehaviourApplicationAccess()
    {
        list($app) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $model = new class extends ModelBase {
            protected $recordClassName = WidgetPlain::class;
        };
        $this->assertSame($app, $model->getApplication());
        $this->assertInstanceOf(Db::class, $model->getDb());
        $this->assertSame($model->getUser(), $model->user);

        $record = new WidgetPlain(array('id' => 1, 'code' => 'c', 'data' => '{}'));
        $this->assertSame($app, $record->getApplication());

        $behaviour = new class($record) extends BehaviourBase {
        };
        $this->assertSame($app, $behaviour->getApplication());
    }

    public function testFormFileReCaptchaValidatorsAndCompositeElement()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array('routes' => array()),
            'storage' => sys_get_temp_dir(),
        ));

        $file = new File('upload');
        $file->setAttrib('accept', '.pdf');
        $this->assertSame(sys_get_temp_dir(), $file->getBaseStorageDir());
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'gate');
        rewind($stream);
        $upload = new UploadedFile($stream, 4, UPLOAD_ERR_OK, 'x.txt', 'text/plain');
        $file->setValue($upload);
        $file->receiveFile();
        $meta = $file->getMeta();
        $this->assertNotEmpty($meta);
        $this->assertArrayHasKey('storedFilename', $meta);

        $recaptcha = new ReCaptcha('g-recaptcha', array(
            'secret' => 'sec',
            'site_key' => 'site',
        ));
        $this->assertSame('sec', $recaptcha->getOption('secret'));
        $stubValidator = new class('sec', 'site') extends ReCaptchaValidator {
            public function isValid($value, $context = null)
            {
                return false;
            }
        };
        $recaptcha->setValidator('reCaptcha', $stubValidator);
        $this->assertFalse($recaptcha->isValid(array()));
        $this->assertFalse($recaptcha->isValid(array()));

        $uploadField = new File('upload2');
        $uploadField->setAttrib('accept', '.pdf');
        $validator = new FileUpload($uploadField);
        $this->assertTrue($validator->isValid(null, array()));
        $bad = new UploadedFile(fopen('php://temp', 'r'), 4, UPLOAD_ERR_PARTIAL, 'bad.txt', 'text/plain');
        $uploadField->setValue($bad);
        $this->assertFalse($validator->isValid(null, array()));

        $wrongExt = new UploadedFile(fopen('php://temp', 'r'), 4, UPLOAD_ERR_OK, 'note.txt', 'text/plain');
        $uploadField->setValue($wrongExt);
        $this->assertFalse($validator->isValid(null, array()));

        $recapVal = new ReCaptchaValidator('s', 'k');
        $this->assertFalse($recapVal->isValid('', array()));

        $required = new \Ufw\Form\Validator\Required('r');
        $implode = new \ReflectionMethod(\Ufw\Form\Validator\Base::class, 'implodeRecursive');
        $implode->setAccessible(true);
        $this->assertSame('a', $implode->invoke($required, array('a')));

        $composite = new class('group') extends \Ufw\Form\Element\SubElementsAbstract {
            public function init()
            {
                $this->subEl[] = new Text('part');
            }
        };
        $composite->setValue(array('part' => 'v'), array('part' => 'v'));
        $values = iterator_to_array($composite->yieldSubValues());
        $this->assertSame('v', $values['part']);

        $form = new class(array('action' => '/up')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('t'));
            }
        };
        $this->assertNotEmpty((string) $form);
    }

    public function testPartialBreadcrumbsRendersStringCaptions()
    {
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $partial = new Partial();
        $partial->setController($controller);
        $html = $partial->partial('breadcrumbs.php', array(
            'items' => array(
                array('caption' => 'Home', 'content' => array('href' => '/home')),
                array('caption' => 'Here', 'content' => array('href' => '/here')),
            ),
        ));
        $this->assertStringContainsString('breadcrumb', $html);
        $this->assertStringContainsString('Home', $html);

        $items = array(
            array('caption' => 'A', 'content' => array('href' => '/a')),
            array('caption' => 'B', 'content' => array('href' => '/b')),
        );
        $html4 = $partial->partial('bootstrap4/breadcrumbs.php', array('items' => $items));
        $this->assertStringContainsString('breadcrumb', $html4);
        $html5 = $partial->partial('bootstrap5/breadcrumbs.php', array('items' => $items));
        $this->assertStringContainsString('breadcrumb', $html5);
        $menu = $partial->partial('menu-generic.php', array(
            'items' => array(
                array('caption' => 'Nav', 'content' => array('href' => '/nav')),
            ),
            'currentUrl' => '/nav',
        ));
        $this->assertStringContainsString('Nav', $menu);

        $render = new \ReflectionMethod(\Ufw\Helper\ViewRendererBase::class, 'getScriptName');
        $render->setAccessible(true);
        $this->assertSame('index', $render->invoke($partial));
    }

    public function testRequestWithUploadedFiles()
    {
        $file = new UploadedFile(fopen('php://temp', 'r'), 0, UPLOAD_ERR_OK, 'a.txt', 'text/plain');
        $psr = new \Ufw\ServerRequest(array(), array('up' => $file), '/up', 'POST', 'php://input');
        $request = new \Ufw\Request(array(), array(), array(), null, $psr);
        $with = $request->withUploadedFiles(array('up' => $file));
        $this->assertSame($file, $with->getUploadedFiles()['up']);
    }

    public function testInfoHashGetTotalCountFromEntries()
    {
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => 1, 'code' => 'a'));
        $hash->setDataQuery('SELECT id, code FROM items');
        $this->assertSame(1, $hash->getTotalCount());
    }
}
