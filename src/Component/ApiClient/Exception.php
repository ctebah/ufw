<?php
namespace Ufw\Component\ApiClient;

/**
 * API client failure: carries one or more human-readable messages (often derived from Guzzle) and may
 * attach the {@see Response} wrapper for inspection.
 */
class Exception extends \Exception
{

    protected $messages;

    /**
     *
     * @var \Ufw\Component\ApiClient\Response
     */
    protected $response;

    public function __construct($msg, $code = null, $previous = null)
    {
        if (!$msg && $previous) {
            if ($previous instanceof \GuzzleHttp\Exception\ServerException) {
                $msg = $previous->getResponse()->getReasonPhrase();
            } else {
                $msg = $previous->getMessage();
                if (preg_match('~<[^>]+>~', $msg)) {
                    $msg = strip_tags($msg);
                }
                if (strlen($msg) > 2048) {
                    $msg = $previous->getResponse()->getReasonPhrase();
                }
            }
        }
        if (!$code && $previous) {
            $code = $previous->getCode();
        }
        if (!is_array($msg))
            $msg = array(
                $msg
            );
        $firstMsg = reset($msg);
        parent::__construct($firstMsg, (int) ($code ?? 0), $previous);
        $this->setMessages($msg);
    }

    public function getMessages()
    {
        return $this->messages;
    }

    protected function setMessages(array $messages = NULL)
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     *
     * @param \Ufw\Component\ApiClient\Response $response
     */
    public function setResponse(\Ufw\Component\ApiClient\Response $response)
    {
        $this->response = $response;
        return $this;
    }
}