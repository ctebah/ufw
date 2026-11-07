<?php
namespace Ufw\Tests\Support;

/**
 * Cross-platform temp dirs/files for tests (sys_get_temp_dir + DIRECTORY_SEPARATOR).
 */
final class TempFiles
{
    /**
     * @return string Absolute directory under the system temp folder.
     */
    public static function uniqueDir($prefix = 'ufw-test-')
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(4));
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    /**
     * @return string Absolute path to a new file under the system temp folder (file need not exist yet).
     */
    public static function uniqueFilePath($prefix = 'ufw-test-', $suffix = '.tmp')
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(4)) . $suffix;
    }

    /**
     * @param string $dir
     */
    public static function removeTree($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                self::removeTree($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
