<?php
namespace Ufw\Component\ApiClient;

/**
 * Wraps decoded JSON (or error) from Guzzle: status {@see $code}, body tree, optional {@see $exception}; {@see __call()} delegates to PSR-7 or the exception.
 */
class Response
{

    /**
     *
     * @var array
     */
    protected $body;

    /**
     *
     * @var int
     */
    protected $code = null;

    /**
     *
     * @var \GuzzleHttp\Exception\BadResponseException
     */
    protected $exception = null;

    /**
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response = null;

    public function __call($method, array $params)
    {
        return call_user_func_array(array(
            isset($this->exception) ? $this->exception : $this->response,
            $method
        ), $params);
    }

    /**
     *
     * @return boolean
     */
    public function hasException()
    {
        return !!$this->getException();
    }

    /**
     *
     * @return boolean
     */
    public function isOk()
    {
        return !$this->getException();
    }

    /**
     *
     * @return boolean
     */
    public function isClientException()
    {
        return $this->getException() instanceof \GuzzleHttp\Exception\ClientException;
    }

    /**
     *
     * @return boolean
     */
    public function isRequestException()
    {
        return $this->getException() instanceof \GuzzleHttp\Exception\RequestException;
    }

    /**
     *
     * @return boolean
     */
    public function isServerError()
    {
        return $this->getException() instanceof \GuzzleHttp\Exception\ServerException;
    }

    /**
     *
     * @return array|string
     */
    public function getBody()
    {
        if (!isset($this->body)) {
            if ($this->getResponse()) {
                $body = $this->getResponse()->getBody();
                if (preg_grep('~^application/json~', $this->getResponse()->getHeader('content-type'))) {
                    $body = json_decode($body, true);
                }
                $this->body = $body;
            }
        }
        return $this->body;
    }

    /**
     *
     * @param string $type
     * @return NULL|array|string
     */
    public function getMessage($type = 'error')
    {
        $body = $this->getBody();
        return is_array($body) && isset($body[$type]) ? $body[$type] : null;
    }

    /**
     *
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     *
     * @param int $code
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     *
     * @return \GuzzleHttp\Exception\BadResponseException
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     *
     * @param \GuzzleHttp\Exception\BadResponseException $exception
     */
    public function setException(\Exception $exception)
    {
        if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
            $exception = new \GuzzleHttp\Exception\ConnectException("Network error, please try again later.", $exception->getRequest(), $exception->getPrevious(), $exception->getHandlerContext());
        }
        
        $this->exception = $exception;
        $this->body = null;
        if (method_exists($exception, 'hasRespons') && $exception->hasResponse()) {
            $this->setResponse($exception->getResponse());
        }
        return $this;
    }

    /**
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function setResponse(\Psr\Http\Message\ResponseInterface $response)
    {
        $this->response = $response;
        $this->setCode($response->getStatusCode());
        $this->body = null;
        return $this;
    }
}
