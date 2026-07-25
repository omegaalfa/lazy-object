<?php

declare(strict_types=1);

namespace Omegaalfa\LazyObject\Tests;

use Error;
use InvalidArgumentException;
use Omegaalfa\LazyObject\LazyObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use stdClass;

interface Contract {}
trait ATestTrait {}
enum ATestEnum { case One; }
abstract class AbstractService {}
class Service { public function __construct(public string $value = '') {} }
final class PrivateConstructor { private function __construct(public string $value = '') {} }
final class EmptyService {}

final class LazyObjectTest extends TestCase
{
    public function testProxyIsDeferredReceivesProxyAndRunsOnce(): void
    {
        $calls = 0; $received = null;
        $proxy = LazyObject::lazyProxy(Service::class, static function (Service $object) use (&$calls, &$received): Service {
            ++$calls; $received = $object; return new Service('ready');
        });
        self::assertSame(0, $calls);
        self::assertSame('ready', $proxy->value);
        self::assertSame($proxy, $received);
        self::assertSame('ready', $proxy->value);
        self::assertSame(1, $calls);
    }

    #[DataProvider('invalidResults')]
    public function testProxyRejectsNonObject(mixed $result, string $type): void
    {
        $proxy = LazyObject::lazyProxy(Service::class, static fn (Service $proxy): mixed => $result);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Factory must return an instance of "%s", "%s" given.', Service::class, $type));
        $proxy->value;
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidResults(): iterable
    {
        yield 'null' => [null, 'null']; yield 'string' => ['x', 'string']; yield 'int' => [1, 'int']; yield 'array' => [[], 'array'];
    }

    public function testProxyPreservesFactoryExceptionAndRejectsIncompatibleObject(): void
    {
        $expected = new RuntimeException('failed');
        $proxy = LazyObject::lazyProxy(Service::class, static function (Service $proxy) use ($expected): never { throw $expected; });
        try { $proxy->value; self::fail('Expected exception'); } catch (RuntimeException $actual) { self::assertSame($expected, $actual); }

        $bad = LazyObject::lazyProxy(Service::class, static fn (Service $proxy): stdClass => new stdClass());
        $this->expectException(InvalidArgumentException::class);
        $bad->value;
    }

    public function testGhostInitializesSameObjectOnceAndCanRetryAfterFailure(): void
    {
        $calls = 0; $received = null;
        $ghost = LazyObject::lazyGhost(Service::class, static function (Service $object) use (&$calls, &$received): void {
            $received = $object; if (++$calls === 1) { throw new RuntimeException('retry'); } $object->__construct('ready');
        });
        try { $ghost->value; self::fail('Expected exception'); } catch (RuntimeException $e) { self::assertSame('retry', $e->getMessage()); }
        self::assertSame('ready', $ghost->value);
        self::assertSame($ghost, $received);
        self::assertSame(2, $calls);
    }

    public function testSeparateObjectsHaveIndependentState(): void
    {
        $a = LazyObject::lazyGhost(Service::class, static fn (Service $o): mixed => $o->__construct('a'));
        $b = LazyObject::lazyGhost(Service::class, static fn (Service $o): mixed => $o->__construct('b'));
        self::assertSame('a', $a->value); self::assertSame('b', $b->value);
    }

    public function testGhostRejectsNonNullReturn(): void
    {
        $ghost = LazyObject::lazyGhost(Service::class, static function (Service $o): Service { $o->__construct('kept'); return new Service('ignored'); });
        $this->expectException(\TypeError::class);
        $ghost->value;
    }

    #[DataProvider('invalidClasses')]
    public function testRejectsUnsupportedClass(string $class, string $message): void
    {
        $this->expectException(InvalidArgumentException::class); $this->expectExceptionMessage($message);
        LazyObject::lazyGhost($class, static function (object $o): void {});
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function invalidClasses(): iterable
    {
        foreach ([Contract::class, ATestTrait::class, ATestEnum::class, AbstractService::class] as $class) { yield $class => [$class, sprintf('Class "%s" cannot be made lazy.', $class)]; }
        yield 'internal' => [ReflectionClass::class, sprintf('Internal class "%s" cannot be made lazy.', ReflectionClass::class)];
    }

    public function testUnknownClassPreservesPreviousException(): void
    {
        try { LazyObject::lazyProxy('Missing\\ClassName', static fn (object $o): object => $o); self::fail('Accepted missing class'); }
        catch (InvalidArgumentException $e) { self::assertSame('Invalid class "Missing\\ClassName".', $e->getMessage()); self::assertInstanceOf(ReflectionException::class, $e->getPrevious()); }
    }

    public function testAcceptsObjectPrivateConstructorStdClassAndEmptyClass(): void
    {
        $proxy = LazyObject::lazyProxy(new Service(), static fn (Service $o): Service => new Service('object'));
        $private = LazyObject::lazyGhost(PrivateConstructor::class, static function (PrivateConstructor $o): void { $o->value = 'private'; });
        $std = LazyObject::lazyGhost(stdClass::class, static function (stdClass $o): void {});
        $empty = LazyObject::lazyGhost(EmptyService::class, static function (EmptyService $o): void {});
        self::assertSame('object', $proxy->value); self::assertSame('private', $private->value); self::assertInstanceOf(stdClass::class, $std);
        self::assertFalse((new ReflectionClass(EmptyService::class))->isUninitializedLazyObject($empty));
    }

    public function testCloneAndSerializationTriggerInitialization(): void
    {
        $cloneCalls = 0; $source = LazyObject::lazyGhost(Service::class, static function (Service $o) use (&$cloneCalls): void { ++$cloneCalls; $o->__construct('clone'); });
        $clone = clone $source;
        $serializeCalls = 0; $serializedSource = LazyObject::lazyGhost(Service::class, static function (Service $o) use (&$serializeCalls): void { ++$serializeCalls; $o->__construct('serialized'); });
        $serialized = serialize($serializedSource);
        self::assertSame(1, $cloneCalls); self::assertSame('clone', $clone->value); self::assertSame(1, $serializeCalls); self::assertStringContainsString('serialized', $serialized);
    }
}
