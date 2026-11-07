<?php
namespace Ufw\Component\ApiClient;

/**
 * Default {@see MagicTrait} + {@see \Ufw\Singleton} HTTP client: embeds {@see Requester} for Guzzle-backed calls.
 *
 * @method static \Ufw\Component\ApiClient\Client getInstance()
 *
 * @method \Ufw\Component\ApiClient\Response delete(string $url, array<string, mixed> $body = array(), array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response get(string $url, array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response head(string $url, array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response patch(string $url, array<string, mixed> $body = array(), array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response post(string $url, array<string, mixed> $body = array(), array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response put(string $url, array<string, mixed> $body = array(), array<string, mixed> $headers = array())
 */
class Client
{
    use \Ufw\Singleton, \Ufw\Component\ApiClient\MagicTrait;

    /**
     * @var \Ufw\Component\ApiClient\Requester|null
     */
    protected $requester;

    /**
     * @return $this
     */
    public function getClient()
    {
        return $this;
    }

    /**
     * Resolves `{AppNamespace}\Requester` (same convention as magic {@see MagicTrait::__call()} `getRequester`).
     *
     * @return \Ufw\Component\ApiClient\Requester
     */
    protected function resolveRequester()
    {
        $className = (new \ReflectionClass(static::class))->getNamespaceName() . '\\Requester';
        if (!class_exists($className) || !is_subclass_of($className, Requester::class)) {
            throw new \RuntimeException(sprintf(
                'ApiClient Requester class %s not found for %s.',
                $className,
                static::class
            ));
        }
        /** @var class-string<Requester> $className */
        return $className::getInstance();
    }

    /**
     * @return \Ufw\Component\ApiClient\Requester
     */
    public function getRequester()
    {
        if ($this->requester === null) {
            $this->requester = $this->resolveRequester();
        }

        return $this->requester;
    }

    /**
     * @param \Ufw\Component\ApiClient\Requester $requester
     * @return $this
     */
    public function setRequester(\Ufw\Component\ApiClient\Requester $requester)
    {
        $this->requester = $requester;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRaiseExceptionOnApiError()
    {
        return true;
    }
}
