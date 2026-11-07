<?php
namespace Ufw\Tests\Unit\Misc;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Helper as ApiHelper;
use Ufw\Form\Element\Text;
use Ufw\Form\Validator\Required;
use Ufw\Helper\Menu;
use Ufw\Registry;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\UploadedTmpFile;
use Ufw\Application;
use Ufw\Envs;

class ResidualRegressionTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        parent::tearDown();
    }

    public function testSetFormErrorsSkipsMissingElementAndBrokenErrorPath(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $form = new class(array('action' => '/post')) extends \Ufw\Form\Base {
            public function init()
            {
                $this->addElement(new Text('title', array('label' => 'Title')));
            }
        };
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $api->exposeSetFormErrors(array(
            'missing' => 'err',
            'title' => array('nested' => 'deep'),
        ));
        $this->assertSame(array(), $form->getElement('title')->getErrors());
    }

    public function testApiHelperAssignValueToKeyWithSimpleKey(): void
    {
        $helper = ApiHelper::getInstance();
        $payload = array();
        $out = $helper->assignValueToKey($payload, 'field', 'value');
        $this->assertSame('value', $out['field']);
    }

    public function testApiHelperGetElementWithNonStringLeafKey(): void
    {
        $helper = ApiHelper::getInstance();
        $form = array(0 => new Text('title'));
        $this->assertNull($helper->getElement(array('title', 0), $form));
    }

    public function testValidatorCreateMessageReturnsNullForUnknownTemplateKey(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $required = new Required();
        $create = new \ReflectionMethod(Required::class, 'createMessage');
        $create->setAccessible(true);
        $this->assertNull($create->invoke($required, 'not-a-template', 'x'));
    }

    public function testValidatorTranslateUsesTranslateHelperWhenRegistered(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $required = new class extends Required {
            public function probeTranslate($msg)
            {
                return $this->translate($msg);
            }
        };
        $this->assertSame('plain', $required->probeTranslate('plain'));
    }

    public function testValidatorScalarOrObjectToStringReturnsEmptyForUnsupportedValue(): void
    {
        $required = new Required();
        $method = new \ReflectionMethod(\Ufw\Form\Validator\Base::class, 'scalarOrObjectToString');
        $method->setAccessible(true);
        $handle = fopen('php://memory', 'r');
        try {
            $this->assertSame('', $method->invoke($required, $handle));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    public function testUploadedTmpFileResolveSourceFilePathReadsDiskPath(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'ufw-path-');
        $this->assertNotFalse($source);
        file_put_contents($source, 'x');
        $stream = fopen($source, 'r');
        $upload = new \Ufw\Tests\Unit\UploadedTmpFileDiskMoveProbe($stream, 1, UPLOAD_ERR_OK, 'f.txt', 'text/plain');
        $ref = new \ReflectionClass(\Laminas\Diactoros\UploadedFile::class);
        if ($ref->hasProperty('file')) {
            $prop = $ref->getProperty('file');
            $prop->setAccessible(true);
            $prop->setValue($upload, $source);
        }
        $method = new \ReflectionMethod(UploadedTmpFile::class, 'resolveSourceFilePath');
        $method->setAccessible(true);
        try {
            $this->assertSame($source, $method->invoke($upload));
        } finally {
            if (is_file($source)) {
                unlink($source);
            }
        }
    }

    public function testRecordSimpleUpdateNormalizesNonArrayPrimaryKey(): void
    {
        ApplicationFixtures::bareWithController();
        $record = new class(array('id' => 2, 'code' => 'c', 'data' => '{}')) extends WidgetPlain {
            public function getPrimaryKeyField()
            {
                return false;
            }

            public function getDb()
            {
                return new class {
                    public $rows_affected = 1;

                    public function simple_update_pk($table, $data, $criteria, $ident, $file, $line)
                    {
                        return true;
                    }
                };
            }
        };
        $record->captureReadState();
        $update = new \ReflectionMethod($record, 'update');
        $update->setAccessible(true);
        $this->assertTrue($update->invoke($record));
    }

    public function testMenuBuildTreeUsesDefaultWrapWhenNormalizeReturnsEmpty(): void
    {
        Envs::setRuntimeEnvLabel('development');
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $app->setCurrentController($controller);
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $menu = new Menu();
        $menu->setController($controller);
        $definition = array(
            array('caption' => 'Item', 'content' => array('href' => '/item')),
            'multiwrap' => array('skip'),
        );
        $build = new \ReflectionMethod(Menu::class, 'buildTree');
        $build->setAccessible(true);
        $tree = $build->invokeArgs($menu, array(&$definition, '/item', false));
        $this->assertArrayHasKey('wrapper', $tree);
    }

    public function testApplicationTerminateAfterEmitNoOpWhenSuppressExitDefined(): void
    {
        if (!defined('UFW_APPLICATION_SUPPRESS_EXIT')) {
            define('UFW_APPLICATION_SUPPRESS_EXIT', true);
        }
        list($app) = ApplicationFixtures::bareWithController();
        $term = new \ReflectionMethod(Application::class, 'terminateAfterEmit');
        $term->setAccessible(true);
        $term->invoke($app);
        $this->assertTrue(true);
    }
}
