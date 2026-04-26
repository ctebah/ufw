<?php
namespace Ufw\Helper;

/**
 * Named slots for layout injection: resolves {@see \Ufw\Placeholder} instances from a shared container
 * (e.g. `jsReady`, `jsInline`). Invokable as `$this->placeholder('name')`.
 */
class Placeholder extends Base
{

    private static $container = null;

    public function __construct()
    {
        parent::__construct();
        if (!self::$container) {
            self::$container = new \Ufw\Container(\Ufw\Placeholder::class);
        }
    }

    public function __invoke($name)
    {
        return $this->placeholder($name);
    }

    public function placeholder($name)
    {
        return self::$container->get((string) $name);
    }
}