<?php

namespace Ufw\Component;

/**
 * Session-backed synchronizer tokens for CSRF protection on state-changing requests (typically POST forms).
 *
 * Each <strong>purpose</strong> string names an independent token (e.g. <code>contact</code>, <code>admin_login</code>).
 * Use {@see self::DEFAULT_FIELD_NAME} as the HTML input name unless you have a reason to customize it.
 *
 * Subclass to customize behaviour (e.g. override {@see self::SESSION_PREFIX} or static methods).
 */
class Csrf
{
    public const DEFAULT_FIELD_NAME = 'csrf_token';

    protected const SESSION_PREFIX = 'ufw.csrf.';

    public static function getDefaultFieldName(): string
    {
        return static::DEFAULT_FIELD_NAME;
    }

    /**
     * Returns the current token for this purpose, creating one in the session if needed.
     */
    public static function token(string $purpose = 'default'): string
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        $key = static::SESSION_PREFIX . $purpose;
        if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }

    /**
     * Whether the submitted value matches the session token for this purpose.
     */
    public static function validate(string $purpose, $submitted): bool
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        $key = static::SESSION_PREFIX . $purpose;
        if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            return false;
        }
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }

        return hash_equals($_SESSION[$key], $submitted);
    }

    /**
     * Issue a new token after a successful state-changing request so the old token cannot be replayed.
     */
    public static function rotate(string $purpose): void
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[static::SESSION_PREFIX . $purpose] = bin2hex(random_bytes(32));
    }
}
