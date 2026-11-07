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
use Ufw\Tests\Support\RuntimeProbe;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class BaseBehaviourTest extends TestCase
{
    /**
     * @var Application
     */
    private $app;

    protected function setUp(): void
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controller_aliases' => array(),
                'controllers' => array('ping'),
                'module' => array(),
            ),
        ));
        $app->setDispatchControllers(array('ping'));
        Registry::getInstance()->set('application', $app);
        $this->app = $app;
    }

    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testMatchRequestPathRunsPatternsOnRegExRoute()
    {
        $route = new RegEx($this->router('/ping/9'), array(
            'patterns' => array('#^/ping/(?P<id>\d+)$#'),
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
        ));
        $match = $route->matchRequestPath('/ping/9');
        $this->assertIsArray($match);
        $this->assertSame('9', $match['id']);
    }

    public function testInvalidRouteDefArraysAreIgnoredSafely()
    {
        if (!RuntimeProbe::routeLiteralNormalizesInvalidDefinitionArrays()) {
            $this->markTestSkipped('Literal route definition normalization is a 1.1 guard.');
        }
        $route = new Literal($this->router('/x'), array(
            'path' => '/x',
            'defaults' => 'not-an-array',
            'constraints' => 0,
            'options' => null,
        ));
        $defaults = new \ReflectionMethod(Literal::class, 'getDefaults');
        $defaults->setAccessible(true);
        $this->assertSame(array(), $defaults->invoke($route));
        $this->assertTrue($route->check(array()));
    }

    public function testRouterGetRoutesListsRegisteredDefinitions()
    {
        $routes = array(
            'home' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        );
        $router = new Router($this->app, $this->request('/'), $routes);
        $this->assertArrayHasKey('home', $router->getRoutes());
    }

    public function testRegExBuildUrlCompilesMultiplePatterns()
    {
        $route = new RegEx($this->router('/'), array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'urlBuilder' => array(
                '/only/:id',
                '/only/:id/:slug',
            ),
        ));
        $this->assertSame('/only/3/slug', $route->buildUrl(array(
            'id' => '3',
            'slug' => 'slug',
        )));
    }

    public function testCheckRejectsNonStringRouteConstraintDefinition()
    {
        $route = new RegEx($this->router('/ping/1'), array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'constraints' => array(
                'checkIfControllerIsRegistered' => false,
                'checkAction' => false,
                'id' => 123,
            ),
        ));
        $this->assertFalse($route->check(array('id' => '1')));
    }

    /**
     * @param array<string, array<string, mixed>> $routes
     */
    private function router($uri, array $routes = array())
    {
        return new Router($this->app, $this->request($uri), $routes);
    }

    private function request($uri)
    {
        $server = new ServerRequest(array('REQUEST_URI' => $uri), array(), $uri, 'GET', 'php://input');
        return new Request(array(), array(), array(), null, $server);
    }
}
