<?php
namespace Ufw\Middleware;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Wraps response content in the configured HTML layout, or in `views/layout/_bare.ajax.php` for AJAX-style responses
 * when the controller did not disable layout but HTML layout is not used.
 */
class Layout extends Base
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        if (!$this->getController()->getDisableLayout() && $this->getResponse()->mayHaveHtmlLayout()) {
            $this->getController()->render([
                'content' => $this->getResponse()
                    ->getContent()
            ], 'layout/' . $this->getController()
                ->getLayout() . '.php', false);
        } elseif ($this->getResponse()->shouldRenderViewScript()) {
            $this->getController()->render([
                'content' => $this->getResponse()
                    ->getContent()
            ], 'layout/_bare.ajax.php', false);
        }
        return $handler->handle($request);
    }
}