<?php
namespace Ufw\Tests\Unit\Views;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Ufw\Form\Element\Button;
use Ufw\Form\Element\Checkbox;
use Ufw\Form\Element\CheckboxSet;
use Ufw\Form\Element\Csrf;
use Ufw\Form\Element\DateRange;
use Ufw\Form\Element\Email;
use Ufw\Form\Element\File;
use Ufw\Form\Element\Hidden;
use Ufw\Form\Element\Html;
use Ufw\Form\Element\Number;
use Ufw\Form\Element\Password;
use Ufw\Form\Element\Radio;
use Ufw\Form\Element\ReCaptcha;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Form\Element\Textarea;
use Ufw\Helper\Bootstrap3;
use Ufw\Helper\Bootstrap3plain;
use Ufw\Helper\Bootstrap4;
use Ufw\Helper\Bootstrap4plain;
use Ufw\Helper\Bootstrap5;
use Ufw\Helper\FormRenderHelperBase;
use Ufw\Helper\Partial;
use Ufw\Helper\Render;
use Ufw\Paths;
use Ufw\Router;
use Ufw\Route\Literal;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormRenderTestSupport;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\PaginatorControllerStub;

class PartialFormFeedbackTest extends TestCase
{
    use FormFixtureConfig;
    use FormRenderTestSupport;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        Paths::setApplicationPath(TestAppPaths::app());
        unset($GLOBALS['__ufw_form_check_icon_css']);
        $this->resetFormRenderHelperNames();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            session_write_close();
        }
        parent::tearDown();
    }

    /**
     * @return list<array{0: class-string<FormRenderHelperBase>}>
     */
    public static function formHelpers(): array
    {
        return array(
            array(Bootstrap3::class),
            array(Bootstrap4::class),
            array(Bootstrap4plain::class),
            array(Bootstrap5::class),
            array(Bootstrap3plain::class),
        );
    }

    #[DataProvider('formHelpers')]
    public function testFormPartialsCoverErrorSuccessAndLabelBranches($helperClass): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        /** @var FormRenderHelperBase $helper */
        $helper = new $helperClass();
        $helper->setController($controller);
        $pathProp = new \ReflectionProperty($helperClass, 'baseScriptPath');
        $pathProp->setAccessible(true);
        $this->syncFormRenderHelperName($pathProp->getValue($helper));

        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $form->setValidated(true);

        $password = new Password('secret', array('label' => 'Secret'));
        $password->setForm($form);
        $password->setLabelPlacement('above');
        $password->addError('Too weak');
        $password->setDescription('Min 8 chars');
        $fieldHtml = strtolower($helper->field($password));
        $this->assertStringContainsString('min 8 chars', $fieldHtml);
        $this->assertTrue(
            strpos($fieldHtml, 'help') !== false || strpos($fieldHtml, 'form-text') !== false,
            'description wrapper expected'
        );

        $passwordOk = new Password('secret2', array('label' => 'Secret2'));
        $passwordOk->setForm($form);
        $passwordOk->setValue('valid-pass');
        $passwordOk->setLabelPlacement('below');
        $fieldOk = $helper->field($passwordOk);
        $fieldOkLower = strtolower($fieldOk);
        $this->assertTrue(
            strpos($fieldOkLower, 'has-success') !== false || strpos($fieldOkLower, 'is-valid') !== false,
            'success state expected for password field'
        );

        $passwordIcon = new Password('secret3', array('label' => 'Secret3', 'icon' => '<i>I</i>'));
        $passwordIcon->setForm($form);
        $this->assertStringContainsString('<i>I</i>', $helper->field($passwordIcon));

        $multiSelect = new Select('roles', array(
            'label' => 'Roles',
            'multiOptions' => array('a' => 'A', 'b' => 'B'),
        ));
        $multiSelect->setForm($form);
        $multiSelect->setAllowMultiple(true);
        $multiSelect->setValue(array('a', 'b'));
        $multiSelect->addError('Pick fewer');
        $multiSelect->setDescription('Help roles');
        $multiSelect->setLabelPlacement('below');
        $this->assertNotSame('', trim($helper->field($multiSelect)));

        $multiSelectOk = new Select('roles2', array(
            'label' => 'Roles2',
            'multiOptions' => array('x' => 'X'),
            'icon' => '<span class="sel-icon">!</span>',
        ));
        $multiSelectOk->setForm($form);
        $multiSelectOk->setAllowMultiple(true);
        $multiSelectOk->setValue(array('x'));
        $this->assertNotSame('', trim($helper->field($multiSelectOk)));

        $cbOk = new Checkbox('ok2', array('label' => 'OK2'));
        $cbOk->setForm($form);
        $cbOk->setValue('1');
        $this->assertNotSame('', trim($helper->field($cbOk)));

        $areaOk = new Textarea('body2', array('label' => 'Body2'));
        $areaOk->setForm($form);
        $areaOk->setValue('text');
        $areaOk->setLabelPlacement('above');
        $this->assertNotSame('', trim($helper->field($areaOk)));

        $htmlOk = new Html('blurb2', array('value' => '<em>Hi</em>', 'label' => 'Blurb2'));
        $htmlOk->setForm($form);
        $htmlOk->setValue('<em>Hi</em>');
        $this->assertNotSame('', trim($helper->field($htmlOk)));

        $text = new Text('line', array('label' => 'Line'));
        $text->setForm($form);
        $text->setLabelPlacement('above');
        $text->addError('Bad');
        $text->setValue('filled');
        $this->assertNotSame('', trim($helper->field($text)));

        $area = new Textarea('body', array('label' => 'Body'));
        $area->setForm($form);
        $area->setLabelPlacement('below');
        $area->addError('Required');
        $this->assertNotSame('', trim($helper->field($area)));

        $radio = new Radio('choice', array('label' => 'Choice', 'multiOptions' => array('a' => 'Alpha', 'b' => 'Beta')));
        $radio->setForm($form);
        $radio->setValue('a');
        $radio->addError('Invalid');
        $radio->setDescription('Pick one');
        $this->assertNotSame('', trim($helper->field($radio)));

        $set = new CheckboxSet('opts', array('label' => 'Opts', 'multiOptions' => array('1' => 'One')));
        $set->setForm($form);
        $set->addError('Need one');
        $this->assertNotSame('', trim($helper->field($set)));

        $select = new Select('sel', array('label' => 'Sel', 'multiOptions' => array('x' => 'X')));
        $select->setForm($form);
        $select->addError('Required');
        $this->assertNotSame('', trim($helper->field($select)));

        $cb = new Checkbox('ok', array('label' => 'OK'));
        $cb->setForm($form);
        $cb->addError('Must check');
        $this->assertNotSame('', trim($helper->field($cb)));

        $range = new DateRange('when', array('label' => 'When'));
        $range->setForm($form);
        $range->setValue(array('from' => '2024-06-01', 'to' => '2024-06-30'));
        $range->addError('Bad range');
        $this->assertNotSame('', trim($helper->field($range)));

        $html = new Html('blurb', array('value' => '<em>Hi</em>', 'label' => 'Blurb'));
        $html->setForm($form);
        $html->addError('Invalid');
        $this->assertNotSame('', trim($helper->field($html)));

        $cap = new ReCaptcha('cap', array('secret' => 's', 'site_key' => 'k'));
        $cap->setForm($form);
        $cap->addError('Captcha failed');
        $this->assertNotSame('', trim($helper->field($cap)));

        $file = new File('up', array('label' => 'Up'));
        $file->setForm($form);
        $this->assertNotSame('', trim($helper->field($file)));

        $btn = new Button('go', array('label' => 'Go'));
        $btn->setForm($form);
        $this->assertNotSame('', trim($helper->field($btn)));

        $email = new Email('mail', array('label' => 'Mail'));
        $email->setForm($form);
        $email->addError('Invalid email');
        $this->assertNotSame('', trim($helper->field($email)));

        $num = new Number('n', array('label' => 'N'));
        $num->setForm($form);
        $num->setValue('3');
        $this->assertNotSame('', trim($helper->field($num)));

        $hid = new Hidden('hid');
        $hid->setForm($form);
        $this->assertNotSame('', trim($helper->field($hid)));

        $csrf = new Csrf('tok');
        $csrf->setForm($form);
        $this->assertNotSame('', trim($helper->field($csrf)));
    }

    public function testMenuBreadcrumbsFlashAndPaginatorBranches(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $controller = new PaginatorControllerStub($app);
        $app->setCurrentController($controller);
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $router = new Router($app, $app->getRequest(), array(
            'default' => array(
                'type' => Literal::class,
                'path' => '/list',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $router->setCurrentRouteName('default');
        $app->setRouter($router);

        $partial = new Partial();
        $partial->setController($controller);

        $captionObj = new class() {
            public function __toString()
            {
                return 'ObjCaption';
            }
        };
        $crumbItems = array(
            array('caption' => function () {
                return 42;
            }, 'content' => array('href' => '/a')),
            array('caption' => $captionObj, 'content' => array('href' => '/b')),
            array('caption' => 'Last', 'content' => array('href' => '/c')),
        );
        $this->assertStringContainsString('42', $partial->partial('breadcrumbs.php', array('items' => $crumbItems)));
        $this->assertStringContainsString('ObjCaption', $partial->partial('bootstrap4/breadcrumbs.php', array('items' => $crumbItems)));
        $this->assertStringContainsString('Last', $partial->partial('bootstrap5/breadcrumbs.php', array('items' => $crumbItems)));

        $menuHtml = $partial->partial('menu-generic.php', array(
            'currentUrl' => '/here',
            'items' => array(
                'wrapper' => array(array('tag' => 'ul', 'attribs' => array('class' => 'nav'))),
                0 => array(
                    'caption' => function () {
                        return 'Callable';
                    },
                    'content' => array('tag' => 'span', 'class' => 'item'),
                    'wrapper' => array(array('tag' => 'li')),
                    'items' => array(
                        0 => array('caption' => 'Nested', 'content' => array('href' => '/nested')),
                    ),
                ),
                1 => array('caption' => 'Plain'),
                'ignored' => array('caption' => 'Skip'),
                2 => array(
                    'caption' => function () {
                        return 42;
                    },
                    'content' => array('href' => '/num'),
                ),
            ),
        ));
        $this->assertStringContainsString('Callable', $menuHtml);
        $this->assertStringContainsString('Nested', $menuHtml);

        $this->assertSame('', trim($partial->partial('paginator.php', array('count' => 'nope'))));
        $this->assertSame('', trim($partial->partial('paginator.php', array('count' => 5, 'page' => 1, 'perPage' => 10))));

        $firstPage = $partial->partial('paginator.php', array('count' => 100, 'page' => 1, 'perPage' => 10));
        $this->assertStringContainsString('disabled', $firstPage);
        $lastPage = $partial->partial('bootstrap5/paginator.php', array('count' => 100, 'page' => 10, 'perPage' => 10));
        $this->assertStringContainsString('pagination', $lastPage);
        $customPer = $partial->partial('bootstrap5/paginator.php', array('count' => 100, 'page' => 5, 'perPage' => 25));
        $this->assertStringContainsString('pagination', $customPer);
        $invalidCount = $partial->partial('bootstrap5/paginator.php', array('count' => 'bad'));
        $this->assertSame('', trim($invalidCount));
        $singlePage = $partial->partial('bootstrap5/paginator.php', array('count' => 5, 'page' => 1, 'perPage' => 10));
        $this->assertSame('', trim($singlePage));
        $lastDisabled = $partial->partial('bootstrap5/paginator.php', array('count' => 100, 'page' => 10, 'perPage' => 10));
        $this->assertStringContainsString('disabled', $lastDisabled);
        $firstDisabled = $partial->partial('bootstrap4/paginator.php', array('count' => 100, 'page' => 1, 'perPage' => 10));
        $this->assertStringContainsString('disabled', $firstDisabled);
        $pageDefault = $partial->partial('bootstrap4/paginator.php', array('count' => 120, 'page' => 0, 'perPage' => 0));
        $this->assertStringContainsString('pagination', $pageDefault);

        $metaField = new DateRange('meta', array('label' => 'Meta', 'metadata' => array('pick' => 'range')));
        $metaField->setForm(new class(array('action' => '/x')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        });
        $bs5 = new Bootstrap5();
        $bs5->setController($controller);
        $this->syncFormRenderHelperName('bootstrap5');
        $metaHtml = $bs5->field($metaField);
        $this->assertNotSame('', trim($metaHtml));

        $render = new Render();
        $render->setController($controller);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array(
            'ufw__fM' => array(
                'info' => array('Info msg'),
                'warning' => array('Warn msg'),
                'error' => array('Err msg'),
            ),
        );
        $fmRef = new \ReflectionClass(\Ufw\Helper\FlashMessenger::class);
        foreach (array('_session', '_messages') as $name) {
            $prop = $fmRef->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
        new \Ufw\Helper\FlashMessenger();
        $method = new \ReflectionMethod(\Ufw\Helper\ViewRendererBase::class, 'renderHtml');
        $method->setAccessible(true);
        $flash = $method->invoke($render, array(), 'partials/flashmessages.php', false);
        $this->assertStringContainsString('Info msg', $flash);
        $this->assertStringContainsString('Warn msg', $flash);
        $this->assertStringContainsString('Err msg', $flash);
    }
}
