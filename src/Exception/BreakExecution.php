<?php
namespace Ufw\Exception;

/**
 * Control-flow exception used to stop dispatch early (e.g. after sending a redirect) while optionally
 * setting the HTTP status code on the current {@see \Ufw\Response} when the code is non-zero.
 */
class BreakExecution extends \Exception
{

    public function __construct($message = null, $code = null, $previous = null)
    {
        parent::__construct((string) ($message ?? ''), (int) ($code ?? 0), $previous);
        if ($code) {
            $this->getApplication()
                ->getResponse()
                ->withStatus($code, $message);
        }
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