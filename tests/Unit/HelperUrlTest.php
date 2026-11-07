<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Helper\Url;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Route\Literal;
use Ufw\Router;
use Ufw\ServerRequest;

require_once dirname(__DIR__) . '/Fixture/route-controllers.php';

class HelperUrlTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private $savedServer = array();

    /**
     * @var Application
     */
    private $app;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
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
        $_SERVER = $this->savedServer;
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testUrlBuildsFromNamedRouteAndInvokeAlias()
    {
        $routes = array(
            'health' => array(
                'type' => Literal::class,
                'path' => '/health',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        );
        $router = new Router($this->app, $this->request('/health'), $routes);
        $this->app->setRouter($router);
        $router->match();

        $controller = new class($this->app) extends ControllerBase {
        };
        $this->app->setCurrentController($controller);

        $url = new Url();
        $url->setController($controller);

        $this->assertSame('/health', $url->url(array(), true, false, 'health'));
        $this->assertSame('/health', $url(array(), true, false, 'health'));
    }

    public function testUrlUsesCurrentRouteWhenRouteNameIsNull()
    {
        $routes = array(
            'health' => array(
                'type' => Literal::class,
                'path' => '/health',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        );
        $router = new Router($this->app, $this->request('/health'), $routes);
        $this->app->setRouter($router);
        $router->match();

        $controller = new class($this->app) extends ControllerBase {
        };
        $this->app->setCurrentController($controller);
        $url = new Url();
        $url->setController($controller);

        $this->assertSame('/health', $url->url(array(), true, false, null));
    }

    public function testFullAndDomainHonorHttpHostOrServerName()
    {
        $routes = array(
            'default' => array(
                'type' => Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        );
        $router = new Router($this->app, $this->request('/'), $routes);
        $this->app->setRouter($router);

        $controller = new class($this->app) extends ControllerBase {
        };
        $this->app->setCurrentController($controller);
        $url = new Url();
        $url->setController($controller);

        $_SERVER = array(
            'HTTPS' => 'on',
            'HTTP_HOST' => 'app.example',
        );
        $this->assertSame('https://app.example', $url->domain());
        $this->assertSame('https://app.example/', $url->full());

        unset($_SERVER['HTTP_HOST']);
        $_SERVER['SERVER_NAME'] = 'fallback.test';
        $_SERVER['SERVER_PORT'] = 8080;
        $_SERVER['HTTPS'] = 'off';
        $this->assertSame('http://fallback.test:8080', $url->domain());
    }

    /**
     * @param array<string, string> $query
     */
    private function request($uri, array $query = array())
    {
        $server = new ServerRequest(array('REQUEST_URI' => $uri), array(), $uri, 'GET', 'php://input', array(), array(), $query);
        return new Request(array(), array(), array(), null, $server);
    }
}
