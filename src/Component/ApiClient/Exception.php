<?php
namespace Ufw\Component\ApiClient;

/**
 * API client failure: carries one or more human-readable messages (often derived from Guzzle) and may
 * attach the {@see Response} wrapper for inspection.
 */
class Exception extends \Exception
{

    /**
     * @var list<string>|null
     */
    protected $messages;

    /**
     * @var \Ufw\Component\ApiClient\Response|null
     */
    protected $response;

    /**
     * @param string|list<string>|null $msg
     * @param int|null $code
     * @param \Throwable|null $previous
     */
    public function __construct($msg, $code = null, $previous = null)
    {
        if (!$msg && $previous) {
            if ($previous instanceof \GuzzleHttp\Exception\ServerException) {
                $msg = $previous->getResponse()->getReasonPhrase();
            } elseif ($previous instanceof \GuzzleHttp\Exception\RequestException && $previous->hasResponse()) {
                $httpResponse = $previous->getResponse();
                $msg = $httpResponse !== null ? $httpResponse->getReasonPhrase() : $previous->getMessage();
            } else {
                $msg = $previous->getMessage();
                if (preg_match('~<[^>]+>~', $msg)) {
                    $msg = strip_tags($msg);
                }
                if (strlen($msg) > 2048 && $previous instanceof \GuzzleHttp\Exception\RequestException && $previous->hasResponse()) {
                    $httpResponse = $previous->getResponse();
                    if ($httpResponse !== null) {
                        $msg = $httpResponse->getReasonPhrase();
                    }
                }
            }
        }
        if (!$code && $previous) {
            $code = $previous->getCode();
        }
        if (!is_array($msg)) {
            $msg = array(
                (string) $msg
            );
        }
        $firstMsg = reset($msg);
        parent::__construct((string) $firstMsg, (int) ($code ?? 0), $previous);
        $this->setMessages($msg);
    }

    /**
     * @return list<string>|null
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param list<string>|null $messages
     * @return $this
     */
    protected function setMessages(array $messages = null)
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * @return \Ufw\Component\ApiClient\Response|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param \Ufw\Component\ApiClient\Response $response
     * @return $this
     */
    public function setResponse(\Ufw\Component\ApiClient\Response $response)
    {
        $this->response = $response;
        return $this;
    }
}
