<?php
namespace Ufw\Tests\Unit\Db;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Model\Behaviour\OptimisticLock;
use Ufw\Model\Exception\StaleRecord;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Response;
use Ufw\ServerRequest;
use Ufw\ServerRequestFactory;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\ApplicationFixtures;

class DbCriticalPathRetryTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        $_GET = array();
        $_POST = array();
        $_SERVER = array();
        parent::tearDown();
    }

    public function testRequestGlobalsFactoryGettersAndParamResolution()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = array('fromQuery' => 'q');
        $_POST = array('fromPost' => 'p');

        $request = new Request(array('module' => 'admin', 'controller' => 'dash', 'action' => 'home'), array(), array('def' => 1));

        $this->assertSame('admin', $request->getModule());
        $this->assertSame('dash', $request->getController());
        $this->assertSame('home', $request->getAction());
        $this->assertTrue($request->isGet());
        $this->assertFalse($request->isPost());

        $this->assertSame('q', $request->getParam('fromQuery'));
        $this->assertSame('p', $request->getParam('fromPost'));
        $this->assertSame('missing', $request->getParam('nope', 'missing'));

        $psr = $request->getServerRequest();
        $this->assertNotNull($psr);
        $request->setServerRequest($psr);
        $this->assertSame($psr, $request->getServerRequest());

        $nonArrayBody = new ServerRequest(
            array('REQUEST_METHOD' => 'POST'),
            array(),
            '/',
            'POST',
            'php://input',
            array('Content-Type' => 'text/plain'),
            array(),
            array(),
            'plain',
            '1.1',
            array()
        );
        $postBody = new Request(array(), array(), array(), null, $nonArrayBody);
        $this->assertSame('fallback', $postBody->getPost('field', 'fallback'));
        $this->assertSame('plain', $postBody->getPost());

        $xhr = new Request(
            array(),
            array(),
            array(),
            null,
            new ServerRequest(
                array('REQUEST_METHOD' => 'GET'),
                array(),
                '/',
                'GET',
                'php://input',
                array('X-Requested-With' => array('XMLHttpRequest'))
            )
        );
        $this->assertTrue($xhr->isXmlHttpRequest());

        $this->assertFalse(Request::acceptPrefersApplicationJson(
            ServerRequestFactory::fromGlobals(array('HTTP_ACCEPT' => 'text/html'))
        ));
    }

    public function testOptimisticLockThrowsStaleRecordWhenNoRowUpdated()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);

        $record = new WidgetPlain(array('id' => 8, 'code' => 'c', 'data' => '{}', 'version' => 1));
        $record->captureReadState();
        $lock = new OptimisticLock($record);
        $record->setLastWriteAffected(0);

        $this->expectException(StaleRecord::class);
        $lock->afterUpdate(true, array());
    }

    public function testResponseJsonInnerResponseAndRequestFromRegistry()
    {
        list($app) = ApplicationFixtures::bareWithController(array());
        $app->setRequest(new Request(array('controller' => 'ping', 'action' => 'index'), array(), array(), null, new ServerRequest()));

        $response = new Response();
        $response->setFormat('json');
        $response->setContent(array('ok' => true));
        $this->assertFalse($response->shouldRenderViewScript());

        $inner = $response->getInnerResponse();
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $inner);
        $this->assertSame($app->getRequest(), $response->getRequest());
    }
}
