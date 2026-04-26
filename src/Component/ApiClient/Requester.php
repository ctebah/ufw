<?php
namespace Ufw\Component\ApiClient;

/**
 * Guzzle-backed HTTP layer: {@see getApiEndpointConfig()} supplies `base_uri`, verbs return {@see Response}, {@see getLastResponse()} for debugging.
 */
abstract class Requester
{
    protected static $_instance = null;

    protected $debuglog = true;

    protected $logger = null;

    /**
     *
     * @var \GuzzleHttp\Client
     */
    protected $httpClient = null;

    /**
     *
     * @var \Ufw\Component\ApiClient\Response
     */
    protected $lastResponse = null;

    protected function __construct()
    {
    }

    /**
     * Should return associative array to use in \GuzzleHttp\Client constructor
     * Only requiered key is base_uri
     */
    abstract protected function getApiEndpointConfig();

    /**
     * Singleton model use get_called_class late static binding
     * function to determine name of the called class.
     *
     * @return \Ufw\Component\ApiClient\Base
     */
    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new static();
        }
        return self::$_instance;
    }

    /**
     *
     * @param string $url
     * @param array $body
     * @param array $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function delete($url, $body = array(), $headers = array())
    {
        $ret = $this->doApiRequest('delete', $url, $body, $headers);
        $this->sleep();
        return $ret;
    }

    /**
     *
     * @param string $url
     * @param array $body
     * @param array $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function get($url, $headers = array())
    {
        return $this->doApiRequest('get', $url, null, $headers);
    }

    /**
     *
     * @param string $url
     * @param array $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function head($url, $headers = array())
    {
        return $this->doApiRequest('head', $url, null, $headers);
    }

    /**
     *
     * @param string $url
     * @param array $body
     * @param array $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function patch($url, $body = array(), $headers = array())
    {
        $ret = $this->doApiRequest('patch', $url, $body, $headers);
        $this->sleep();
        return $ret;
    }

    /**
     *
     * @param string $url
     * @param array $body
     * @param array $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function post($url, $body = array(), $headers = array())
    {
        $ret = $this->doApiRequest('post', $url, $body, $headers);
        $this->sleep();
        return $ret;
    }

    /**
     *
     * @param string $url
     * @param array $body
     * @param array $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function put($url, $body = array(), $headers = array())
    {
        $ret = $this->doApiRequest('put', $url, $body, $headers);
        $this->sleep();
        return $ret;
    }

    public function raw($method, $url, $options)
    {
        try {
            $response = $this->getHttpClient()->request($method, $url, $options);
            $this->createResponse($response);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->createExceptionResponse($e);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->createExceptionResponse($e);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->createExceptionResponse($e);
        } catch (\Exception $e) {
            $this->createExceptionResponse($e);
        }
        
        return $this->getLastResponse();
    }

    protected function sleep()
    {
        return $this;
    }

    protected function createExceptionResponse(\Exception $e)
    {
        $response = new \Ufw\Component\ApiClient\Response();
        $this->setLastResponse($response->setException($e));
        return $response;
    }

    protected function createResponse(\Psr\Http\Message\ResponseInterface $resp)
    {
        $response = new \Ufw\Component\ApiClient\Response();
        $this->setLastResponse($response->setResponse($resp));
        return $response;
    }

    /**
     *
     * @param string $method
     * @param string $url
     * @param array|null $body
     * @param array $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    protected function doApiRequest($method, $url, $body, $headers)
    {
        try {
            $response = call_user_func_array(array(
                $this->getHttpClient(),
                $method
            ), array(
                $url,
                array_filter(array(
                    'json' => $body,
                    'headers' => $headers + $this->getHeadersForRequest()
                ), 'is_not_null')
            ));
            $this->createResponse($response);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->createExceptionResponse($e);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->createExceptionResponse($e);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->createExceptionResponse($e);
        } catch (\Exception $e) {
            $this->createExceptionResponse($e);
        }
        
        return $this->getLastResponse();
    }

    public function getHeadersForRequest()
    {
        $headers = array(
            "Accept" => "application/json"
        );
        return $headers;
    }

    /**
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        if (!isset($this->httpClient)) {
            $opts = $this->getApiEndpointConfig();
            
            if ($this->debuglog) {
                $opts['handler'] = $this->createLoggingHandlerStack([
                    'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $this->getApplication()
                        ->getHelper('url')
                        ->url([], false, true) . "\n{req_headers}\n{req_body}" . "\nRESPONSE:\n{res_headers}\n{res_body}\n\n"
                ]);
            }
            
            $this->httpClient = new \GuzzleHttp\Client($opts);
        }
        return $this->httpClient;
    }

    protected function createLoggingHandlerStack(array $messageFormats)
    {
        $stack = \GuzzleHttp\HandlerStack::create();
        
        foreach ($messageFormats as $messageFormat) {
            $stack->unshift($this->createGuzzleLoggingMiddleware($messageFormat));
        }
        
        return $stack;
    }

    protected function createGuzzleLoggingMiddleware($messageFormat)
    {
        return \GuzzleHttp\Middleware::log($this->getLogger(), new \GuzzleHttp\MessageFormatter($messageFormat));
    }

    protected function getLogger()
    {
        if (!$this->logger) {
            $handler = new \Monolog\Handler\RotatingFileHandler(\Ufw\Paths::application() . '/log/apiclient/' . basename(dirname(preg_replace('~\\\\~', '/', get_class($this)))) . '.log');
            $formatter = new \Monolog\Formatter\LineFormatter('[%datetime%] %level_name%: %message%', null, true, true);
            $handler->setFormatter($formatter);
            $this->logger = (new \Monolog\Logger('api-consumer'))->pushHandler($handler);
            $this->logger->setTimezone(new \DateTimeZone('UTC'));
        }
        
        return $this->logger;
    }

    /**
     *
     * @param \GuzzleHttp\Client $client
     */
    protected function setHttpClient(\GuzzleHttp\Client $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     *
     * @param \Ufw\Component\ApiClient\Response $lastResponse
     */
    protected function setLastResponse(\Ufw\Component\ApiClient\Response $lastResponse)
    {
        $this->lastResponse = $lastResponse;
        return $this;
    }

    /**
     *
     * @return \Ufw\Application
     */
    protected function getApplication()
    {
        return \Ufw\Registry::getInstance()->get('application');
    }
}
