<?php
namespace Ufw\Component\ApiClient;

/**
 * Default {@see MagicTrait} + {@see \Ufw\Singleton} HTTP client: embeds {@see Requester} for Guzzle-backed calls.
 *
 * @method static \Ufw\Component\ApiClient\Client getInstance()
 *
 * @method \Ufw\Component\ApiClient\Response delete(string $url, array $body = array(), array $headers = array())
 * @method \Ufw\Component\ApiClient\Response get(string $url, array $headers = array())
 * @method \Ufw\Component\ApiClient\Response head(string $url, array $headers = array())
 * @method \Ufw\Component\ApiClient\Response patch(string $url, array $body = array(), array $headers = array())
 * @method \Ufw\Component\ApiClient\Response post(string $url, array $body = array(), array $headers = array())
 * @method \Ufw\Component\ApiClient\Response put(string $url, array $body = array(), array $headers = array())
 *        
 */
class Client
{
    use \Ufw\Singleton, \Ufw\Component\ApiClient\MagicTrait;

    public function getClient()
    {
        return $this;
    }
}