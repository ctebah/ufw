<?php
namespace Ufw\Tests\Support;

/**
 * Paths to minimal UFW apps under {@see tests/test-apps/} used by unit and component tests.
 */
final class TestAppPaths
{
    /**
     * @return string
     */
    public static function root()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'test-apps';
    }

    /**
     * Default fixture app (forms, views, dbconns for Docker/host component tests).
     *
     * @return string
     */
    public static function app()
    {
        return self::root() . DIRECTORY_SEPARATOR . 'fixture-app';
    }

    /**
     * @return string
     */
    public static function throwApp()
    {
        return self::root() . DIRECTORY_SEPARATOR . 'fixture-throw-app';
    }

    /**
     * @return string
     */
    public static function silentApp()
    {
        return self::root() . DIRECTORY_SEPARATOR . 'fixture-silent-app';
    }

    /**
     * @return string
     */
    public static function logApp()
    {
        return self::root() . DIRECTORY_SEPARATOR . 'fixture-log-app';
    }

    /**
     * @return string
     */
    public static function badApp()
    {
        return self::root() . DIRECTORY_SEPARATOR . 'fixture-bad-app';
    }

    /**
     * @return string
     */
    public static function dbconnsConfigPath()
    {
        return self::app() . '/config/dbconns.php';
    }
}
