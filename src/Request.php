<?php

namespace Ufw;

/**
 * UFW request: wraps {@see \Laminas\Diactoros\ServerRequest} with route defaults, attributes, and magic access to PSR-7 attributes.
 *
 * @method string getController()
 * @method string getAction()
 */
class Request implements \Psr\Http\Message\ServerRequestInterface, \ArrayAccess
{

    protected $defaults = array();

    /**
     *
     * @var \Ufw\Request|null
     */
    protected $prevRequest = null;

    /**
     *
     * @var \Laminas\Diactoros\ServerRequest
     */
    protected $serverRequest = null;

    /** @var \Exception|null */
    protected $runtimeException = null;

    public function __construct($values, $params, $defaults, Request $prevRequest = null, \Laminas\Diactoros\ServerRequest $serverRequest = null)
    {
        if (!isset($serverRequest)) {
            $serverRequest = ServerRequestFactory::fromGlobals(null, null, null, null, null, null, $values + $params + $defaults);
        }
        $this->setDefaults($defaults)
            ->setPrevRequest($prevRequest)
            ->setServerRequest($serverRequest);
    }

    public function __get($name)
    {
        return $this->getAttribute($name);
    }

    public function __call($method, $params)
    {
        if ($this->getServerRequest() && method_exists($this->getServerRequest(), $method)) {
            return call_user_func_array([
                $this->getServerRequest(),
                $method
            ], $params);
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
     *
     * @param
     *            $offset
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
        return $this->withAttribute($offset, $value);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        return $this->withoutAttribute($offset);
    }

    /**
     * ** \Psr\Http\Message\ServerRequestInterface proxy implementation ***
     */
    public function getAttributes()
    {
        return $this->getServerRequest()->getAttributes();
    }

    public function setAttributes($attributes)
    {
        $this->getServerRequest()->setAttributes($attributes);
        return $this;
    }

    public function getAttribute($attribute, $default = null)
    {
        return $this->getServerRequest()->getAttribute($attribute, $default);
    }

    public function getCookieParams()
    {
        return $this->getServerRequest()->getCookieParams();
    }

    public function getMethod()
    {
        $headerMethod = @$this->getHeader('X-HTTP-Method-Override')[0];
        return $headerMethod ? $headerMethod : $this->getServerRequest()->getMethod();
    }

    public function getParsedBody()
    {
        return $this->getServerRequest()->getParsedBody();
    }

    public function getQueryParams()
    {
        return $this->getServerRequest()->getQueryParams();
    }

    public function getServerParams()
    {
        return $this->getServerRequest()->getServerParams();
    }

    public function getUploadedFiles()
    {
        return $this->getServerRequest()->getUploadedFiles();
    }

    public function getRequestTarget()
    {
        return $this->getServerRequest()->getRequestTarget();
    }

    public function getUri()
    {
        return $this->getServerRequest()->getUri();
    }

    public function getBody()
    {
        return $this->getServerRequest()->getBody();
    }

    public function getHeader($header)
    {
        return $this->getServerRequest()->getHeader($header);
    }

    public function getHeaderLine($name)
    {
        return $this->getServerRequest()->getHeaderLine($name);
    }

    public function getHeaders()
    {
        return $this->getServerRequest()->getHeaders();
    }

    public function getProtocolVersion()
    {
        return $this->getServerRequest()->getProtocolVersion();
    }

    public function hasHeader($header)
    {
        return $this->getServerRequest()->hasHeader($header);
    }

    public function withAttribute($attribute, $value)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withAttribute($attribute, $value));
    }

    public function withCookieParams(array $cookies)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withCookieParams($cookies));
    }

    public function withMethod($method)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withMethod($method));
    }

    public function withoutAttribute($attribute)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withoutAttribute($attribute));
    }

    public function withParsedBody($data)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withParsedBody($data));
    }

    public function withQueryParams(array $query)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withQueryParams($query));
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withUploadedFiles($uploadedFiles));
    }

    public function withRequestTarget($requestTarget)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withRequestTarget($requestTarget));
    }

    public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withUri($uri, $preserveHost));
    }

    public function withAddedHeader($header, $value)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withAddedHeader(
                HttpHeaderSanitizer::sanitizeName($header),
                HttpHeaderSanitizer::sanitizeValue($value)
            ));
    }

    public function withBody(\Psr\Http\Message\StreamInterface $body)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withBody($body));
    }

    public function withHeader($header, $value)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withHeader(
                HttpHeaderSanitizer::sanitizeName($header),
                HttpHeaderSanitizer::sanitizeValue($value)
            ));
    }

    public function withoutHeader($header)
    {
        return $this->setServerRequest($this->getServerRequest()
            ->withoutHeader($header));
    }

    public function withProtocolVersion($version)
    {
        return $this->setServerRequest($this->getServerRequest()
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

    public function getParam($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->$key;
        } elseif (isset($this->getQueryParams()[$key])) {
            return $this->getQueryParams()[$key];
        } elseif (isset($this->getParsedBody()[$key])) {
            return $this->getParsedBody()[$key];
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
        return @$this->getHeader('X-Requested-With')[0] == 'XMLHttpRequest';
    }

    /**
     * Whether Accept prefers application/json over text/html (same rule as {@see \Ufw\Middleware\JsonResponse}).
     */
    public function prefersApplicationJson(): bool
    {
        $sr = $this->getServerRequest();

        return $sr ? self::acceptPrefersApplicationJson($sr) : false;
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
        /**
         * @var \Negotiation\Accept $mediaType
         */
        $mediaType = $negotiator->getBest($acceptHeader, [
            'text/html',
            'application/json',
        ]);

        return $mediaType && $mediaType->getValue() === 'application/json';
    }

    /**
     *
     * @return array
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     *
     * @param array $defaults            
     */
    public function setDefaults($defaults)
    {
        $this->defaults = $defaults;
        return $this;
    }

    /**
     *
     * @return request
     */
    public function getPrevRequest()
    {
        return $this->prevRequest;
    }

    /**
     *
     * @param request $prevRequest            
     */
    public function setPrevRequest(Request $prevRequest = null)
    {
        $this->prevRequest = $prevRequest;
        return $this;
    }

    /**
     *
     * @return \Ufw\ServerRequest
     */
    public function getServerRequest()
    {
        return $this->serverRequest;
    }

    /**
     *
     * @param \Laminas\Diactoros\ServerRequest $serverRequest            
     */
    public function setServerRequest(\Laminas\Diactoros\ServerRequest $serverRequest = null)
    {
        $this->serverRequest = $serverRequest;
        return $this;
    }

    /**
     *
     * @return \Exception
     */
    public function getRuntimeException()
    {
        return $this->runtimeException;
    }

    /**
     *
     * @param \Exception $runtimeException            
     */
    public function setRuntimeException($runtimeException)
    {
        $this->runtimeException = $runtimeException;
        return $this;
    }
}
