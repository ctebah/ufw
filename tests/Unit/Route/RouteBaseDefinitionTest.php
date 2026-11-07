<?php
namespace Ufw\Tests\Unit\Route;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Request;
use Ufw\Route\Literal;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\ServerRequest;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class RouteBaseDefinitionTest extends TestCase
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
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        \Ufw\Registry::getInstance()->set('application', $this->app);
        $this->app->setDispatchControllers(array('ping'));
    }

    protected function tearDown(): void
    {
        \Ufw\Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testMatchRequestPathSkipsInvalidPatternEntries()
    {
        $router = $this->router(array(
            'x' => array(
                'type' => RegEx::class,
                'patterns' => array(0, '#^/valid$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ), '/valid');
        $router->match();
        $route = $router->getCurrentRoute();
        $this->assertNotFalse($route->matchRequestPath('/valid'));
        $this->assertFalse($route->matchRequestPath('/nope'));
    }

    public function testCheckAppliesHttpMethodAndCustomConstraint()
    {
        $routeDef = array(
            'type' => RegEx::class,
            'patterns' => array('#^/items/(?P<id>\d+)$#'),
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'constraints' => array(
                'httpMethod' => array('POST'),
                'id' => '#^\d+$#',
            ),
        );

        $postRouter = $this->routerWithMethod(array('item' => $routeDef), '/items/5', 'POST');
        $postRoute = new RegEx($postRouter, $routeDef);
        $postMatch = $postRoute->matchRequestPath('/items/5');
        $this->assertTrue($postRoute->check($postMatch));

        $getRouter = $this->routerWithMethod(array('item' => $routeDef), '/items/5', 'GET');
        $getRoute = new RegEx($getRouter, $routeDef);
        $getMatch = $getRoute->matchRequestPath('/items/5');
        $this->assertFalse($getRoute->check($getMatch));
    }

    public function testProvideAttributesMergesUriTailWhenAllowed()
    {
        $router = $this->router(array(
            'cat' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/shop#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'options' => array('allowUriParams' => true),
            ),
        ), '/shop/page/2');
        $route = $router->match();
        $match = $route->matchRequestPath('/shop/page/2');
        $request = new Request(array(), array(), array(), null, new ServerRequest(
            array('REQUEST_URI' => '/shop/page/2'),
            array(),
            '/shop/page/2',
            'GET',
            'php://input'
        ));
        $route->check($match);
        $route->provideAttributes($request, '/shop/page/2', $match);
        $this->assertSame('2', $request->getAttribute('page'));
    }

    public function testGetControllerClassResolvesClassicPingController()
    {
        $router = $this->router(array(
            'p' => array(
                'type' => Literal::class,
                'path' => '/ping',
                'defaults' => array('controller' => 'ping', 'action' => 'other'),
            ),
        ), '/ping');
        $route = $router->match();
        $route->setProvisionalControllerName('ping')->setProvisionalActionName('other');
        $this->assertSame('\\Controller\\Ping', $route->getControllerClass());
        $this->assertSame('otherAction', $route->getActionMethod());
    }

    public function testCheckRejectsUnregisteredControllerWhenConstraintEnabled()
    {
        $routeDef = array(
            'type' => Literal::class,
            'path' => '/secret',
            'defaults' => array('controller' => 'missing', 'action' => 'index'),
            'constraints' => array(
                'checkIfControllerIsRegistered' => true,
                'controller' => '~^missing$~',
            ),
        );
        $router = $this->router(array('x' => $routeDef), '/secret');
        $route = new Literal($router, $routeDef);
        $match = $route->matchRequestPath('/secret');
        $this->assertFalse($route->check($match));
    }

    public function testCheckFailsWhenCustomConstraintIsNotAString()
    {
        $routeDef = array(
            'type' => Literal::class,
            'path' => '/bad',
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'constraints' => array('controller' => array('not-a-regex')),
        );
        $router = $this->router(array('x' => $routeDef), '/bad');
        $route = new Literal($router, $routeDef);
        $match = $route->matchRequestPath('/bad');
        $this->assertFalse($route->check($match));
    }

    public function testMatchRequestPathReturnsFalseWhenPatternsIsNotArray()
    {
        $routeDef = array(
            'type' => RegEx::class,
            'patterns' => 'not-array',
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
        );
        $router = $this->router(array('bad' => $routeDef), '/x');
        $route = new RegEx($router, $routeDef);
        $this->assertFalse($route->matchRequestPath('/x'));
    }

    public function testBuildUrlWithoutEncodingLeavesRawSegments()
    {
        $router = $this->router(array(
            'slug' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/p/(?P<slug>[\w-]+)$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'urlBuilder' => array('/p/:slug'),
            ),
        ), '/p/hello-world');
        $route = $router->match();
        $built = $route->buildUrl(array('slug' => 'a/b'), true, false, false);
        $this->assertStringContainsString('a/b', $built);
    }

    public function testBuildUrlWithEncodingAndArrayUriParams()
    {
        $router = $this->router(array(
            'shop' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/shop#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'options' => array('allowUriParams' => true),
                'urlBuilder' => array('/shop'),
            ),
        ), '/shop');
        $route = $router->match();
        $built = $route->buildUrl(array('tag' => array('a', 'b'), 'skip' => null), true, false, true);
        $this->assertStringContainsString('tag', $built);
        $this->assertStringContainsString('a', $built);
    }

    public function testCheckActionConstraintRejectsMissingActionMethod()
    {
        $routeDef = array(
            'type' => Literal::class,
            'path' => '/bad-action',
            'defaults' => array('controller' => 'ping', 'action' => 'secret'),
            'constraints' => array('checkAction' => true),
        );
        $router = $this->router(array('x' => $routeDef), '/bad-action');
        $route = new Literal($router, $routeDef);
        $match = $route->matchRequestPath('/bad-action');
        $this->assertFalse($route->check($match));
    }

    public function testGetControllerClassAppliesModuleAliases()
    {
        $this->app->setConfig(array(
            'dispatch' => array(
                'controller_aliases' => array(),
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'module' => array(
                    'api' => array(
                        'controller_aliases' => array('p' => 'ping'),
                    ),
                ),
            ),
        ));
        $router = $this->router(array(
            'm' => array(
                'type' => Literal::class,
                'path' => '/api/ping',
                'defaults' => array('module' => 'api', 'controller' => 'p', 'action' => 'index'),
            ),
        ), '/api/ping');
        $route = $router->match();
        $route->setProvisionalModuleName('api')->setProvisionalControllerName('p')->setProvisionalActionName('index');
        $this->assertSame('\\Controller\\Api\\Ping', $route->getControllerClass());
    }

    public function testRouteDefNormalizersDropNonStringKeys()
    {
        $routeDef = array(
            'type' => Literal::class,
            'path' => '/norm',
            'defaults' => array('controller' => 'ping', 'action' => 'index', 0 => 'x'),
            'constraints' => array('controller' => '~^ping$~', 1 => 'y'),
            'options' => array('allowUriParams' => true, 2 => 'z'),
        );
        $router = $this->router(array('n' => $routeDef), '/norm');
        $route = new Literal($router, $routeDef);
        $norm = new \ReflectionMethod($route, 'normalizeStringKeyedArray');
        $norm->setAccessible(true);
        $this->assertArrayHasKey('controller', $norm->invoke($route, $routeDef['defaults']));
        $this->assertArrayNotHasKey(0, $norm->invoke($route, $routeDef['defaults']));
    }

    public function testOptionalRouteStringCoercesNumericValues()
    {
        $routeDef = array(
            'type' => Literal::class,
            'path' => '/num',
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
        );
        $router = $this->router(array('n' => $routeDef), '/num');
        $route = new Literal($router, $routeDef);
        $opt = new \ReflectionMethod(\Ufw\Route\Base::class, 'optionalRouteString');
        $opt->setAccessible(true);
        $this->assertSame('42', $opt->invoke($route, 42));
        $this->assertNull($opt->invoke($route, array()));
    }

    public function testCheckUsesEmptyStringWhenConstraintValueMissing()
    {
        $routeDef = array(
            'type' => Literal::class,
            'path' => '/need-id',
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'constraints' => array('id' => '#^\d+$#'),
        );
        $router = $this->router(array('x' => $routeDef), '/need-id');
        $route = new Literal($router, $routeDef);
        $match = $route->matchRequestPath('/need-id');
        $this->assertFalse($route->check($match));
    }

    public function testBuildUrlSkipsNullUriParamValues()
    {
        $router = $this->router(array(
            'shop' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/shop#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'options' => array('allowUriParams' => true),
                'urlBuilder' => array('/shop'),
            ),
        ), '/shop');
        $route = $router->match();
        $built = $route->buildUrl(array('only' => null, 'page' => 2), true, false, true);
        $this->assertStringContainsString('page', $built);
        $this->assertStringNotContainsString('only', $built);
    }

    public function testBuildUrlSkipsUriParamValuesThatAreNotScalars()
    {
        $router = $this->router(array(
            'shop' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/shop#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'options' => array('allowUriParams' => true),
                'urlBuilder' => array('/shop'),
            ),
        ), '/shop');
        $route = $router->match();
        $built = $route->buildUrl(array('tag' => array(new \stdClass(), 'ok')), true, false, true);
        $this->assertStringContainsString('ok', $built);
        $this->assertStringNotContainsString('Object', $built);
    }

    public function testNormalizeStringKeyedArrayReturnsEmptyForScalar()
    {
        $routeDef = array(
            'type' => Literal::class,
            'path' => '/scalar-norm',
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
        );
        $router = $this->router(array('n' => $routeDef), '/scalar-norm');
        $route = new Literal($router, $routeDef);
        $norm = new \ReflectionMethod($route, 'normalizeStringKeyedArray');
        $norm->setAccessible(true);
        $this->assertSame(array(), $norm->invoke($route, null));
    }

    public function testGetOptionsReturnsEmptyWhenNotArray()
    {
        $routeDef = array(
            'type' => Literal::class,
            'path' => '/o',
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'options' => 'bad',
            'constraints' => 'bad',
        );
        $router = $this->router(array('o' => $routeDef), '/o');
        $route = new Literal($router, $routeDef);
        $opts = new \ReflectionMethod($route, 'getOptions');
        $opts->setAccessible(true);
        $this->assertSame(array(), $opts->invoke($route));
        $cons = new \ReflectionMethod($route, 'getConstraints');
        $cons->setAccessible(true);
        $this->assertSame(array(), $cons->invoke($route));
    }

    public function testProvideAttributesMergesDuplicateUriParamKeys()
    {
        $router = $this->router(array(
            'cat' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/shop#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'options' => array('allowUriParams' => true),
            ),
        ), '/shop/tag/a/tag/b');
        $route = $router->match();
        $match = $route->matchRequestPath('/shop/tag/a/tag/b');
        $request = new Request(array(), array(), array(), null, new ServerRequest(
            array('REQUEST_URI' => '/shop/tag/a/tag/b'),
            array(),
            '/shop/tag/a/tag/b',
            'GET',
            'php://input'
        ));
        $route->check($match);
        $route->provideAttributes($request, '/shop/tag/a/tag/b', $match);
        $tag = $request->getAttribute('tag');
        $this->assertIsArray($tag);
    }

    /**
     * @param array<string, mixed> $routes
     * @param string $uri
     * @return Router
     */
    private function router(array $routes, $uri)
    {
        return new Router($this->app, $this->request($uri, 'GET'), $routes);
    }

    /**
     * @param array<string, mixed> $routes
     * @param string $uri
     * @param string $method
     * @return Router
     */
    private function routerWithMethod(array $routes, $uri, $method)
    {
        return new Router($this->app, $this->request($uri, $method), $routes);
    }

    /**
     * @param string $uri
     * @param string $method
     * @return Request
     */
    private function request($uri, $method = 'GET')
    {
        return new Request(array(), array(), array(), null, new ServerRequest(
            array('REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri),
            array(),
            $uri,
            $method,
            'php://input'
        ));
    }
}
