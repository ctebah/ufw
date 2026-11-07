<?php
namespace Ufw\Tests\Unit\Route;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Route\Literal;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\ServerRequest;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class RouteMatchTest extends TestCase
{
    /**
     * @var Application
     */
    private $app;

    protected function setUp(): void
    {
        $this->app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $this->app->setConfig(array(
            'dispatch' => array(
                'controller_aliases' => array(),
                'controllers' => array('ping'),
            ),
        ));
        Registry::getInstance()->set('application', $this->app);
    }

    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testRegexBuildUrlAllowUriParamsWithArrayValuesAndConstraintsNormalization()
    {
        $request = $this->request('/ping/7?existing=1', array('existing' => '1'));
        $router = new Router($this->app, $request, array(
            'ping' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/ping(?:/(?P<id>\d+))?$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'constraints' => array('id' => '#^\d+$#', 0 => 'ignored'),
                'options' => array('allowUriParams' => true, 0 => 'bad'),
                'urlBuilder' => array('/ping', '/ping/:id'),
            ),
        ));
        $router->match();
        $route = $router->getCurrentRoute();
        $this->assertInstanceOf(RegEx::class, $route);

        $built = $route->buildUrl(array(
            'id' => '7',
            'tags' => array('a', 'b'),
            'skip' => null,
        ), true, false, true);
        $this->assertStringContainsString('/ping/7/tags/a', $built);
        $this->assertStringContainsString('tags/b', $built);
    }

    public function testLiteralRouteNormalizesDefaultsAndOptions()
    {
        $router = $this->router(array(
            'health' => array(
                'type' => Literal::class,
                'path' => '/health/',
                'defaults' => array('controller' => 'ping', 'action' => 'index', 0 => 'x'),
                'options' => array('allowUriParams' => false, 0 => 'y'),
            ),
        ), '/health');
        $route = $router->match();
        $this->assertInstanceOf(Literal::class, $route);
        $this->assertSame('/health', $route->buildUrl());
    }

    public function testPrepareBuildUrlKeepsControllerWhenUriTailExpected()
    {
        $router = $this->router(array(
            'catalog' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'catalog', 'action' => 'index'),
                'options' => array('allowUriParams' => true),
            ),
        ), '/');
        $route = $router->match();
        $this->assertInstanceOf(Literal::class, $route);

        $built = $route->buildUrl(array('page' => '2'), true, false);
        $this->assertStringContainsString('page/2', $built);
    }

    public function testBuildUrlMergesQueryStringWhenWithQsEnabled()
    {
        $router = $this->routerWithQuery(array(
            'item' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/items/(?P<id>\d+)$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'urlBuilder' => array('/items/:id'),
            ),
        ), '/items/7', array('sort' => 'name'));
        $route = $router->match();
        $built = $route->buildUrl(array('id' => '9'), false, true);
        $this->assertStringContainsString('/items/9', $built);
        $this->assertStringContainsString('sort=name', $built);
    }

    /**
     * @param array<string, mixed> $routes
     * @param string $uri
     * @return Router
     */
    private function router(array $routes, $uri)
    {
        return new Router($this->app, $this->request($uri), $routes);
    }

    /**
     * @param string $uri
     * @param array<string, string> $query
     * @return Router
     */
    private function routerWithQuery(array $routes, $uri, array $query)
    {
        return new Router($this->app, $this->request($uri, $query), $routes);
    }

    /**
     * @param string $uri
     * @param array<string, string> $query
     * @return Request
     */
    private function request($uri, array $query = array())
    {
        $server = new ServerRequest(array('REQUEST_URI' => $uri), array(), $uri, 'GET', 'php://input', array(), array(), $query);

        return new Request(array(), array(), array(), null, $server);
    }
}
