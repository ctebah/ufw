<?php
namespace Ufw\Tests\Unit\Infra;

use Ufw\Tests\Support\TestAppPaths;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Laminas\Diactoros\Response\JsonResponse as DiactorosJsonResponse;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\TestCase;
use Ufw\Form\Validator\Csrf as CsrfValidator;
use Ufw\Form\Validator\RegEx as RegExValidator;
use Ufw\Helper\FlashMessenger;
use Ufw\Paths;
use Ufw\Response;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\ApiClientRequesterProbe;

class PathsAndRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientRequesterProbe::resetInstance();
        $this->resetFlashMessengerStatics();
        parent::tearDown();
    }

    private function resetFlashMessengerStatics()
    {
        $ref = new \ReflectionClass(FlashMessenger::class);
        foreach (array('_messages', '_session') as $propName) {
            if (!$ref->hasProperty($propName)) {
                continue;
            }
            $prop = $ref->getProperty($propName);
            $prop->setAccessible(true);
            $prop->setValue(null, $propName === '_session' ? null : array());
        }
    }

    public function testResponseJsonHtmlAndCustomFormatHandlers()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));

        $html = new Response();
        $html->setContent('<p>x</p>');
        $html->setFormat('html');
        $this->assertStringContainsString('<p>', (string) $html->getInnerResponse()->getBody());

        $json = new Response();
        $json->setFormat('json');
        $json->setContent(array('ok' => true));
        $inner = $json->getInnerResponse();
        $this->assertInstanceOf(DiactorosJsonResponse::class, $inner);

        Response::registerFormat('plain', function ($content, Response $response) {
            $stream = new Stream('php://memory', 'wb+');
            $stream->write((string) $content);

            return $response->getProvisionalResponse()->withBody($stream);
        });
        $plain = new Response();
        $plain->setFormat('plain');
        $plain->setContent('text');
        $this->assertSame('text', (string) $plain->getInnerResponse()->getBody());
    }

    public function testResponsePsr7MutatorsAndLayoutHelpers()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));

        $response = new Response();
        $response->withHeader('X-Test', '1')->withAddedHeader('X-Test', '2')->withoutHeader('X-Missing');
        $this->assertSame('1,2', $response->getHeaderLine('X-Test'));
        $response->withStatus(404, 'Nope')->withProtocolVersion('1.0');
        $this->assertSame(404, $response->getStatusCode());

        $response->setFormat('html');
        $this->assertTrue($response->shouldRenderViewScript());
        $this->assertTrue($response->mayHaveHtmlLayout());

        $response->reset(201);
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testFlashMessengerNamespacesAndAggregators()
    {
        ApplicationFixtures::bareWithController();
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        @session_start();
        $_SESSION = array();
        $this->resetFlashMessengerStatics();

        $flash = new FlashMessenger();
        $flash->setNamespace(FlashMessenger::NAMESPACE_ERROR);
        $flash->addMessage('e1', FlashMessenger::NAMESPACE_ERROR);
        $this->assertTrue($flash->hasErrors());
        $errors = $flash->getErrors();
        $this->assertContains('e1', $errors);

        $flash->addMessage('w1', FlashMessenger::NAMESPACE_WARNING);
        $this->assertTrue($flash->hasWarnings());
        $this->assertContains('w1', $flash->getWarnings());

        $flash->addMessage('i1', FlashMessenger::NAMESPACE_INFO);
        $this->assertTrue($flash->hasInfos());
        $this->assertContains('i1', $flash->getInfos());

        $flash->off();
        $this->assertTrue($flash->isOff());
        $flash->on(true);
        $this->assertFalse($flash->isOff());
        $this->assertSame($flash, $flash());
    }

    public function testRequesterRawCapturesRequestException()
    {
        ApplicationFixtures::bareWithController();
        Paths::setApplicationPath(TestAppPaths::app());

        $mock = new MockHandler(array(
            new RequestException('network', new PsrRequest('GET', 'http://api.test/fail')),
        ));
        $guzzle = new GuzzleClient(array(
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'http://api.test',
        ));

        $requester = ApiClientRequesterProbe::getInstance();
        $requester->injectHttpClient($guzzle);
        $requester->disableDebugLog();

        $requester->raw('GET', '/fail', array());
        $last = $requester->getLastResponse();
        $this->assertNotNull($last);
        $this->assertTrue($last->hasException());
    }

    public function testFormValidatorsCsrfAndRegEx()
    {
        ApplicationFixtures::bareWithController();
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        @session_start();
        $_SESSION = array();
        $token = \Ufw\Component\Csrf::token('form');
        $csrf = new CsrfValidator('form');
        $this->assertTrue($csrf->isValid($token, array('csrf_token' => $token)));
        $this->assertFalse($csrf->isValid('bad', array('csrf_token' => 'bad')));

        $regex = new RegExValidator('/^[a-z]+$/');
        $this->assertTrue($regex->isValid('abc'));
        $this->assertFalse($regex->isValid('abc123'));
    }
}
