<?php
namespace Ufw\Middleware;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use InvalidArgumentException;

/**
 * When {@see ServerRequestInterface::getHeader()} `Content-Type` is `application/json`, decodes the raw body with
 * {@see \Ufw\Middleware\Base::getPhpInput()} and attempts to attach it as the parsed body.
 *
 * @throws InvalidArgumentException When JSON decoding fails.
 */
class JsonRequest extends Base
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        @list ($cth) = $request->getHeader('content-type');
        if ($cth && !strcmp($cth, 'application/json')) {
            $json = json_decode($this->getPhpInput(), true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new InvalidArgumentException(sprintf('Unable to decode JSON - %s', json_last_error_msg()));
            }
            if (is_array($json)) {
                $request = $request->withParsedBody($json);
            }
        }
        return $handler->handle($request);
    }
}