<?php
namespace Ufw\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Envs;
use Ufw\Helper\Menu;
use Ufw\Registry;
use Ufw\Router;
use Ufw\Route\Literal;
use Ufw\ServerRequest;
use Ufw\Request;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

class MenuTest extends TestCase
{
    protected function tearDown(): void
    {
        Envs::setRuntimeEnvLabel(null);
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testMenuBuildTreeAppliesRbacEnvCallableCaptionAndRendersGenericPartial()
    {
        Envs::setRuntimeEnvLabel('development');
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'env' => 'development',
            'dispatch' => array('controllers' => array('ping')),
        ));
        Registry::getInstance()->set('application', $app);
        $controller = new MenuRenderTestController($app);
        $app->setCurrentController($controller);
        $app->setRequest(TestHttpRequest::withAttributes(array(
            'controller' => 'ping',
            'action' => 'index',
        )));
        $router = new Router($app, $app->getRequest(), array(
            'home' => array(
                'type' => Literal::class,
                'path' => '/current',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $router->match();
        $app->setRouter($router);

        $menu = new Menu();
        $menu->setController($controller);
        $menu->setStrictMode(true);
        $menu(array(
            array(
                'caption' => function () {
                    return 'Dynamic';
                },
                'content' => array('href' => '/current'),
                'env' => array('development'),
                'rbac' => function () {
                    return true;
                },
                'wrap' => array('class' => 'extra'),
            ),
            array(
                'caption' => 'Hidden',
                'env' => array('production'),
                'content' => array('href' => '/nope'),
            ),
            array(
                'caption' => 'Child',
                'content' => array('href' => array('route' => 'home', 'action' => 'index')),
                'items' => array(
                    array(
                        'caption' => 'Nested',
                        'content' => array('href' => '/current/sub'),
                    ),
                ),
            ),
            'wrap' => array('tag' => 'ul', 'class' => 'root'),
        ));

        $html = $menu->render();
        $this->assertStringContainsString('menu-stub', $html);

        $crumbs = $menu->breadcrumbs(null, array(array('caption' => 'Root', 'content' => array('href' => '/'))), true);
        $this->assertStringContainsString('breadcrumb', $crumbs);
    }

    public function testMenuSkipsItemsWhenRbacOrEnvDoNotMatch()
    {
        Envs::setRuntimeEnvLabel('development');
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('env' => 'development', 'dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $controller = new MenuRenderTestController($app);
        $controller->user = new class {
            public function hasRole($role)
            {
                return $role === 'staff';
            }

            public function hasAnyOfRoles(array $roles)
            {
                return in_array('staff', $roles, true);
            }
        };
        $app->setCurrentController($controller);
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));

        $menu = new Menu();
        $menu->setController($controller);
        $definition = array(
            array('caption' => 'Allowed', 'rbac' => array('staff', 'admin'), 'content' => array('href' => '/ok')),
            array('caption' => 'Denied', 'rbac' => 'guest', 'content' => array('href' => '/no')),
            array('caption' => 'Prod only', 'env' => array('production'), 'content' => array('href' => '/prod')),
        );
        $build = new \ReflectionMethod(Menu::class, 'buildTree');
        $build->setAccessible(true);
        $tree = $build->invoke($menu, $definition, '/ok', false);
        $captions = array();
        foreach ($tree as $key => $item) {
            if (is_numeric($key) && is_array($item) && isset($item['caption'])) {
                $captions[] = $item['caption'];
            }
        }
        $this->assertContains('Allowed', $captions);
        $this->assertNotContains('Denied', $captions);
        $this->assertNotContains('Prod only', $captions);
    }

    public function testBuildTreeMarksActiveWithPrefixAndStrictExactMatch()
    {
        $menu = new Menu();
        $prefix = new \ReflectionMethod(Menu::class, 'isMenuItemActive');
        $prefix->setAccessible(true);
        $strict = new \ReflectionMethod(Menu::class, 'isMenuItemActiveStrict');
        $strict->setAccessible(true);
        $this->assertTrue($prefix->invoke($menu, '/admin', '/admin/users'));
        $this->assertFalse($strict->invoke($menu, '/admin', '/admin/users'));
        $this->assertTrue($strict->invoke($menu, '/admin/users', '/admin/users'));
    }

    public function testBuildTreeAppliesMultiWrapAndFiltersEmptyChildMenus()
    {
        Envs::setRuntimeEnvLabel('development');
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);
        $controller = new MenuRenderTestController($app);
        $menu = new Menu();
        $menu->setController($controller);
        $menu->setStrictMode(false);
        $build = new \ReflectionMethod(Menu::class, 'buildTree');
        $build->setAccessible(true);
        $tree = $build->invoke($menu, array(
            array(
                'caption' => 'Parent',
                'content' => array('href' => '/parent'),
                'multiwrap' => array(array('class' => 'multi')),
                'items' => array(
                    array(
                        'caption' => 'Hidden child',
                        'env' => array('production'),
                        'content' => array('href' => '/child'),
                    ),
                ),
            ),
            'wrap' => array('tag' => 'nav', 'class' => 'root-wrap'),
        ), '/parent', false);
        $this->assertTrue(!empty($tree['active']));
        $this->assertArrayHasKey('wrapper', $tree);
        $this->assertArrayHasKey(0, $tree);
        $this->assertArrayHasKey('wrapper', $tree[0]);
    }

