<?php
namespace Ufw;

/**
 * Runtime environment **label** (e.g. `development`, `production`) for feature gates - same idea as {@see Paths}:
 * one place resolves the string; callers use {@see runtimeLabel()}.
 *
 * Resolution order:
 *
 * 1. {@see setRuntimeEnvLabel()} - explicit override (tests, or bootstrap before config exists).
 * 2. **`env`** in {@see Application::getConfig()} when **`application`** is in {@see Registry} - typical when `config/app.php` sets it.
 * 3. Registry key **`env`** (non-empty string) - optional manual mirror at bootstrap.
 * 4. PHP constant **`APPLICATION_ENV`** - legacy `define()` after autoload.
 * 5. **`production`** - last-resort default so helpers never see an empty label.
 */
class Envs
{
    /**
     * @var string|null
     */
    private static $runtimeEnvOverride = null;

    /**
     * Force the env label for this process. Pass `null` or `''` to clear and fall back to config / Registry / constant.
     */
    public static function setRuntimeEnvLabel(?string $label): void
    {
        self::$runtimeEnvOverride = $label;
    }

    public static function runtimeLabel(): string
    {
        if (self::$runtimeEnvOverride !== null && self::$runtimeEnvOverride !== '') {
            return self::$runtimeEnvOverride;
        }

        $registry = Registry::getInstance();
        if ($registry->has('application')) {
            $application = $registry->get('application');
            if ($application instanceof Application) {
                $cfg = $application->getConfig();
                if (isset($cfg['env']) && $cfg['env'] !== '') {
                    return (string) $cfg['env'];
                }
            }
        }

        if ($registry->has('env')) {
            $fromRegistry = $registry->get('env');
            if (\is_string($fromRegistry) && $fromRegistry !== '') {
                return $fromRegistry;
            }
        }

        if (\defined('APPLICATION_ENV')) {
            return (string) \constant('APPLICATION_ENV');
        }

        return 'production';
    }
}
