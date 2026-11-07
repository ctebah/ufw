<?php
namespace Ufw\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Registry;

class ApplicationGlobalsContextTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = array();
        $_SERVER = array();
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testBuildRequestContextLinesFromGlobalsIncludesQueryRefererAndTruncatedAgent()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);

        $_GET = array('page' => '2');
        $_SERVER = array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/items/list',
            'HTTP_HOST' => 'example.test',
            'HTTPS' => 'on',
            'HTTP_REFERER' => 'https://example.test/prev',
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_USER_AGENT' => str_repeat('x', 300),
        );

        $build = new \ReflectionMethod(Application::class, 'buildRequestContextLinesFromGlobals');
        $build->setAccessible(true);
        $lines = $build->invoke($app);
        $blob = implode("\n", $lines);

        $this->assertStringContainsString('POST', $blob);
        $this->assertStringContainsString('https://example.test/items/list', $blob);
        $this->assertStringContainsString('Query: page=2', $blob);
        $this->assertStringContainsString('Referer:', $blob);
        $this->assertStringContainsString('Client: 10.0.0.1', $blob);
        $this->assertStringContainsString('...', $blob);
        $this->assertStringContainsString('decodeRequest', $blob);
    }

    public function testBuildRequestContextWhenHostMissingUsesRequestUriOnly()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $_SERVER = array(
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/bare',
        );
        $build = new \ReflectionMethod(Application::class, 'buildRequestContextLinesFromGlobals');
        $build->setAccessible(true);
        $lines = $build->invoke($app);
        $this->assertStringContainsString('/bare', implode("\n", $lines));
    }
}