    public function testFormatWrapAppendsActiveClassAndEmptyPrunesCaptionOnlyNodes()
    {
        $menu = new Menu();
        $format = new \ReflectionMethod(Menu::class, 'formatWrap');
        $format->setAccessible(true);
        $wrapped = $format->invoke($menu, array(array('class' => 'item')), 'li', 'nav-item', true);
        $this->assertStringContainsString('active', $wrapped[0]['attribs']['class']);

        $empty = new \ReflectionMethod(Menu::class, 'empty');
        $empty->setAccessible(true);
        $sub = array();
        $this->assertTrue($empty->invoke($menu, $sub));
    }

    public function testBuildTreeSkipsStringRbacWhenRoleMissing()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);
        $controller = new MenuRenderTestController($app);
        $controller->user = new class {
            public function hasRole($role)
            {
                return $role === 'staff';
            }

            public function hasAnyOfRoles(array $roles)
            {
                return false;
            }
        };
        $menu = new Menu();
        $menu->setController($controller);
        $build = new \ReflectionMethod(Menu::class, 'buildTree');
        $build->setAccessible(true);
        $tree = $build->invoke($menu, array(
            array('caption' => 'Guest only', 'rbac' => 'guest', 'content' => array('href' => '/g')),
            array('caption' => 'Staff', 'rbac' => 'staff', 'content' => array('href' => '/s')),
        ), '/s', false);
        $captions = array();
        foreach ($tree as $key => $item) {
            if (is_numeric($key) && is_array($item)) {
                $captions[] = $item['caption'];
            }
        }
        $this->assertSame(array('Staff'), $captions);
    }

    public function testNormalizeWrapListSkipsInvalidEntries()
    {
        $menu = new Menu();
        $method = new \ReflectionMethod(Menu::class, 'normalizeWrapList');
        $method->setAccessible(true);
        $out = $method->invoke($menu, array('bad', array('class' => 'ok', 0 => 'drop')));
        $this->assertCount(1, $out);
        $this->assertSame('ok', $out[0]['class']);
    }

    public function testBuildTreeSkipsNonArrayEntriesAndMissingContentArray()
    {
        $menu = new Menu();
        $build = new \ReflectionMethod(Menu::class, 'buildTree');
        $build->setAccessible(true);
        $tree = $build->invoke($menu, array(
            'skip-me',
            array('caption' => 'No href block'),
        ), '/', false);
        $this->assertArrayHasKey(1, $tree);
        $this->assertIsArray($tree[1]['content']);
    }

    public function testGetConfigUsesEmptyDefinitionWhenNotStringOrArray()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);
        $controller = new MenuRenderTestController($app);
        $menu = new Menu();
        $menu->setController($controller);
        $menu->setDefinition(123);
        $getConfig = new \ReflectionMethod(Menu::class, 'getConfig');
        $getConfig->setAccessible(true);
        $config = $getConfig->invoke($menu);
        $this->assertIsArray($config);
    }

    public function testGetRenderHelperNameReadsFormHelperFromApplicationConfig()
    {
        Menu::setRenderHelperName(null);
        ApplicationFixtures::bareWithController(array(
            'form' => array('helper' => 'bootstrap5'),
        ));
        $menu = new Menu();
        $this->assertSame('bootstrap5', $menu->getRenderHelperName());
        Menu::setRenderHelperName(null);
    }

    public function testNormalizeWrapListReturnsDefaultWhenAllEntriesInvalid()
    {
        $menu = new Menu();
        $method = new \ReflectionMethod(Menu::class, 'normalizeWrapList');
        $method->setAccessible(true);
        $out = $method->invoke($menu, array('x', 1));
        $this->assertSame(array(array()), $out);
    }

    public function testBuildTreeRootWrapperUsesDefaultWhenWrapNormalizesEmpty()
    {
        $menu = new Menu();
        $build = new \ReflectionMethod(Menu::class, 'buildTree');
        $build->setAccessible(true);
        $tree = $build->invoke($menu, array(
            array('caption' => 'One', 'content' => array('href' => '/one')),
            'wrap' => 'not-an-array',
        ), '/one', false);
        $this->assertArrayHasKey('wrapper', $tree);
    }
}

class MenuRenderTestController extends ControllerBase
{
    /** @var object|null */
    public $user;

    public function partial($script, $vars = array())
    {
        if (strpos($script, 'breadcrumbs') !== false) {
            return '<nav class="breadcrumb">stub</nav>';
        }

        return '<ul class="menu-stub">' . (isset($vars['items']) && is_array($vars['items']) ? count($vars['items']) : 0) . '</ul>';
    }

    public function url($params = array(), $reset = true, $withQS = false, $routeName = 'default', $encode = true)
    {
        if ($routeName === 'home') {
            return '/current';
        }

        return '/current';
    }
}
