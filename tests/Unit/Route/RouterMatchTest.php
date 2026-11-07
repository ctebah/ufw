<?php
namespace Ufw\Tests\Unit\Route;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Route\Literal;
use Ufw\Router;
use Ufw\ServerRequest;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class RouterMatchTest extends TestCase
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
                'controllers' => array('ping'),
                'controller_aliases' => array(),
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

    public function testMatchNormalizesEmptyRequestUriToRoot()
    {
        $routes = array(
            'home' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        );
        $server = new ServerRequest(array('REQUEST_URI' => ''), array(), '', 'GET', 'php://input');
        $request = new Request(array(), array(), array(), null, $server);
        $router = new Router($this->app, $request, $routes);

        $this->assertNotNull($router->match());
        $this->assertSame('home', $router->getCurrentRouteName());
    }

    public function testMatchReturnsNullWhenRoutesBagIsEmpty()
    {
        $server = new ServerRequest(array('REQUEST_URI' => '/nope'), array(), '/nope', 'GET', 'php://input');
        $request = new Request(array(), array(), array(), null, $server);
        $router = new Router($this->app, $request, array());
        $router->setRoutes(null);

        $this->assertNull($router->match());
    }
}
