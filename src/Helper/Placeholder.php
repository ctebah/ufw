<?php
namespace Ufw\Helper;

/**
 * Named slots for layout injection: resolves {@see \Ufw\Placeholder} instances from a shared container
 * (e.g. `jsReady`, `jsInline`). Invokable as `$this->placeholder('name')`.
 */
class Placeholder extends Base
{

    /**
     * @var \Ufw\Container<\Ufw\Placeholder>|null
     */
    private static $container = null;

    public function __construct()
    {
        parent::__construct();
        if (!self::$container) {
            self::$container = new \Ufw\Container(\Ufw\Placeholder::class);
        }
    }

    /**
     * @param string $name
     * @return \Ufw\Placeholder
     */
    public function __invoke($name)
    {
        return $this->placeholder($name);
    }

    /**
     * @param string $name
     * @return \Ufw\Placeholder
     */
    public function placeholder($name)
    {
        if (self::$container === null) {
            self::$container = new \Ufw\Container(\Ufw\Placeholder::class);
        }
        return self::$container->get((string) $name);
    }
}
