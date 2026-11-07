<?php
namespace Ufw\Component\ApiClient;

/**
 * Guzzle-backed HTTP layer: {@see getApiEndpointConfig()} supplies `base_uri`, verbs return {@see Response}, {@see getLastResponse()} for debugging.
 *
 * @phpstan-consistent-constructor
 */
abstract class Requester
{
    /**
     * @var static|null
     */
    protected static $_instance;

    /**
     * @var bool
     */
    protected $debuglog = true;

    /**
     * @var \Monolog\Logger|null
     */
    protected $logger = null;

    /**
     * @var \GuzzleHttp\Client|null
     */
    protected $httpClient = null;

    /**
     * @var \Ufw\Component\ApiClient\Response|null
     */
    protected $lastResponse = null;

    protected function __construct()
    {
    }

    /**
     * Should return associative array to use in \GuzzleHttp\Client constructor
     * Only requiered key is base_uri
     */
    /**
     * @return array<string, mixed>
     */
    abstract protected function getApiEndpointConfig();

    /**
     * @return static
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
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function delete($url, $body = array(), $headers = array())
    {
        $ret = $this->doApiRequest('delete', $url, $body, $headers);
        $this->sleep();

        return $this->requireResponse($ret);
    }

    /**
     *
     * @param string $url
     * @param array<string, mixed> $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function get($url, $headers = array())
    {
        return $this->requireResponse($this->doApiRequest('get', $url, null, $headers));
    }

    /**
     *
     * @param string $url
     * @param array<string, mixed> $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function head($url, $headers = array())
    {
        return $this->requireResponse($this->doApiRequest('head', $url, null, $headers));
    }

    /**
     *
     * @param string $url
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function patch($url, $body = array(), $headers = array())
    {
        $ret = $this->doApiRequest('patch', $url, $body, $headers);
        $this->sleep();

        return $this->requireResponse($ret);
    }

    /**
     *
     * @param string $url
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function post($url, $body = array(), $headers = array())
    {
        $ret = $this->doApiRequest('post', $url, $body, $headers);
        $this->sleep();

        return $this->requireResponse($ret);
    }

    /**
     *
     * @param string $url
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function put($url, $body = array(), $headers = array())
    {
        $ret = $this->doApiRequest('put', $url, $body, $headers);
        $this->sleep();

        return $this->requireResponse($ret);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array<string, mixed> $options
     * @return \Ufw\Component\ApiClient\Response|null
     */
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

    /**
     * @return $this
     */
    protected function sleep()
    {
        return $this;
    }

    /**
     * @param \Exception $e
     * @return \Ufw\Component\ApiClient\Response
     */
    protected function createExceptionResponse(\Exception $e)
    {
        $response = new \Ufw\Component\ApiClient\Response();
        $this->setLastResponse($response->setException($e));
        return $response;
    }

    /**
     * @return \Ufw\Component\ApiClient\Response
     */
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
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $headers
     *
     * @return \Ufw\Component\ApiClient\Response|null
     */
    protected function doApiRequest($method, $url, $body, $headers)
    {
        try {
            $client = $this->getHttpClient();
            $response = $client->$method($url, array_filter(array(
                'json' => $body,
                'headers' => $headers + $this->getHeadersForRequest()
            ), function ($value) {
                return $value !== null;
            }));
            if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                $this->createResponse($response);
            }
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

    /**
     * @return array<string, string>
     */
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
        if ($this->httpClient === null) {
            $opts = $this->getApiEndpointConfig();
            
            if ($this->debuglog) {
                $app = $this->getApplication();
                $urlHelper = $app !== null ? $app->getHelper('url') : null;
                $logPrefix = '';
                if ($urlHelper instanceof \Ufw\Helper\Url && isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])) {
                    $https = $_SERVER['HTTPS'] ?? '';
                    $secure = is_string($https) && $https !== '' && strtolower($https) !== 'off';
                    $logPrefix = ($secure ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $urlHelper->url([], false, true) . "\n";
                }
                $opts['handler'] = $this->createLoggingHandlerStack([
                    $logPrefix . "{req_headers}\n{req_body}" . "\nRESPONSE:\n{res_headers}\n{res_body}\n\n"
                ]);
            }
            
            $this->httpClient = new \GuzzleHttp\Client($opts);
        }
        return $this->httpClient;
    }

    /**
     * @param list<string> $messageFormats
     * @return \GuzzleHttp\HandlerStack
     */
    protected function createLoggingHandlerStack(array $messageFormats)
    {
        $stack = \GuzzleHttp\HandlerStack::create();
        
        foreach ($messageFormats as $messageFormat) {
            $stack->unshift($this->createGuzzleLoggingMiddleware($messageFormat));
        }
        
        return $stack;
    }

    /**
     * @param string $messageFormat
     * @return callable
     */
    protected function createGuzzleLoggingMiddleware($messageFormat)
    {
        return \GuzzleHttp\Middleware::log($this->getLogger(), new \GuzzleHttp\MessageFormatter($messageFormat));
    }

    /**
     * @return string
     */
    protected function classPathSlashesForLogger()
    {
        $classPath = $this->pregReplaceClassPathSlashes(get_class($this));
        if ($classPath === null) {
            return 'api';
        }

        return $classPath;
    }

    /**
     * @param class-string $className
     * @return string|null
     */
    protected function pregReplaceClassPathSlashes($className)
    {
        return preg_replace('~\\\\~', '/', $className);
    }

    /**
     * @return \Monolog\Logger
     */
    protected function getLogger()
    {
        if (!$this->logger) {
            $classPath = $this->classPathSlashesForLogger();
            $handler = new \Monolog\Handler\RotatingFileHandler(\Ufw\Paths::application() . '/log/apiclient/' . basename(dirname($classPath)) . '.log');
            $formatter = new \Monolog\Formatter\LineFormatter('[%datetime%] %level_name%: %message%', null, true, true);
            $handler->setFormatter($formatter);
            $this->logger = (new \Monolog\Logger('api-consumer'))->pushHandler($handler);
            $this->logger->setTimezone(new \DateTimeZone('UTC'));
        }
        
        return $this->logger;
    }

    /**
     * @param \GuzzleHttp\Client $httpClient
     * @return $this
     */
    protected function setHttpClient(\GuzzleHttp\Client $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * @return \Ufw\Component\ApiClient\Response|null
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * @param \Ufw\Component\ApiClient\Response $lastResponse
     * @return $this
     */
    protected function setLastResponse(\Ufw\Component\ApiClient\Response $lastResponse)
    {
        $this->lastResponse = $lastResponse;
        return $this;
    }

    /**
     * @return \Ufw\Application|null
     */
    protected function getApplication()
    {
        $app = \Ufw\Registry::getInstance()->get('application');
        if ($app instanceof \Ufw\Application) {
            return $app;
        }

        return null;
    }

    /**
     * @param \Ufw\Component\ApiClient\Response|null $response
     * @return \Ufw\Component\ApiClient\Response
     */
    protected function requireResponse($response)
    {
        if ($response === null) {
            throw new \RuntimeException('API request did not produce a response');
        }

        return $response;
    }
}
