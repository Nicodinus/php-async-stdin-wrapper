<?php


namespace Nicodinus\PhpAsync\StdinWrapper;


use ReflectionClass;
use function shell_exec;
use function strtoupper;
use function substr;
use const PHP_OS;

/**
 * Class Utils
 *
 * @package Nicodinus\PhpAsync\StdinWrapper
 */
final class Utils
{
    /**
     * @return bool
     */
    public static function isWindowsOS(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * @param int $pid
     *
     * @return void
     */
    public static function killProcess(int $pid): void
    {
        if (static::isWindowsOS()) {
            @shell_exec("taskkill /F /PID {$pid}");
        } else {
            @shell_exec("kill -9 {$pid}");
        }
    }

    /**
     * @return string
     */
    public static function getComposerVendorPath(): string
    {
        $reflection = new ReflectionClass(\Composer\Autoload\ClassLoader::class);

        return dirname($reflection->getFileName(), 2);
    }
}