<?php
namespace Ufw\Tests\Support\ApiClient;

/**
 * Concrete sibling {@see Requester} for {@see Client} — mirrors app convention
 * `{ClientNamespace}\Requester` so {@see \Ufw\Component\ApiClient\Client::resolveRequester()} can succeed in tests.
 */
class Requester extends \Ufw\Component\ApiClient\Requester
{
    public static function resetForTests(): void
    {
        self::$_instance = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getApiEndpointConfig()
    {
        return array('base_uri' => 'http://api.test');
    }
}
