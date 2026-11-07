<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Registry;
use Ufw\Request;
use Ufw\ServerRequest;
use Ufw\Route\Literal;
use Ufw\Route\RegEx;
use Ufw\Router;
use Ufw\Tests\Support\RuntimeProbe;

require_once dirname(__DIR__) . '/Fixture/route-controllers.php';

class RouteTest extends TestCase
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
                'controller_aliases' => array('aliased' => 'ping'),
                'controllers' => array('ping'),
                'module' => array(
                    'admin' => array(
                        'controllers' => array('ping'),
                        'controller_aliases' => array('home' => 'ping'),
                    ),
                    'bare' => array(
                        'controllers' => array('ping'),
                    ),
                ),
            ),
        ));
        $app->setDispatchControllers(array('ping'));
        Registry::getInstance()->set('application', $app);
        $this->app = $app;
    }

    public function testLiteralMatchesANormalizedPathAndBuildsTheSameUrl()
    {
        $router = $this->router(array(
            'health' => $this->literal('/health/', array('controller' => 'ping', 'action' => 'index')),
        ), '/health?x=1');

        $route = $router->match();

        $this->assertInstanceOf(Literal::class, $route);
        $this->assertSame('health', $router->getCurrentRouteName());
        $this->assertSame('ping', $router->getRequest()->getDefaults()['controller']);
        $this->assertSame('index', $router->getRequest()->getDefaults()['action']);
        $this->assertSame('/health', $route->buildUrl());
        $this->assertSame($route, $router->getRoute('health'));
    }

    public function testAnEmptyRequestUriMatchesTheRootLiteral()
    {
        $router = $this->router(array(
            'home' => $this->literal('/', array('controller' => 'ping', 'action' => 'index')),
        ), '');

        $this->assertSame('home', $router->match() ? $router->getCurrentRouteName() : null);
        $this->assertSame('/', Literal::normalizePath(''));
        $this->assertFalse((new Literal($router, array('defaults' => array())))->matchRequestPath('/health'));
    }

    public function testNoRouteLeavesTheCurrentRouteEmpty()
    {
        $router = $this->router(array(
            'health' => $this->literal('/health', array('controller' => 'ping', 'action' => 'index')),
        ), '/missing');

        $this->assertNull($router->match());
    }

    public function testAFailedCheckKeepsNamedGroupsAndTheNextRouteCanStillWin()
    {
        $router = $this->router(array(
            'lang' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/(?P<lang>sr|en)/ping$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'constraints' => array(
                    'httpMethod' => array('POST'),
                    'checkIfControllerIsRegistered' => false,
                    'checkAction' => false,
                ),
                'urlBuilder' => array('/:lang/ping'),
            ),
            'plain' => $this->literal('/sr/ping', array('controller' => 'ping', 'action' => 'index')),
        ), '/sr/ping');

        $this->assertSame('plain', $router->match() ? $router->getCurrentRouteName() : null);

        $only = $this->router(array(
            'lang' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/(?P<lang>sr|en)/ping$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'constraints' => array(
                    'httpMethod' => array('POST'),
                    'checkIfControllerIsRegistered' => false,
                    'checkAction' => false,
                ),
                'urlBuilder' => array('/:lang/ping'),
            ),
        ), '/sr/ping');
        $this->assertNull($only->match());
        $this->assertSame('sr', $only->getRequest()->getAttribute('lang'));
    }

    public function testConstraintsAcceptARegisteredPublicActionAndRejectTheRest()
    {
        $route = $this->regexRoute(array(
            'checkIfControllerIsRegistered' => true,
            'checkAction' => true,
            'httpMethod' => array('GET'),
            'id' => '#^\d+$#',
        ));

        $this->assertTrue($route->check(array(
            'controller' => 'ping',
            'action' => 'other',
            'id' => '12',
        )));
        $this->assertSame('\Controller\Ping', $route->getControllerClass());
        $this->assertSame('otherAction', $route->getActionMethod());

        $indexed = $this->regexRoute(array(
            'checkIfControllerIsRegistered' => true,
            'checkAction' => true,
            'httpMethod' => array('GET'),
        ));
        $this->assertTrue($indexed->check(array(
            'module' => '',
            'controller' => 'aliased',
            'action' => 'index',
        )));
        $this->assertSame('\Controller\Ping\Index', $indexed->getControllerClass());
        $this->assertSame('GET', $indexed->getActionMethod());

        $this->assertFalse($route->check(array(
            'controller' => 'missing',
            'action' => 'other',
            'id' => '12',
        )));
        $this->assertFalse($this->regexRoute(array(
            'checkIfControllerIsRegistered' => false,
            'checkAction' => true,
            'httpMethod' => array('GET'),
        ))->check(array(
            'controller' => 'ping',
            'action' => 'secret',
        )));
        $this->assertFalse($this->regexRoute(array(
            'checkIfControllerIsRegistered' => false,
            'checkAction' => false,
            'httpMethod' => array('POST'),
        ))->check(array(
            'controller' => 'ping',
            'action' => 'other',
        )));
        $this->assertFalse($this->regexRoute(array(
            'checkIfControllerIsRegistered' => false,
            'checkAction' => false,
            'id' => '#^\d+$#',
        ))->check(array(
            'controller' => 'ping',
            'action' => 'other',
            'id' => 'nope',
        )));
    }

    public function testRegexBuildsTheMostSpecificUrlAndAppendsATailAndQuery()
    {
        $request = $this->request('/ping/7?existing=1', array('existing' => '1'));
        $router = new Router($this->app, $request, array(
            'ping' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/ping(?:/(?P<id>\d+))?$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'options' => array('allowUriParams' => true),
                'urlBuilder' => array(
                    '/ping',
                    '/ping/:id',
                ),
            ),
        ));
        $router->match();
        $route = $router->getCurrentRoute();

        $this->assertSame('/ping/7/page/2', $route->buildUrl(array('id' => '7', 'page' => '2'), true, false, true));
        $this->assertSame('/ping/7?existing=1', $route->buildUrl(array(), false, true, true));
        $this->assertSame('/ping/note/a b', $route->buildUrl(array('note' => 'a b'), true, false, false));
    }

    public function testTheRemainingRouteAndRouterBranches()
    {
        $router = $this->router(array(
            'health' => $this->literal('/health', array('controller' => 'ping', 'action' => 'index')),
        ), '/health');
        $this->assertSame($this->app, $router->getApplication());
        $router->setApplication($this->app);
        $router->setRequest($router->getRequest());
        $routes = $router->getRoutes();
        $this->assertNotEmpty($routes);
        $router->setRoutes($routes);
        if (RuntimeProbe::routerThrowsLogicExceptionForUnknownRoute()) {
            $this->expectException(\LogicException::class);
        } else {
            $this->expectException(\Error::class);
        }
        $router->getRoute('missing');
    }

    public function testRouterTracksTheCurrentRouteAndRegexNamedGroups()
    {
        $router = $this->router(array(
            'lang' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/(?P<lang>sr|en)/ping$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'constraints' => array(
                    'checkIfControllerIsRegistered' => false,
                    'checkAction' => false,
                ),
            ),
        ), '/sr/ping');
        $route = $router->match();
        $this->assertNotNull($route);
        $router->setCurrentRoute($route);
        $router->setCurrentRouteName('lang');
        $this->assertSame('lang', $router->getCurrentRouteName());
        $this->assertSame($route, $router->getCurrentRoute());
        $this->assertSame('sr', $router->getRequest()->getAttribute('lang'));
    }

    public function testARouterWithNoRouteMapMatchesNothing()
    {
        $router = $this->router(array(), '/health');
        $routes = new \ReflectionProperty(Router::class, 'routes');
        $routes->setAccessible(true);
        $routes->setValue($router, null);

        $this->assertNull($router->match());
    }

    public function testAFailedLiteralCheckKeepsNoNamedGroups()
    {
        $router = $this->router(array(
            'health' => array(
                'type' => Literal::class,
                'path' => '/health',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'constraints' => array(
                    'httpMethod' => array('POST'),
                    'checkIfControllerIsRegistered' => false,
                    'checkAction' => false,
                ),
            ),
        ), '/health');

        $this->assertNull($router->match());
        $this->assertNull($router->getRequest()->getAttribute('lang'));
    }

    public function testPatternsFallThroughAndANonStringUrlBuilderEntryIsSkipped()
    {
        if (!RuntimeProbe::routeRegExSkipsNonStringUrlBuilderEntries()) {
            $this->markTestSkipped('v1.0.2 RegEx urlBuilder does not skip non-string patterns.');
        }
        $hit = $this->router(array(
            'yes' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/no$#', '#^/yes/(?P<id>\d+)$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'urlBuilder' => array('/yes/:id', array('not-a-pattern')),
            ),
        ), '/yes/4');
        $route = $hit->match();
        $this->assertSame('yes', $hit->getCurrentRouteName());
        $this->assertSame('/yes/4', $route->buildUrl(array('id' => '4')));
        $this->assertSame('/yes/4', $route->buildUrl(array('id' => '4')));

        $miss = $this->router(array(
            'nope' => array(
                'type' => RegEx::class,
                'patterns' => array('#^/no$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'urlBuilder' => array('/:required'),
            ),
        ), '/yes');
        $this->assertNull($miss->match());
        $this->assertSame('', $miss->getRoute('nope')->buildUrl(array()));
    }

    public function testUriTailDefaultsModuleAliasesAndTheCurrentController()
    {
        $route = new RegEx($this->router(array(), '/ping/extra/1/tail'), array(
            'defaults' => array('controller' => 'ping', 'action' => 'index', 'id' => '5'),
            'options' => array('allowUriParams' => true),
            'constraints' => array(
                'checkIfControllerIsRegistered' => false,
                'checkAction' => false,
                'id' => '#^\d+$#',
            ),
            'urlBuilder' => array('/ping/:id'),
        ));
        $this->assertTrue($route->check(array('action' => 'other', 'id' => '')));
        $request = $this->request('/ping/a%20b/c%20d/extra/1/tail');
        $route->provideAttributes($request, '/ping/a%20b/c%20d/extra/1/tail', array(
            0 => '/ping',
            'extra' => 'kept',
            'a b' => 'preset',
        ));
        $this->assertSame(array('kept', '1'), $request->getAttribute('extra'));
        $this->assertSame(array('preset', 'c d'), $request->getAttribute('a b'));
        $this->assertNull($request->getAttribute('tail'));

        $fromDefault = $this->regexRoute(array(
            'checkIfControllerIsRegistered' => false,
            'checkAction' => false,
        ));
        $this->assertTrue($fromDefault->check(array('controller' => 'ping')));
        $this->assertSame('index', $fromDefault->getProvisionalActionName());

        $noAction = new RegEx($this->router(array(), '/'), array(
            'defaults' => array('controller' => 'ping', 'action' => ''),
            'constraints' => array(
                'checkIfControllerIsRegistered' => false,
                'checkAction' => false,
            ),
        ));
        $this->assertFalse($noAction->check(array()));

        $admin = $this->regexRoute(array(
            'checkIfControllerIsRegistered' => true,
            'checkAction' => true,
            'httpMethod' => array('GET'),
        ));
        $this->assertFalse($admin->check(array(
            'module' => 'admin',
            'controller' => 'home',
            'action' => 'index',
        )));
        $this->assertSame('admin', $admin->getProvisionalModuleName());
        $this->assertSame('\Controller\Admin\Ping', $admin->getControllerClass());
        $this->assertSame($admin->getRouter()->getRequest(), $admin->getRequest());

        $bare = $this->regexRoute(array(
            'checkIfControllerIsRegistered' => false,
            'checkAction' => false,
        ));
        $this->assertTrue($bare->check(array(
            'module' => 'bare',
            'controller' => 'ping',
            'action' => 'other',
        )));
        $this->assertSame('\Controller\Bare\Ping', $bare->getControllerClass());

        $unknown = $this->regexRoute(array(
            'checkIfControllerIsRegistered' => false,
            'checkAction' => false,
        ));
        $this->assertTrue($unknown->check(array(
            'module' => 'missing',
            'controller' => 'ping',
            'action' => 'other',
        )));
        $this->assertSame('\Controller\Missing\Ping', $unknown->getControllerClass());

        $hasAction = new \ReflectionMethod(RegEx::class, 'hasAction');
        $hasAction->setAccessible(true);
        $bare->setControllerClass('')->setActionMethod('');
        $this->assertFalse($hasAction->invoke($bare));
        $bare->setControllerClass('Missing\\Nope')->setActionMethod('go');
        $this->assertFalse($hasAction->invoke($bare));

        $current = new class($this->app) extends \Ufw\Controller\Base {
            public function getCurrentControllerName()
            {
                return 'from-current';
            }
        };
        $this->app->setCurrentController($current);
        $literal = new Literal($this->router(array(), '/health?kept=1', ), array(
            'path' => '/health',
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'urlBuilder' => array('/:controller'),
        ));
        $literal->setRouteDef($literal->getRouteDef())->setRouter($literal->getRouter());
        $this->assertSame('/health?ping=1&module=admin&controller=from-current', $literal->buildUrl(array(
            'ping' => '1',
            'module' => 'admin',
        ), true, true, true));
        $this->assertSame('/health?controller=other&action=show', $literal->buildUrl(array(
            'controller' => 'other',
            'action' => 'show',
        ), true, true, true));
        $this->assertSame('/health', $literal->buildUrl(array(
            'controller' => 'ping',
            'action' => 'index',
        ), true, true, true));

        $encoded = new RegEx($this->router(array(), '/ping/7'), array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'options' => array('allowUriParams' => true),
            'urlBuilder' => array('/ping/:id'),
        ));
        $this->assertSame('/ping/7/tag/a+b/tag/c', $encoded->buildUrl(array(
            'id' => '7',
            'tag' => array('a b', 'c'),
            'skip' => null,
        ), true, false, true));
        $this->assertSame('/ping/7/tag/a b/tag/c', $encoded->buildUrl(array(
            'id' => '7',
            'tag' => array('a b', 'c'),
        ), true, false, false));
    }

    public function testARouteWithoutPatternsDoesNotMatch()
    {
        $router = $this->router(array(), '/');
        $route = new RegEx($router, array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
        ));

        $this->assertFalse($route->matchRequestPath('/ping'));
        $this->assertTrue($route->check(array()));
    }

    public function testLiteralRouteSettersExposeFluentAccessors()
    {
        $route = new Literal($this->router(array(), '/health'), array(
            'path' => '/health',
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
        ));
        $route->setProvisionalModuleName('admin')
            ->setProvisionalControllerName('home')
            ->setProvisionalActionName('show')
            ->setControllerClass('\\Controller\\Admin\\Ping')
            ->setActionMethod('showAction');
        $this->assertSame('admin', $route->getProvisionalModuleName());
        $this->assertSame('home', $route->getProvisionalControllerName());
        $this->assertSame('show', $route->getProvisionalActionName());
        $this->assertSame('\\Controller\\Admin\\Ping', $route->getControllerClass());
        $this->assertSame('showAction', $route->getActionMethod());
        $def = $route->getRouteDef();
        $route->setRouteDef($def + array('note' => 'x'));
        $this->assertSame('x', $route->getRouteDef()['note']);
    }

    public function testRegExUrlBuilderSelectsPatternWithMostPlaceholderMatches()
    {
        $route = new RegEx($this->router(array(), '/'), array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'urlBuilder' => array(
                '/items/:id',
                '/items/:id/:slug',
            ),
        ));
        $this->assertSame('/items/5/details', $route->buildUrl(array(
            'id' => '5',
            'slug' => 'details',
        )));

        $pick = new \ReflectionMethod(RegEx::class, 'getUrlBuilderString');
        $pick->setAccessible(true);
        $this->assertSame('/items/:id/:slug', $pick->invoke($route, array(
            'id' => '5',
            'slug' => 'details',
        )));
    }

    public function testRouteCheckAppliesPerKeyRegexConstraints()
    {
        $route = new RegEx($this->router(array(), '/ping/abc'), array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'constraints' => array(
                'checkIfControllerIsRegistered' => false,
                'checkAction' => false,
                'slug' => '#^[a-z]+$#',
            ),
        ));
        $this->assertFalse($route->check(array('slug' => 'ABC')));
        $this->assertTrue($route->check(array('slug' => 'abc')));
    }

    /**
     * @param array<string, array<string, mixed>> $routes
     */
    private function router(array $routes, $uri)
    {
        return new Router($this->app, $this->request($uri), $routes);
    }

    /**
     * @param array<string, string> $defaults
     * @return array<string, mixed>
     */
    private function literal($path, array $defaults)
    {
        return array(
            'type' => Literal::class,
            'path' => $path,
            'defaults' => $defaults,
        );
    }

    /**
     * @param array<string, mixed> $constraints
     */
    private function regexRoute(array $constraints)
    {
        $router = $this->router(array(), '/');
        return new RegEx($router, array(
            'defaults' => array('controller' => 'ping', 'action' => 'index'),
            'constraints' => $constraints,
            'urlBuilder' => array('/ping'),
        ));
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
