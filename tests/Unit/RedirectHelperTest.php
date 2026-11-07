<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Exception\BreakExecution;
use Ufw\Helper\Redirect;
use Ufw\Registry;
use Ufw\Response;

class RedirectHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testRedirectSetsLocationHeaderAndBreaksWith302()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);

        $response = new Response();
        $ref = new \ReflectionProperty(Application::class, 'response');
        $ref->setAccessible(true);
        $ref->setValue($app, $response);

        $controller = new class($app, $response) extends ControllerBase {
            /**
             * @var Response
             */
            private $localResponse;

            public function __construct(Application $application, Response $response)
            {
                $this->localResponse = $response;
                parent::__construct($application);
            }

            public function getResponse()
            {
                return $this->localResponse;
            }
        };
        $app->setCurrentController($controller);

        $redirect = new Redirect();
        $redirect->setController($controller);

        try {
            $redirect('/go/here');
            $this->fail('Expected BreakExecution');
        } catch (BreakExecution $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame('/go/here', $response->getHeaderLine('Location'));
        $this->assertSame('raw', $response->getFormat());
    }
}
