<?php
namespace Ufw;

/**
 * Application filesystem root and related resolved paths. Callers use {@see application()}; the value is resolved in order:
 *
 * 1. {@see setApplicationPath()} - explicit override (tests, or bootstrap before any read).
 * 2. Registry key **`application_path`** (non-empty string) - set e.g. in `index.php` before `new Application()`.
 * 3. PHP constant **`APPLICATION_PATH`** - classic `define()` after autoload.
 *
 * {@see exceptionLogFilePath()} resolves `logging.exception_log_path` from the same app root (used for exception file logging).
 *
 * Subclassing `Paths` does not change behaviour for code that calls `\Ufw\Paths::application()`; use one of
 * the mechanisms above instead of replacing the class.
 */
class Paths
{
    /**
     * @var string|null
     */
    private static $applicationPathOverride = null;

    /**
     * Force the application root path for this process. Pass `null` to clear and fall back to
     * Registry / `APPLICATION_PATH`.
     */
    public static function setApplicationPath(?string $path): void
    {
        self::$applicationPathOverride = $path;
    }

    /**
     * @throws \RuntimeException if the path cannot be resolved
     */
    public static function application(): string
    {
        if (self::$applicationPathOverride !== null && self::$applicationPathOverride !== '') {
            return self::$applicationPathOverride;
        }

        $registry = Registry::getInstance();
        if ($registry->has('application_path')) {
            $fromRegistry = $registry->get('application_path');
            if (\is_string($fromRegistry) && $fromRegistry !== '') {
                return $fromRegistry;
            }
        }

        if (\defined('APPLICATION_PATH')) {
            return (string) \constant('APPLICATION_PATH');
        }

        throw new \RuntimeException(
            'Application path is not set. Use Paths::setApplicationPath(), Registry::set(\'application_path\', ...), or define APPLICATION_PATH before loading UFW.'
        );
    }

    /**
     * Absolute path for file-based exception logging, or null when disabled.
     *
     * Reads <code>$appConfig['logging']['exception_log_path']</code> (same shape as <code>config/app.php</code>):
     *
     * - Key omitted or <code>$appConfig</code> not an array → <code>application() . '/log/exceptions.txt'</code>.
     * - <code>false</code> or empty string (after trim) → null (no file log).
     * - Absolute path → used as-is (Unix <code>/…</code>, Windows drive or UNC).
     * - Relative path → under {@see application()}.
     *
     * @param array|null $appConfig Full application config array, or null when unavailable.
     * @return string|null
     */
    public static function exceptionLogFilePath($appConfig = null)
    {
        $default = self::application() . '/log/exceptions.txt';

        if (!\is_array($appConfig)) {
            return $default;
        }

        $logging = $appConfig['logging'] ?? null;
        if (!\is_array($logging) || !\array_key_exists('exception_log_path', $logging)) {
            return $default;
        }

        $raw = $logging['exception_log_path'];
        if ($raw === false) {
            return null;
        }
        if (!\is_string($raw)) {
            return $default;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (self::isAbsoluteFilesystemPath($raw)) {
            return $raw;
        }

        $rel = ltrim(str_replace('\\', '/', $raw), '/');

        return self::application() . '/' . $rel;
    }

    /**
     * Whether <code>$path</code> is treated as absolute for {@see exceptionLogFilePath()}.
     */
    private static function isAbsoluteFilesystemPath(string $path): bool
    {
        if ($path !== '' && $path[0] === '/') {
            return true;
        }
        if (strlen($path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/')) {
            return true;
        }
        if (strlen($path) >= 2 && $path[0] === '\\' && $path[1] === '\\') {
            return true;
        }

        return false;
    }
}
