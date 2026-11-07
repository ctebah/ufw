<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\ServerRequest;

require_once dirname(__DIR__) . '/Fixture/route-controllers.php';

/**
 * Exercises {@see RegEx} public API only (PCOV method attribution on the concrete class).
 */
class RegExBuildUrlTest extends TestCase
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

    public function testBuildUrlSubstitutesPlaceholdersAndQueryString()
    {
        $route = new RegEx($this->router('/ping/7?q=1'), array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'urlBuilder' => array('/ping/:id'),
        ));
        $this->assertSame('/ping/7', $route->buildUrl(array('id' => 7)));
        $this->assertSame('/ping/7?q=1&extra=1', $route->buildUrl(array(
            'id' => '7',
            'extra' => '1',
        ), true, true));
    }

    public function testBuildUrlWithoutEncodeLeavesRawSegments()
    {
        $route = new RegEx($this->router('/'), array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'urlBuilder' => array('/tag/:label'),
        ));
        $this->assertSame('/tag/a b', $route->buildUrl(array(
            'label' => 'a b',
        ), true, false, false));
    }

    public function testBuildUrlPicksBestPatternWhenMultipleBuildersExist()
    {
        $route = new RegEx($this->router('/'), array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'urlBuilder' => array(
                '/items/:id',
                '/items/:id/:slug',
            ),
        ));
        $this->assertSame('/items/2/detail', $route->buildUrl(array(
            'id' => '2',
            'slug' => 'detail',
        )));
    }

    private function router($uri)
    {
        $server = new ServerRequest(array('REQUEST_URI' => $uri), array(), $uri, 'GET', 'php://input', array(), array(), array('q' => '1'));
        $request = new Request(array(), array(), array(), null, $server);
        return new Router($this->app, $request, array());
    }
}
