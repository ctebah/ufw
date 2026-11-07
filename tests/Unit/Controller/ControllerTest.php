<?php
namespace Ufw\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Controller\RestApi;
use Ufw\Response;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

class ControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testRestApiInitSetsJsonFormatAndJsonMiddleware()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $app->getResponse()->setFormat('html');
        $controller = new class($app) extends RestApi {
            public function exposeMiddlewaresPre()
            {
                return $this->middlewaresPre();
            }
        };
        $controller->init();
        $this->assertSame('json', $app->getResponse()->getFormat());
        $this->assertContains(\Ufw\Middleware\JsonRequest::class, $controller->exposeMiddlewaresPre());
    }

    public function testControllerNameAndModuleGetters()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array(
            'module' => 'admin',
            'controller' => 'ping',
            'action' => 'index',
        )));
        $controller = new class($app) extends ControllerBase {
            protected $ident = 'my-ping';
        };
        $app->setCurrentController($controller);

        $this->assertSame('index', $controller->getCurrentActionName());
        $this->assertSame('ping', $controller->getCurrentControllerName());
        $this->assertSame('admin', $controller->getCurrentModuleName());
        $this->assertSame('my-ping', $controller->getIdent());
        $this->assertSame('index', $controller->getScriptName());
        $getDb = new \ReflectionMethod(ControllerBase::class, 'getDb');
        $getDb->setAccessible(true);
        $this->assertInstanceOf(\Ufw\Db\Db::class, $getDb->invoke($controller));
        $this->assertSame($app, $controller->getApplication());
        $this->assertSame($app, $controller->__get('application'));
    }
}
