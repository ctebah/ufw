<?php
namespace Ufw\Tests\Unit\Helper;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Helper\Assets;
use Ufw\Helper\FlashMessenger;
use Ufw\Helper\FormErrorsToFlashMessenger;
use Ufw\Helper\JsTranslate;
use Ufw\Helper\Menu;
use Ufw\Helper\User;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Form\Element\Text;

class HelperTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        $_SERVER = array();
        $_COOKIE = array();
        parent::tearDown();
    }

    public function testAssetsHelperLoadsBundleConfigAndRendersCssAndJs()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(array(
            'assets' => array('serve-minified' => false),
            'dispatch' => array('routes' => array()),
        ));
        $app->setConfig(array_merge($app->getConfig(), array(
            'assets' => array('serve-minified' => false),
        )));

        $assets = new Assets();
        $assets->setController($controller);
        $assets('site');
        $this->assertSame('site', $assets->getName());
        $cssHtml = (string) $assets->css();
        $this->assertStringContainsString('gate.css', $cssHtml);
        $jsHtml = (string) $assets->js();
        $this->assertStringContainsString('gate.js', $jsHtml);
    }

    public function testMenuBuildPathBreadcrumbsAndWrapNormalization()
    {
        list($app) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $controller = new MenuTestController($app);
        $app->setRequest(TestHttpRequest::withAttributes(array(
            'controller' => 'ping',
            'action' => 'index',
        )));
        $menu = new Menu();
        $menu->setController($controller);
        $menu(array(
            array(
                'caption' => 'Home',
                'active' => true,
                'content' => array('href' => '/home'),
            ),
        ));

        $normalize = new \ReflectionMethod(Menu::class, 'normalizeWrapList');
        $normalize->setAccessible(true);
        $this->assertIsArray($normalize->invoke($menu, 'div'));

        $this->assertStringContainsString('partial', $menu->breadcrumbs(null, array(), true));
        $this->assertIsString($menu->getRenderHelperName());
    }

    public function testUserFlashFormErrorsAndJsTranslate()
    {
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $_SERVER['PHP_AUTH_USER'] = 'admin';
        $_SERVER['PHP_AUTH_PW'] = 'x';
        $_COOKIE['logged'] = '1';

        $user = new User();
        $user->setController($controller);
        $this->assertTrue($user->isLoggedOn());
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->hasRole('editor'));
        $this->assertFalse($user->hasAnyOfRoles(array('editor', 'admin')));

        Registry::getInstance()->set('translator', function ($s) {
            return 'T:' . $s;
        });
        $jsTranslate = new JsTranslate();
        $jsTranslate->setController($controller);
        $this->assertSame('"T:save"', $jsTranslate->jsTranslate('save'));

        $helperBase = new \ReflectionMethod(\Ufw\Helper\Base::class, 'getRequest');
        $helperBase->setAccessible(true);
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $this->assertSame('ping', $helperBase->invoke($user)->getController());

        $normalize = new \ReflectionMethod(FlashMessenger::class, 'normalizeMessageList');
        $normalize->setAccessible(true);
        $this->assertSame(array('a'), $normalize->invoke(null, array('a', 7)));

        $flash = new FlashMessenger();
        $flash->setController($controller);
        $flash->addMessage('err', FlashMessenger::NAMESPACE_ERROR, false);
        $this->assertContains('err', $flash->getCurrentMessages(FlashMessenger::NAMESPACE_ERROR));

        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $form->getElement('title')->addError('Required');
        $bridge = new FormErrorsToFlashMessenger();
        $bridge->setController($controller);
        $bridge($form);
    }
}

class MenuTestController extends ControllerBase
{
    public function partial($script, $vars = array())
    {
        return '<partial>' . $script . '</partial>';
    }

    public function url($params = array(), $reset = true, $withQS = false, $routeName = 'default', $encode = true)
    {
        return '/home';
    }
}
