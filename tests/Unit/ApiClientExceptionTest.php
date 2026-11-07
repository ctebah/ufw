<?php
namespace Ufw\Tests\Unit;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Exception as ApiClientException;

class ApiClientExceptionTest extends TestCase
{
    public function testBuildsMessageFromServerException(): void
    {
        $resp = new PsrResponse(500, array(), 'err');
        $srv = new ServerException('Server Error', new PsrRequest('GET', '/'), $resp);
        $fromServer = new ApiClientException(null, null, $srv);
        $this->assertSame('Internal Server Error', $fromServer->getMessage());
    }

    public function testStripTagsFromHtmlPreviousMessage(): void
    {
        $html = new ApiClientException(null, null, new \RuntimeException('<b>fail</b>'));
        $this->assertSame('fail', $html->getMessage());
    }

    public function testTruncatesLongHtmlPreviousMessage(): void
    {
        $req = new PsrRequest('GET', '/api');
        $resp = new PsrResponse(503, array(), 'unavailable');
        $previous = new RequestException('<p>' . str_repeat('z', 2100) . '</p>', $req, $resp);
        $ex = new ApiClientException(null, null, $previous);
        $this->assertSame('Service Unavailable', $ex->getMessage());
    }

    public function testLongHtmlFromRequestExceptionWithoutResponseUsesReasonPhraseOnSecondCheck(): void
    {
        $req = new PsrRequest('GET', '/api');
        $resp = new PsrResponse(502, array(), 'bad');
        $previous = new class('<p>' . str_repeat('z', 2100) . '</p>', $req, $resp) extends RequestException {
            private $hasResponseCalls = 0;

            public function hasResponse(): bool
            {
                $this->hasResponseCalls++;

                return $this->hasResponseCalls > 1;
            }
        };
        $ex = new ApiClientException(null, null, $previous);
        $this->assertSame('Bad Gateway', $ex->getMessage());
    }

    public function testUsesPreviousExceptionMessageWhenPresent(): void
    {
        $ex = new ApiClientException(null, null, new \RuntimeException('upstream'));
        $this->assertSame('upstream', $ex->getMessage());
    }
}
