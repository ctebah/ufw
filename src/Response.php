<?php
namespace Ufw;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Mutable response facade around {@see \Laminas\Diactoros\Response}: body/content, {@see $format} (`html`, `json`, …), and layout/view helpers.
 *
 * Each format name maps to a handler {@code (mixed $content, self $response): ResponseInterface}. Defaults are installed on first use;
 * {@see registerFormat()} replaces or adds handlers (e.g. CSV, custom JSON).
 */
class Response implements ResponseInterface
{
    private const JSON_ENCODE_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE;

    /** @var ResponseInterface|null */
    protected $innerResponse = null;

    /** @var mixed */
    protected $content = null;

    /** @var string */
    protected $format = 'html';

    /** @var \Laminas\Diactoros\Response */
    protected $provisionalResponse = null;

    /**
     * @var array<string, callable(mixed, self): ResponseInterface>|null
     */
    protected static $formatHandlers = null;

    public function __construct()
    {
        $this->reset(200);
    }

    /**
     * @param int $statusCode
     * @return $this
     */
    public function reset($statusCode = 200)
    {
        $this->provisionalResponse = new \Laminas\Diactoros\Response('php://memory', $statusCode, []);

        return $this;
    }

    /**
     * Provisional PSR-7 response (headers/status); used by format handlers that wrap the body.
     */
    public function getProvisionalResponse(): \Laminas\Diactoros\Response
    {
        return $this->provisionalResponse;
    }

    public function getBody()
    {
        return $this->provisionalResponse->getBody();
    }

    public function getHeader($header)
    {
        return $this->provisionalResponse->getHeader($header);
    }

    public function getHeaders()
    {
        return $this->provisionalResponse->getHeaders();
    }

    public function getHeaderLine($name)
    {
        return $this->provisionalResponse->getHeaderLine($name);
    }

    public function getProtocolVersion()
    {
        return $this->provisionalResponse->getProtocolVersion();
    }

    public function getStatusCode()
    {
        return $this->provisionalResponse->getStatusCode();
    }

    public function getReasonPhrase()
    {
        return $this->provisionalResponse->getReasonPhrase();
    }

    public function hasHeader($header)
    {
        return $this->provisionalResponse->hasHeader($header);
    }

    public function withBody(StreamInterface $body)
    {
        $this->provisionalResponse = $this->provisionalResponse->withBody($body);

        return $this;
    }

    public function withHeader($header, $value)
    {
        $this->provisionalResponse = $this->provisionalResponse->withHeader(
            HttpHeaderSanitizer::sanitizeName($header),
            HttpHeaderSanitizer::sanitizeValue($value)
        );

        return $this;
    }

    public function withAddedHeader($header, $value)
    {
        $this->provisionalResponse = $this->provisionalResponse->withAddedHeader(
            HttpHeaderSanitizer::sanitizeName($header),
            HttpHeaderSanitizer::sanitizeValue($value)
        );

        return $this;
    }

    public function withoutHeader($header)
    {
        $this->provisionalResponse = $this->provisionalResponse->withoutHeader($header);

        return $this;
    }

    public function withProtocolVersion($version)
    {
        $this->provisionalResponse = $this->provisionalResponse->withProtocolVersion($version);

        return $this;
    }

    public function withStatus($code, $reasonPhrase = '')
    {
        $this->provisionalResponse = $this->provisionalResponse->withStatus($code, $reasonPhrase);

        return $this;
    }

    /**
     * @return $this
     */
    public function emit()
    {
        $this->buildInnerResponseObject();
        $emitter = new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter();
        $emitter->emit($this->getInnerResponse());

        return $this;
    }

    /**
     * Replace or add a format handler. Handlers must return a {@see ResponseInterface} (e.g. {@see HtmlResponse}, {@see JsonResponse},
     * or {@see \Laminas\Diactoros\Response} with a stream body).
     *
     * @param callable(mixed, self): ResponseInterface $handler
     */
    public static function registerFormat(string $format, callable $handler): void
    {
        self::handlers();
        self::$formatHandlers[$format] = $handler;
    }

    /**
     * @return bool
     */
    public function mayHaveHtmlLayout()
    {
        return $this->shouldRenderViewScript() && !$this->getRequest()->isXmlHttpRequest();
    }

    /**
     * @return bool
     */
    public function shouldRenderViewScript()
    {
        return in_array($this->getFormat(), [
            'html',
        ], true);
    }

    /**
     * @return ResponseInterface
     */
    public function getInnerResponse()
    {
        if ($this->innerResponse === null) {
            $this->buildInnerResponseObject();
        }
        if (!($this->innerResponse instanceof ResponseInterface)) {
            throw new \RuntimeException('Response format handler did not produce a response.');
        }

        return $this->innerResponse;
    }

    /**
     * @param ResponseInterface $innerResponse
     * @return $this
     */
    public function setInnerResponse(ResponseInterface $innerResponse)
    {
        $this->innerResponse = $innerResponse;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @param string $format
     * @return $this
     */
    public function setFormat($format)
    {
        $this->format = $format;

        return $this;
    }

    /**
     * @return \Ufw\Request
     */
    public function getRequest()
    {
        $request = \Ufw\Registry::getInstance()->getApplication()->getRequest();
        if ($request === null) {
            throw new \RuntimeException('Request is not available.');
        }
        return $request;
    }

    /**
     * @return $this
     */
    protected function buildInnerResponseObject()
    {
        $handlers = self::handlers();
        $key = isset($handlers[$this->format]) ? $this->format : 'raw';
        $this->innerResponse = $handlers[$key]($this->getContent(), $this);

        return $this;
    }

    /**
     * @return array<string, callable(mixed, self): ResponseInterface>
     */
    protected static function handlers(): array
    {
        if (self::$formatHandlers === null) {
            self::$formatHandlers = [
                'html' => [self::class, 'defaultHtmlHandler'],
                'json' => [self::class, 'defaultJsonHandler'],
                'raw' => [self::class, 'defaultRawHandler'],
            ];
        }

        return self::$formatHandlers;
    }

    /**
     * @param mixed $content
     */
    private static function defaultHtmlHandler($content, self $response): ResponseInterface
    {
        return new HtmlResponse(self::stringifyResponseContent($content), $response->getStatusCode(), $response->getHeaders());
    }

    /**
     * @param mixed $content
     */
    private static function defaultJsonHandler($content, self $response): ResponseInterface
    {
        return new JsonResponse($content, $response->getStatusCode(), $response->getHeaders(), self::JSON_ENCODE_FLAGS);
    }

    /**
     * @param mixed $content
     */
    private static function defaultRawHandler($content, self $response): ResponseInterface
    {
        $c = $response->getContent();
        if (!$c && $c !== '0') {
            return $response->getProvisionalResponse();
        }
        $stream = new Stream('php://temp', 'wb+');
        $stream->write(self::stringifyResponseContent($c));

        return $response->getProvisionalResponse()->withBody($stream);
    }

    /**
     * @param mixed $content
     */
    private static function stringifyResponseContent($content): string
    {
        if (is_string($content)) {
            return $content;
        }
        if (is_int($content) || is_float($content)) {
            return (string) $content;
        }
        if (is_object($content) && method_exists($content, '__toString')) {
            return (string) $content;
        }
        return '';
    }
}
