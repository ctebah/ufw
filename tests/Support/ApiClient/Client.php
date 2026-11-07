<?php
namespace Ufw\Tests\Support\ApiClient;

/**
 * {@see \Ufw\Component\ApiClient\Client} in a namespace with a concrete {@see Requester} sibling.
 */
class Client extends \Ufw\Component\ApiClient\Client
{
    public static function resetForTests(): void
    {
        $prop = new \ReflectionProperty(\Ufw\Component\ApiClient\Client::class, '_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
    }
}
