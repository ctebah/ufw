<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Response as ApiClientResponse;
use Ufw\Tests\Support\RuntimeProbe;

/**
 * Documents consumer contract: response payload may be PSR-7 stream or string until coerced for display/JSON.
 */
class ApiClientResponseCompatibilityTest extends TestCase
{
    public function testPlainTextFromPsr7StreamBodyMatchesWrittenPayload()
    {
        $psr = new \Laminas\Diactoros\Response('php://memory', 200);
        $psr->getBody()->write('{"ok":true}');
        $wrapped = new ApiClientResponse();
        $wrapped->setResponse($psr);

        $raw = $wrapped->getResponse()->getBody();
        $this->assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $raw);

        $text = RuntimeProbe::coerceHttpBodyToPlainText($raw);
        $this->assertSame('{"ok":true}', $text);
    }

    public function testGetBodyMayRemainStreamForNonJsonResponses()
    {
        $psr = new \Laminas\Diactoros\Response('php://memory', 200, array('Content-Type' => 'text/plain'));
        $psr->getBody()->write('plain');
        $wrapped = new ApiClientResponse();
        $wrapped->setResponse($psr);

        $body = $wrapped->getBody();
        if ($body instanceof \Psr\Http\Message\StreamInterface) {
            $this->assertSame('plain', RuntimeProbe::coerceHttpBodyToPlainText($body));
        } else {
            $this->assertSame('plain', RuntimeProbe::coerceHttpBodyToPlainText($body));
        }
    }

    public function testJsonContentTypeBodyIsUsableAsStructuredOrPlainText()
    {
        $psr = new \Laminas\Diactoros\Response('php://memory', 200, array('Content-Type' => 'application/json'));
        $psr->getBody()->write('{"a":1}');
        $wrapped = new ApiClientResponse();
        $wrapped->setResponse($psr);

        $body = $wrapped->getBody();
        if (is_array($body)) {
            $this->assertSame(array('a' => 1), $body);
        } else {
            $decoded = json_decode(RuntimeProbe::coerceHttpBodyToPlainText($body), true);
            $this->assertSame(array('a' => 1), $decoded);
        }
    }
}
