<?php
namespace Ufw\Component\ApiClient;

/**
 * Wraps decoded JSON (or error) from Guzzle: status {@see $code}, body tree, optional {@see $exception}; {@see __call()} delegates to PSR-7 or the exception.
 */
class Response
{

    /**
     * @var mixed
     */
    protected $body;

    /**
     * @var int|null
     */
    protected $code = null;

    /**
     * @var \Throwable|null
     */
    protected $exception = null;

    /**
     * @var \Psr\Http\Message\ResponseInterface|null
     */
    protected $response = null;

    /**
     * @param string $method
     * @param array<int, mixed> $params
     * @return mixed
     */
    public function __call($method, array $params)
    {
        $target = $this->exception !== null ? $this->exception : $this->response;
        if ($target === null) {
            trigger_error('No response or exception to delegate to', E_USER_ERROR);
        }
        if (!method_exists($target, $method)) {
            trigger_error("Method $method not found", E_USER_ERROR);
        }

        return $target->$method(...$params);
    }

    /**
     * @return bool
     */
    public function hasException()
    {
        return $this->getException() !== null;
    }

    /**
     * @return bool
     */
    public function isOk()
    {
        return !$this->hasException();
    }

    /**
     * @return bool
     */
    public function isClientException()
    {
        return $this->getException() instanceof \GuzzleHttp\Exception\ClientException;
    }

    /**
     * @return bool
     */
    public function isRequestException()
    {
        return $this->getException() instanceof \GuzzleHttp\Exception\RequestException;
    }

    /**
     * @return bool
     */
    public function isServerError()
    {
        return $this->getException() instanceof \GuzzleHttp\Exception\ServerException;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        if (!isset($this->body)) {
            if ($this->getResponse()) {
                $body = (string) $this->getResponse()->getBody();
                if (preg_grep('~^application/json~', $this->getResponse()->getHeader('content-type'))) {
                    $body = json_decode($body, true);
                }
                $this->body = $body;
            }
        }
        return $this->body;
    }

    /**
     * @param string $type
     * @return mixed
     */
    public function getMessage($type = 'error')
    {
        $body = $this->getBody();
        return is_array($body) && isset($body[$type]) ? $body[$type] : null;
    }

    /**
     * @return int|null
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param int $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return \Throwable|null
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @param \Exception $exception
     * @return $this
     */
    public function setException(\Exception $exception)
    {
        if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
            $exception = new \GuzzleHttp\Exception\ConnectException(
                "Network error, please try again later.",
                $exception->getRequest(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            );
        }
        
        $this->exception = $exception;
        $this->body = null;
        if ($exception instanceof \GuzzleHttp\Exception\RequestException && $exception->hasResponse()) {
            $httpResponse = $exception->getResponse();
            if ($httpResponse !== null) {
                $this->setResponse($httpResponse);
            }
        }
        return $this;
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return $this
     */
    public function setResponse(\Psr\Http\Message\ResponseInterface $response)
    {
        $this->response = $response;
        $this->setCode($response->getStatusCode());
        $this->body = null;
        return $this;
    }
}
