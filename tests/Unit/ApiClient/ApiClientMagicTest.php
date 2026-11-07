<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Helper;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;

class ApiClientMagicTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        parent::tearDown();
    }

    public function testNormalizeExceptionMessageAndMappedKeyArray()
    {
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $normalize = new \ReflectionMethod(ApiClientTestApi::class, 'normalizeExceptionMessage');
        $normalize->setAccessible(true);
        $this->assertSame('x', $normalize->invoke($api, 'x'));
        $this->assertSame(array('a'), $normalize->invoke($api, array('a', 7)));

        $helper = Helper::getInstance();
        $mapped = new \ReflectionMethod(Helper::class, '_getMappedKeyArray');
        $mapped->setAccessible(true);
        $this->assertSame('remote', $mapped->invoke($helper, array('title' => 'remote'), 'title'));
    }

    public function testClientResolveRequesterThrowsWhenMissingAppRequester()
    {
        $resolve = new \ReflectionMethod(Client::class, 'resolveRequester');
        $resolve->setAccessible(true);
        $client = Client::getInstance();
        $this->expectException(\RuntimeException::class);
        $resolve->invoke($client);
    }
}
