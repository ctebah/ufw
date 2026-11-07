<?php
namespace Ufw\Tests\Unit\ApiClient;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Exception as ApiClientException;
use Ufw\Component\ApiClient\Response as ApiClientResponse;
use Ufw\Component\ApiClient\Base\TraitRE;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClient\Client as SupportApiClient;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientRequesterProbe;

class HttpResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientClientProbe::resetInstance();
        SupportApiClient::resetForTests();
        parent::tearDown();
    }

    public function testExceptionCarriesMessagesAndResponseWrapper(): void
    {
        $ex = new ApiClientException(array('one', 'two'), 418, null);
        $this->assertSame(array('one', 'two'), $ex->getMessages());
        $wrap = new ApiClientResponse();
        $ex->setResponse($wrap);
        $this->assertSame($wrap, $ex->getResponse());

        $req = new PsrRequest('GET', '/x');
        $resp = new PsrResponse(502, array(), 'x');
        $previous = new RequestException(str_repeat('x', 2100), $req, $resp);
        $mapped = new ApiClientException(null, null, $previous);
        $this->assertSame('Bad Gateway', $mapped->getMessage());
    }

    public function testClientResolvesRequesterAndCachesInstance(): void
    {
        ApplicationFixtures::bareWithController();
        Client::getInstance()->setRequester(ApiClientRequesterProbe::getInstance());
        $client = Client::getInstance();
        $first = $client->getRequester();
        $this->assertSame($first, $client->getRequester());
    }

    public function testClientResolveRequesterFailsWhenClassMissing(): void
    {
        $client = ApiClientClientProbe::getInstance();
        $resolve = new \ReflectionMethod(Client::class, 'resolveRequester');
        $resolve->setAccessible(true);
        $this->expectException(\RuntimeException::class);
        $resolve->invoke($client);
    }

    public function testClientResolveRequesterInstantiatesSiblingRequesterClass(): void
    {
        ApplicationFixtures::bareWithController();
        SupportApiClient::resetForTests();
        \Ufw\Tests\Support\ApiClient\Requester::resetForTests();
        $client = SupportApiClient::getInstance();
        $resolve = new \ReflectionMethod(Client::class, 'resolveRequester');
        $resolve->setAccessible(true);
        $requester = $resolve->invoke($client);
        $this->assertInstanceOf(\Ufw\Tests\Support\ApiClient\Requester::class, $requester);
    }

    public function testTraitReSkipsReloadAfterReadFailure(): void
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        Client::getInstance()->setRequester(ApiClientRequesterProbe::getInstance());
        $api = TraitReFailingProbe::getInstance(Client::getInstance());
        $calls = 0;
        $api->setReadCallback(function () use (&$calls) {
            $calls++;
            throw new \RuntimeException('read failed');
        });
        try {
            $api->read(true);
        } catch (\RuntimeException $e) {
            $this->assertSame('read failed', $e->getMessage());
        }
        $api->read(true);
        $this->assertSame(1, $calls);
        $api->forget();
        try {
            $api->read(true);
        } catch (\RuntimeException $e) {
            $this->assertSame('read failed', $e->getMessage());
        }
        $this->assertSame(2, $calls);
    }
}

class ApiClientClientProbe extends Client
{
    public static function resetInstance()
    {
        $prop = new \ReflectionProperty(Client::class, '_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
        $prop = new \ReflectionProperty(self::class, '_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
    }
}

class TraitReFailingProbe extends ApiClientTestApi
{
    use TraitRE;

    /** @var callable|null */
    public $readCallback;

    public function setReadCallback($cb)
    {
        $this->readCallback = $cb;
    }

    protected function _doRead()
    {
        if (is_callable($this->readCallback)) {
            return call_user_func($this->readCallback);
        }

        return null;
    }
}
