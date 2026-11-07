<?php

namespace Ufw;

/**
 * UFW request: wraps {@see \Ufw\ServerRequest} with route defaults, attributes, and magic access to PSR-7 attributes.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class Request implements \Psr\Http\Message\ServerRequestInterface, \ArrayAccess
{

    /**
     * @var array<string, mixed>
     */
    protected $defaults = array();

    /**
     * @var \Ufw\Request|null
     */
    protected $prevRequest = null;

    /**
     * @var \Ufw\ServerRequest|null
     */
    protected $serverRequest = null;

    /** @var \Exception|null */
    protected $runtimeException = null;

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $params
     * @param array<string, mixed> $defaults
     */
    public function __construct($values, $params, $defaults, Request $prevRequest = null, \Ufw\ServerRequest $serverRequest = null)
    {
        if (!isset($serverRequest)) {
            $serverRequest = ServerRequestFactory::fromGlobals(null, null, null, null, null, null, $values + $params + $defaults);
        }
        $this->setDefaults($defaults)
            ->setPrevRequest($prevRequest)
            ->setServerRequest($serverRequest);
    }

    /**
     * @return \Ufw\ServerRequest
     */
    private function requireServerRequest()
    {
        if ($this->serverRequest === null) {
            throw new \RuntimeException('Server request not initialized.');
        }
        return $this->serverRequest;
    }

    /**
     * @return string|null
     */
    public function getModule()
    {
        $value = $this->getAttribute('module');
        return is_string($value) ? $value : null;
    }

    /**
     * @return string|null
     */
    public function getController()
    {
        $value = $this->getAttribute('controller');
        return is_string($value) ? $value : null;
    }

    /**
     * @return string|null
     */
    public function getAction()
    {
        $value = $this->getAttribute('action');
        return is_string($value) ? $value : null;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getAttribute($name);
    }

    /**
     * @param string $method
     * @param array<int, mixed> $params
     * @return mixed
     */
    public function __call($method, $params)
    {
        $sr = $this->requireServerRequest();
        if (method_exists($sr, $method)) {
            $callable = [$sr, $method];
            if (is_callable($callable)) {
                return call_user_func_array($callable, $params);
            }
        }

        if (substr($method, 0, 3) == 'get') {
            $prop = lcfirst(substr($method, 3));
            return $this->$prop;
        }

        return null;
    }

    /**
     * ** \ArrayAccess implementation ***
     */

    /**
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->getAttributes());
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->withAttribute((string) $offset, $value);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->withoutAttribute((string) $offset);
    }

    /**
     * ** \Psr\Http\Message\ServerRequestInterface proxy implementation ***
     */
    /**
     * @return array<string, mixed>
     */
    public function getAttributes()
    {
        return $this->requireServerRequest()->getAttributes();
    }

    /**
     * @param array<string, mixed> $attributes
     * @return $this
     */
    public function setAttributes($attributes)
    {
        $this->requireServerRequest()->setAttributes($attributes);
        return $this;
    }

    public function getAttribute($attribute, $default = null)
    {
        return $this->requireServerRequest()->getAttribute($attribute, $default);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getCookieParams()
    {
        return $this->requireServerRequest()->getCookieParams();
    }

    public function getMethod()
    {
        $header = $this->getHeader('X-HTTP-Method-Override');
        $headerMethod = isset($header[0]) ? $header[0] : null;

        return $headerMethod ? $headerMethod : $this->requireServerRequest()->getMethod();
    }

    /**
     * @return array<int|string, mixed>|object|null
     */
    public function getParsedBody()
    {
        return $this->requireServerRequest()->getParsedBody();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getQueryParams()
    {
        return $this->requireServerRequest()->getQueryParams();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getServerParams()
    {
        return $this->requireServerRequest()->getServerParams();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getUploadedFiles()
    {
        return $this->requireServerRequest()->getUploadedFiles();
    }

    public function getRequestTarget()
    {
        return $this->requireServerRequest()->getRequestTarget();
    }

    public function getUri()
    {
        return $this->requireServerRequest()->getUri();
    }

    public function getBody()
    {
        return $this->requireServerRequest()->getBody();
    }

    public function getHeader($header)
    {
        return $this->requireServerRequest()->getHeader($header);
    }

    public function getHeaderLine($name)
    {
        return $this->requireServerRequest()->getHeaderLine($name);
    }

    public function getHeaders()
    {
        return $this->requireServerRequest()->getHeaders();
    }

    public function getProtocolVersion()
    {
        return $this->requireServerRequest()->getProtocolVersion();
    }

    public function hasHeader($header)
    {
        return $this->requireServerRequest()->hasHeader($header);
    }

    public function withAttribute($attribute, $value)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withAttribute($attribute, $value));
    }

    /**
     * @param array<string, mixed> $cookies
     * @return static
     */
    public function withCookieParams(array $cookies)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withCookieParams($cookies));
    }

    public function withMethod($method)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withMethod($method));
    }

    public function withoutAttribute($attribute)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withoutAttribute($attribute));
    }

    /**
     * @param array<string, mixed>|object|null $data
     * @return static
     */
    public function withParsedBody($data)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withParsedBody($data));
    }

    /**
     * @param array<string, mixed> $query
     * @return static
     */
    public function withQueryParams(array $query)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withQueryParams($query));
    }

    /**
     * @param array<string, mixed> $uploadedFiles
     * @return static
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withUploadedFiles($uploadedFiles));
    }

    public function withRequestTarget($requestTarget)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withRequestTarget($requestTarget));
    }

    public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withUri($uri, $preserveHost));
    }

    public function withAddedHeader($header, $value)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withAddedHeader(
                HttpHeaderSanitizer::sanitizeName($header),
                HttpHeaderSanitizer::sanitizeValue($value)
            ));
    }

    public function withBody(\Psr\Http\Message\StreamInterface $body)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withBody($body));
    }

    public function withHeader($header, $value)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withHeader(
                HttpHeaderSanitizer::sanitizeName($header),
                HttpHeaderSanitizer::sanitizeValue($value)
            ));
    }

    public function withoutHeader($header)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withoutHeader($header));
    }

    public function withProtocolVersion($version)
    {
        return $this->setServerRequest($this->requireServerRequest()
            ->withProtocolVersion($version));
    }

    /**
     * Retrieve a member of the $_POST superglobal
     *
     * If no $key is passed, returns the entire $_POST array.
     *
     * @todo How to retrieve from nested arrays
     * @param string $key            
     * @param mixed $default
     *            Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getPost($key = null, $default = null)
    {
        if (null === $key) {
            return $this->getParsedBody();
        }
        $post = $this->getParsedBody();
        if (!is_array($post)) {
            return $default;
        }
        return array_key_exists($key, $post) ? $post[$key] : $default;
    }

    /**
     * Retrieve a member of the $_SERVER superglobal
     *
     * If no $key is passed, returns the entire $_SERVER array.
     *
     * @param string $key            
     * @param mixed $default
     *            Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getServer($key = null, $default = null)
    {
        if (null === $key) {
            return $this->getServerParams();
        }

        return (isset($this->getServerParams()[$key])) ? $this->getServerParams()[$key] : $default;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->$key;
        } elseif (isset($this->getQueryParams()[$key])) {
            return $this->getQueryParams()[$key];
        }
        $body = $this->getParsedBody();
        if (is_array($body) && array_key_exists($key, $body)) {
            return $body[$key];
        }
        return $default;
    }

    /**
     * Was the request made by POST?
     *
     * @return boolean
     */
    public function isPost()
    {
        return 'POST' == $this->getMethod();
    }

    /**
     * Was the request made by GET?
     *
     * @return boolean
     */
    public function isGet()
    {
        return 'GET' == $this->getMethod();
    }

    /**
     * Was the request made by PUT?
     *
     * @return boolean
     */
    public function isPut()
    {
        return 'PUT' == $this->getMethod();
    }

    /**
     * Was the request made by DELETE?
     *
     * @return boolean
     */
    public function isDelete()
    {
        return 'DELETE' == $this->getMethod();
    }

    /**
     * Was the request made by HEAD?
     *
     * @return boolean
     */
    public function isHead()
    {
        return 'HEAD' == $this->getMethod();
    }

    /**
     * Was the request made by OPTIONS?
     *
     * @return boolean
     */
    public function isOptions()
    {
        return 'OPTIONS' == $this->getMethod();
    }

    /**
     * Was the request made by PATCH?
     *
     * @return boolean
     */
    public function isPatch()
    {
        return 'PATCH' == $this->getMethod();
    }

    /**
     * Is the request a Javascript XMLHttpRequest?
     *
     * @return boolean
     */
    public function isXmlHttpRequest()
    {
        $header = $this->getHeader('X-Requested-With');
        $value = isset($header[0]) ? $header[0] : null;

        return $value == 'XMLHttpRequest';
    }

    /**
     * Whether Accept prefers application/json over text/html (same rule as {@see \Ufw\Middleware\JsonResponse}).
     */
    public function prefersApplicationJson(): bool
    {
        $sr = $this->getServerRequest();
        if ($sr === null) {
            return false;
        }

        return self::acceptPrefersApplicationJson($sr);
    }

    /**
     * Shared negotiation for HTML vs JSON responses (error dispatch, JsonResponse middleware, app code).
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     */
    public static function acceptPrefersApplicationJson(\Psr\Http\Message\ServerRequestInterface $request): bool
    {
        $acceptHeader = $request->getHeaderLine('Accept');
        if ($acceptHeader === '') {
            return false;
        }
        $negotiator = new \Negotiation\Negotiator();
        $mediaType = $negotiator->getBest($acceptHeader, [
            'text/html',
            'application/json',
        ]);

        if (!$mediaType instanceof \Negotiation\Accept) {
            return false;
        }

        return $mediaType->getType() === 'application/json';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     * @param array<string, mixed> $defaults
     * @return $this
     */
    public function setDefaults($defaults)
    {
        $this->defaults = $defaults;
        return $this;
    }

    /**
     * @return \Ufw\Request|null
     */
    public function getPrevRequest()
    {
        return $this->prevRequest;
    }

    /**
     * @param \Ufw\Request|null $prevRequest
     * @return $this
     */
    public function setPrevRequest(Request $prevRequest = null)
    {
        $this->prevRequest = $prevRequest;
        return $this;
    }

    /**
     * @return \Ufw\ServerRequest|null
     */
    public function getServerRequest()
    {
        return $this->serverRequest;
    }

    /**
     * @param \Ufw\ServerRequest $serverRequest
     * @return $this
     */
    public function setServerRequest(\Ufw\ServerRequest $serverRequest)
    {
        $this->serverRequest = $serverRequest;
        return $this;
    }

    /**
     * @return \Exception|null
     */
    public function getRuntimeException()
    {
        return $this->runtimeException;
    }

    /**
     * @param \Exception|null $runtimeException
     * @return $this
     */
    public function setRuntimeException($runtimeException)
    {
        $this->runtimeException = $runtimeException;
        return $this;
    }
}
