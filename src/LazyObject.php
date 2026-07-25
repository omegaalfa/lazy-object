<?php

declare(strict_types=1);

namespace Omegaalfa\LazyObject;

use Closure;
use Error;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

final class LazyObject
{
    /**
     * Cache of structurally validated reflection metadata.
     *
     * A cached reflection does not guarantee that every lazy-object rule is
     * satisfied. The PHP engine still validates factories, inheritance and
     * options on each newLazyProxy() or newLazyGhost() call.
     *
     * @var array<class-string, ReflectionClass<object>>
     */
    private static array $reflectionCache = [];

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }

    /**
     * Creates a PHP native lazy proxy.
     *
     * @template T of object
     * @param class-string<T>|T $class
     * @param Closure(T): object $factory
     * @return T
     * @throws InvalidArgumentException If the class cannot be reflected or is structurally unsupported.
     * @throws Error If the PHP engine rejects the proxy class or factory result.
     * @throws ReflectionException If the PHP engine rejects the supplied options.
     */
    public static function proxy(
        string|object $class,
        Closure $factory,
        int $options = 0,
    ): object {
        $reflection = self::reflect($class);

        /** @var T */
        // PHPStan's stub is stricter than PHP 8.4, which accepts object here.
        /** @phpstan-ignore-next-line argument.type */
        return $reflection->newLazyProxy($factory, $options);
    }

    /**
     * Creates a PHP native lazy ghost.
     *
     * @template T of object
     * @param class-string<T>|T $class
     * @param Closure(T): void $initializer
     * @return T
     * @throws InvalidArgumentException If the class cannot be reflected or is structurally unsupported.
     * @throws Error If the PHP engine rejects the ghost class.
     * @throws ReflectionException If the PHP engine rejects the supplied options.
     */
    public static function ghost(
        string|object $class,
        Closure $initializer,
        int $options = 0,
    ): object {
        $reflection = self::reflect($class);

        /** @var T */
        return $reflection->newLazyGhost($initializer, $options);
    }

    /**
     * @template T of object
     * @param class-string<T>|T $class
     * @return ReflectionClass<T>
     * @throws InvalidArgumentException If the class cannot be reflected or is structurally unsupported.
     */
    private static function reflect(string|object $class): ReflectionClass
    {
        $className = is_string($class) ? $class : $class::class;

        if (isset(self::$reflectionCache[$className])) {
            /** @var ReflectionClass<T> */
            return self::$reflectionCache[$className];
        }

        try {
            /** @var ReflectionClass<T> $reflection */
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $exception) { // @phpstan-ignore catch.neverThrown
            throw new InvalidArgumentException(
                sprintf('Invalid class "%s".', $className),
                previous: $exception,
            );
        }

        if ($reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum() || $reflection->isAbstract()) {
            throw new InvalidArgumentException(sprintf('Class "%s" cannot be made lazy.', $reflection->getName()));
        }

        // Complex lazy-object compatibility rules are validated by the PHP engine.
        /** @var ReflectionClass<object> $reflection */
        self::$reflectionCache[$className] = $reflection;

        /** @var ReflectionClass<T> */
        return $reflection;
    }
}
