<?php

declare(strict_types=1);


namespace Omegaalfa\LazyObject;


use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

class LazyObject
{

    /**
     * @param string|object $class
     * @param Closure $factory
     * @return object
     * @throws InvalidArgumentException
     */
    public static function lazyProxy(string|object $class, Closure $factory): object
    {
        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException(
                sprintf('Invalid class "%s".', is_string($class) ? $class : get_class($class)),
                0,
                $e
            );
        }

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Class "%s" is not instantiable.',
                    is_string($class) ? $class : get_class($class)
                )
            );
        }

        return $reflection->newLazyProxy(function() use ($factory, $reflection) {
            $instance = $factory();
            $className = $reflection->getName();

            if (!$instance instanceof $className) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Factory must return instance of "%s", "%s" given.',
                        $className,
                        get_debug_type($instance)
                    )
                );
            }

            return $instance;
        });
    }

    /**
     * @param string|object $class
     * @param Closure $factory
     * @return object
     * @throws InvalidArgumentException
     */
    public static function lazyGhost(string|object $class, Closure $factory): object
    {
        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException(
                sprintf('Invalid class "%s".', is_string($class) ? $class : get_class($class)),
                0,
                $e
            );
        }

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Class "%s" is not instantiable.',
                    is_string($class) ? $class : get_class($class)
                )
            );
        }

        return $reflection->newLazyGhost($factory);
    }
}