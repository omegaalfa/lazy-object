<?php

declare(strict_types=1);

namespace Omegaalfa\LazyObject;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use stdClass;

final class LazyObject
{
    /** @codeCoverageIgnore */
    private function __construct() {}

    /**
     * @template T of object
     * @param class-string<T>|T $class
     * @param Closure(T): T $factory
     * @return T
     * @throws InvalidArgumentException
     */
    public static function lazyProxy(string|object $class, Closure $factory): object
    {
        $reflection = self::reflect($class);
        /** @var T */
        return $reflection->newLazyProxy(static function (object $proxy) use ($factory, $reflection): object {
            /** @var T $proxy */
            $instance = $factory($proxy);
            $className = $reflection->getName();
            if (!$instance instanceof $className) {
                throw new InvalidArgumentException(sprintf('Factory must return an instance of "%s", "%s" given.', $className, get_debug_type($instance)));
            }
            return $instance;
        });
    }

    /**
     * @template T of object
     * @param class-string<T>|T $class
     * @param Closure(T): void $initializer
     * @return T
     * @throws InvalidArgumentException
     */
    public static function lazyGhost(string|object $class, Closure $initializer): object
    {
        $reflection = self::reflect($class);
        /** @var T */
        return $reflection->newLazyGhost($initializer);
    }

    /**
     * @template T of object
     * @param class-string<T>|T $class
     * @return ReflectionClass<T>
     */
    private static function reflect(string|object $class): ReflectionClass
    {
        try {
            /** @var ReflectionClass<T> $reflection */
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $exception) { // @phpstan-ignore catch.neverThrown
            throw new InvalidArgumentException(sprintf('Invalid class "%s".', is_string($class) ? $class : $class::class), 0, $exception);
        }
        if ($reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum() || $reflection->isAbstract()) {
            throw new InvalidArgumentException(sprintf('Class "%s" cannot be made lazy.', $reflection->getName()));
        }
        if ($reflection->isInternal() && $reflection->getName() !== stdClass::class) {
            throw new InvalidArgumentException(sprintf('Internal class "%s" cannot be made lazy.', $reflection->getName()));
        }
        return $reflection;
    }
}
