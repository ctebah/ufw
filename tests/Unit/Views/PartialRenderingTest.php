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

class PartialRenderingTest extends TestCase
{
    use FormFixtureConfig;
    use FormRenderTestSupport;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        Paths::setApplicationPath(TestAppPaths::app());
        unset($GLOBALS['__ufw_form_check_icon_css']);
        $this->resetFormRenderHelperNames();
        parent::tearDown();
    }

    /**
     * @return list<array{0: string, 1: class-string<FormRenderHelperBase>}>
     */
    public static function formRenderHelpers(): array
    {
        return array(
            array('bootstrap3', Bootstrap3::class),
            array('bootstrap4', Bootstrap4::class),
            array('bootstrap4plain', Bootstrap4plain::class),
            array('bootstrap5', Bootstrap5::class),
            array('bootstrap3plain', Bootstrap3plain::class),
        );
    }

    #[DataProvider('formRenderHelpers')]
    public function testFormRenderHelperExercisesBundledPartials($label, $helperClass): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $this->syncFormRenderHelperName($label);

        /** @var FormRenderHelperBase $helper */
        $helper = new $helperClass();
        $helper->setController($controller);

        $form = new ViewsCoverageForm(array('action' => '/post'));
        $form->setValidated(true);
        $title = $form->getElement('title');
        $title->addError('Required');
        $title->setLabelPlacement('below');
        $title->setDescription('Help text');

        $html = trim($helper->form($form));
        $this->assertNotSame('', $html, $label . ' form shell');

        foreach ($form->getElements() as $element) {
            $field = trim($helper->field($element));
            $this->assertNotSame('', $field, $label . ' field ' . $element->getName());
        }
    }

    public function testSharedPartialsPaginatorBreadcrumbsMenuAndFlash(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $controller = new PaginatorControllerStub($app);
        $app->setCurrentController($controller);
        $request = TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index'));
        $app->setRequest($request);
        $router = new Router($app, $request, array(
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

        $items = array(
            array('caption' => 'Home', 'content' => array('href' => '/home')),
            array('caption' => 'Here', 'content' => array('href' => '/here')),
        );
        $this->assertStringContainsString('Home', $partial->partial('breadcrumbs.php', array('items' => $items)));
        $this->assertStringContainsString('breadcrumb', $partial->partial('bootstrap4/breadcrumbs.php', array('items' => $items)));
        $this->assertStringContainsString('breadcrumb', $partial->partial('bootstrap5/breadcrumbs.php', array('items' => $items)));

        $paginatorVars = array('count' => 50, 'page' => 2, 'perPage' => 10);
        $this->assertStringContainsString('pagination', $partial->partial('paginator.php', $paginatorVars));
        $this->assertStringContainsString('pagination', $partial->partial('bootstrap4/paginator.php', $paginatorVars));
        $this->assertStringContainsString('pagination', $partial->partial('bootstrap5/paginator.php', $paginatorVars));
        $lastPage = array('count' => 50, 'page' => 5, 'perPage' => 10);
        $this->assertStringContainsString('pagination', $partial->partial('bootstrap5/paginator.php', $lastPage));
        $customPerPage = array('count' => 100, 'page' => 3, 'perPage' => 25);
        $this->assertStringContainsString('pagination', $partial->partial('bootstrap5/paginator.php', $customPerPage));

        $menu = $partial->partial('menu-generic.php', array(
            'items' => array(array('caption' => 'Nav', 'content' => array('href' => '/nav'))),
            'currentUrl' => '/nav',
        ));
        $this->assertStringContainsString('Nav', $menu);

        $render = new Render();
        $render->setController($controller);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array(
            'ufw__fM' => array(
                'info' => array('Saved'),
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
        $this->assertStringContainsString('Saved', $flash);

        $styles = $partial->partial('ufw-form-check-icon-styles-once.php', array());
        $this->assertStringContainsString('ufw-form-check-icon-css', $styles);
        $again = $partial->partial('ufw-form-check-icon-styles-once.php', array());
        $this->assertSame('', trim($again));
    }

    public function testAjaxLayoutPartialRendersContent(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $render = new Render();
        $render->setController($controller);
        $method = new \ReflectionMethod(\Ufw\Helper\ViewRendererBase::class, 'renderHtml');
        $method->setAccessible(true);
        $html = $method->invoke($render, array('content' => 'ajax-body'), 'layout/_bare.ajax.php', false);
        $this->assertStringContainsString('ajax-body', $html);
    }
}

class ViewsCoverageForm extends \Ufw\Form\Base
{
    public function init()
    {
        $this->addElement(new Text('title', array('label' => 'Title', 'required' => true)));
        $secret = new Password('secret', array('label' => 'Secret'));
        $secret->setLabelPlacement('below');
        $this->addElement($secret);
        $this->addElement(new Textarea('notes', array('label' => 'Notes')));
        $this->addElement(new Checkbox('agree', array('label' => 'Agree')));
        $this->addElement(new Select('role', array('label' => 'Role', 'multiOptions' => array('a' => 'A', 'b' => 'B'))));
        $this->addElement(new Radio('pick', array('label' => 'Pick', 'multiOptions' => array('x' => 'X'))));
        $this->addElement(new CheckboxSet('tags', array('label' => 'Tags', 'multiOptions' => array('t1' => 'T1'))));
        $range = new DateRange('period', array('label' => 'Period'));
        $range->setValue(array('from' => '2024-01-01', 'to' => '2024-01-31'));
        $this->addElement($range);
        $this->addElement(new Html('info', array('value' => '<p>Info</p>')));
        $this->addElement(new Button('submit', array('label' => 'Send')));
        $this->addElement(new Csrf('csrf'));
        $this->addElement(new Email('email', array('label' => 'Email')));
        $this->addElement(new Number('qty', array('label' => 'Qty')));
        $this->addElement(new Hidden('hid'));
        $this->addElement(new File('doc', array('label' => 'Doc')));
        $this->addElement(new ReCaptcha('captcha', array('site_key' => 'site-key-test')));
    }
}
