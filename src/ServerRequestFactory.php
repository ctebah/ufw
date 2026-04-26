<?php
declare(strict_types=1);

namespace Ufw;

use InvalidArgumentException;
use Laminas\Diactoros\ServerRequestFilter\FilterServerRequestInterface;
use Laminas\Diactoros\ServerRequestFilter\FilterUsingXForwardedHeaders;

/**
 * Builds {@see \Ufw\ServerRequest} from globals (and optional overrides), extending Laminas defaults with UFW request shaping.
 */
class ServerRequestFactory extends \Laminas\Diactoros\ServerRequestFactory 
{

    /**
     * Function to use to get apache request headers; present only to simplify mocking.
     *
     * @var callable
     */
    protected static $apacheRequestHeaders = 'apache_request_headers';

    /**
     * Create a request from the supplied superglobal values.
     *
     * If any argument is not supplied, the corresponding superglobal value will
     * be used.
     *
     * The ServerRequest created is then passed to the fromServer() method in
     * order to marshal the request URI and headers.
     *
     * @see fromServer()
     *
     * @param array $server $_SERVER superglobal
     * @param array $query $_GET superglobal
     * @param array $body $_POST superglobal
     * @param array $cookies $_COOKIE superglobal
     * @param array $files $_FILES superglobal
     * @param null|FilterServerRequestInterface $requestFilter If present, the
     *     generated request will be passed to this instance and the result
     *     returned by this method. When not present, a default instance of
     *     FilterUsingXForwardedHeaders is created, using the `trustReservedSubnets()`
     *     constructor.
     * @param array $attributes
     * @return ServerRequest
     * @throws InvalidArgumentException for invalid file values
     */
    public static function fromGlobals(
        array $server = null, 
        array $query = null, 
        array $body = null, 
        array $cookies = null, 
        array $files = null, 
        ?FilterServerRequestInterface $requestFilter = null,
        array $attributes = []
    ): ServerRequest {
        $requestFilter = $requestFilter ?: FilterUsingXForwardedHeaders::trustReservedSubnets();

        $server  = \Laminas\Diactoros\normalizeServer(
            $server ?: $_SERVER,
            is_callable(self::$apacheRequestHeaders) ? self::$apacheRequestHeaders : null
        );
        $files   = \Laminas\Diactoros\normalizeUploadedFiles($files ?: $_FILES);
        $headers = HttpHeaderSanitizer::sanitizeHeadersArray(
            \Laminas\Diactoros\marshalHeadersFromSapi($server)
        );
        
        if (null === $cookies && array_key_exists('cookie', $headers)) {
            $cookies = \Laminas\Diactoros\parseCookieHeader($headers['cookie']);
        }
        
        return new ServerRequest(
            $server, 
            $files, 
            \Laminas\Diactoros\marshalUriFromSapi($server, $headers), 
            \Laminas\Diactoros\marshalMethodFromSapi($server),
            'php://input', 
            $headers, 
            $cookies ?: $_COOKIE, 
            $query ?: $_GET, 
            $body ?: $_POST, 
            \Laminas\Diactoros\marshalProtocolVersionFromSapi($server), 
            $attributes ?: []
        );
    }
}