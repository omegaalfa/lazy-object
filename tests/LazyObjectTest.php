<?php

declare(strict_types=1);

namespace Omegaalfa\LazyObject\Tests;

use ArrayObject;
use Error;
use InvalidArgumentException;
use Omegaalfa\LazyObject\LazyObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use stdClass;

interface TestContract {}
trait TestTrait {}
enum TestEnum { case Value; }
abstract class AbstractFixture {}

class ServiceFixture
{
    public function __construct(public string $value = 'default') {}
    public function value(): string { return $this->value; }
}

class CompatibleChild extends ServiceFixture {}
class ChildWithProperty extends ServiceFixture { public int $extra = 1; }
final class InternalChild extends ArrayObject {}
final class StatelessFixture { public function ping(): string { return 'pong'; } }

final class LazyObjectTest extends TestCase
{
    public function testProxyFromClassNameIsDeferredReceivesProxyAndRunsOnce(): void
    {
        $calls = 0;
        $received = null;
        $proxy = LazyObject::proxy(ServiceFixture::class, static function (ServiceFixture $object) use (&$calls, &$received): object {
            ++$calls;
            $received = $object;
            return new ServiceFixture('initialized');
        });

        self::assertInstanceOf(ServiceFixture::class, $proxy);
        self::assertSame(0, $calls);
        self::assertSame('initialized', $proxy->value);
        self::assertSame($proxy, $received);
        self::assertSame('initialized', $proxy->value());
        self::assertSame(1, $calls);
    }

    public function testProxyAcceptsObjectAsTypeAndSupportsPropertyWrite(): void
    {
        $type = new ServiceFixture('type only');
        $proxy = LazyObject::proxy($type, static fn (ServiceFixture $object): object => new ServiceFixture('real'));
        $proxy->value = 'changed';

        self::assertNotSame($type, $proxy);
        self::assertSame('changed', $proxy->value);
    }

    public function testProxyAcceptsCompatibleSuperclassResult(): void
    {
        $proxy = LazyObject::proxy(CompatibleChild::class, static fn (CompatibleChild $object): object => new ServiceFixture('parent'));

        self::assertSame('parent', $proxy->value);
        self::assertInstanceOf(CompatibleChild::class, $proxy);
    }

    public function testEngineRejectsSubclassResultForParentProxy(): void
    {
        $proxy = LazyObject::proxy(ServiceFixture::class, static fn (ServiceFixture $object): object => new CompatibleChild('child'));

        $this->expectException(Error::class);
        $proxy->value;
    }

    public function testEngineRejectsIncompatibleFactoryAndProxyItself(): void
    {
        $incompatible = LazyObject::proxy(ServiceFixture::class, static fn (ServiceFixture $object): object => new stdClass());
        try {
            $incompatible->value;
            self::fail('Incompatible factory was accepted.');
        } catch (Error $error) {
            self::assertStringContainsString('must be', $error->getMessage());
        }

        $self = LazyObject::proxy(ServiceFixture::class, static fn (ServiceFixture $object): object => $object);
        $this->expectException(Error::class);
        $self->value;
    }

    public function testProxyCloneAndSerializationInitializeOnce(): void
    {
        $cloneCalls = 0;
        $source = LazyObject::proxy(ServiceFixture::class, static function (ServiceFixture $object) use (&$cloneCalls): object {
            ++$cloneCalls;
            return new ServiceFixture('clone');
        });
        $clone = clone $source;

        $serializeCalls = 0;
        $serializable = LazyObject::proxy(ServiceFixture::class, static function (ServiceFixture $object) use (&$serializeCalls): object {
            ++$serializeCalls;
            return new ServiceFixture('serialized');
        });
        $serialized = serialize($serializable);

        self::assertSame(1, $cloneCalls);
        self::assertSame('clone', $clone->value);
        self::assertSame(1, $serializeCalls);
        self::assertStringContainsString('serialized', $serialized);
    }

    public function testGhostFromClassNameIsDeferredInitializesInPlaceAndRunsOnce(): void
    {
        $calls = 0;
        $received = null;
        $ghost = LazyObject::ghost(ServiceFixture::class, static function (ServiceFixture $object) use (&$calls, &$received): void {
            ++$calls;
            $received = $object;
            $object->__construct('initialized');
        });

        self::assertSame(0, $calls);
        self::assertSame('initialized', $ghost->value);
        self::assertSame($ghost, $received);
        self::assertSame('initialized', $ghost->value());
        self::assertSame(1, $calls);
    }

    public function testGhostAcceptsObjectAsTypeAndSupportsPropertyWrite(): void
    {
        $type = new ServiceFixture('type only');
        $ghost = LazyObject::ghost($type, static function (ServiceFixture $object): void { $object->__construct('initial'); });
        $ghost->value = 'written';

        self::assertNotSame($type, $ghost);
        self::assertSame('written', $ghost->value);
    }

    public function testGhostCloneAndSerializationInitializeOnce(): void
    {
        $cloneCalls = 0;
        $source = LazyObject::ghost(ServiceFixture::class, static function (ServiceFixture $object) use (&$cloneCalls): void {
            ++$cloneCalls;
            $object->__construct('clone');
        });
        $clone = clone $source;

        $serializeCalls = 0;
        $serializable = LazyObject::ghost(ServiceFixture::class, static function (ServiceFixture $object) use (&$serializeCalls): void {
            ++$serializeCalls;
            $object->__construct('serialized');
        });
        $serialized = serialize($serializable);

        self::assertSame(1, $cloneCalls);
        self::assertSame('clone', $clone->value);
        self::assertSame(1, $serializeCalls);
        self::assertStringContainsString('serialized', $serialized);
    }

    public function testInitializerFailureIsPropagatedAndCanBeRetried(): void
    {
        $attempts = 0;
        $ghost = LazyObject::ghost(ServiceFixture::class, static function (ServiceFixture $object) use (&$attempts): void {
            if (++$attempts === 1) { throw new RuntimeException('temporary'); }
            $object->__construct('recovered');
        });

        try { $ghost->value; self::fail('Expected initializer failure.'); }
        catch (RuntimeException $error) { self::assertSame('temporary', $error->getMessage()); }

        self::assertSame('recovered', $ghost->value);
        self::assertSame(2, $attempts);
    }

    #[DataProvider('unsupportedClasses')]
    public function testRejectsStructurallyUnsupportedClass(string $class, string $fragment): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($fragment);
        LazyObject::ghost($class, static function (object $object): void {});
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function unsupportedClasses(): iterable
    {
        yield 'interface' => [TestContract::class, 'cannot be made lazy'];
        yield 'trait' => [TestTrait::class, 'cannot be made lazy'];
        yield 'enum' => [TestEnum::class, 'cannot be made lazy'];
        yield 'abstract' => [AbstractFixture::class, 'cannot be made lazy'];
    }

    public function testUnknownClassPreservesReflectionException(): void
    {
        try {
            LazyObject::proxy('Missing\\Service', static fn (object $object): object => $object);
            self::fail('Unknown class was accepted.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Invalid class "Missing\\Service".', $error->getMessage());
            self::assertInstanceOf(ReflectionException::class, $error->getPrevious());
        }
    }

    #[DataProvider('engineRejectedClasses')]
    public function testEngineErrorsAreNotConverted(string $class): void
    {
        $this->expectException(Error::class);
        LazyObject::ghost($class, static function (object $object): void {});
    }

    /** @return iterable<string, array{class-string}> */
    public static function engineRejectedClasses(): iterable
    {
        yield 'internal' => [ArrayObject::class];
        yield 'internal descendant' => [InternalChild::class];
    }

    public function testStdClassAndStatelessClassAreNormalObjects(): void
    {
        $stdCalls = 0;
        $standard = LazyObject::ghost(stdClass::class, static function (stdClass $object) use (&$stdCalls): void { ++$stdCalls; });
        $statelessCalls = 0;
        $stateless = LazyObject::ghost(StatelessFixture::class, static function (StatelessFixture $object) use (&$statelessCalls): void { ++$statelessCalls; });

        self::assertFalse((new ReflectionClass(stdClass::class))->isUninitializedLazyObject($standard));
        self::assertFalse((new ReflectionClass(StatelessFixture::class))->isUninitializedLazyObject($stateless));
        self::assertSame(0, $stdCalls);
        self::assertSame(0, $statelessCalls);
        self::assertSame('pong', $stateless->ping());
    }

    public function testInvalidOptionsAreDelegatedToEngine(): void
    {
        try {
            LazyObject::proxy(ServiceFixture::class, static fn (ServiceFixture $object): object => new ServiceFixture(), ReflectionClass::SKIP_DESTRUCTOR);
            self::fail('Invalid proxy option was accepted.');
        } catch (ReflectionException $error) {
            self::assertStringContainsString('does not accept', $error->getMessage());
        }

        $this->expectException(ReflectionException::class);
        LazyObject::ghost(ServiceFixture::class, static function (ServiceFixture $object): void {}, ReflectionClass::SKIP_DESTRUCTOR);
    }

    public function testSkipInitializationOnSerializeIsForwardedForProxyAndGhost(): void
    {
        $proxyCalls = 0;
        $proxy = LazyObject::proxy(ServiceFixture::class, static function (ServiceFixture $object) use (&$proxyCalls): object {
            ++$proxyCalls;
            return new ServiceFixture('proxy');
        }, ReflectionClass::SKIP_INITIALIZATION_ON_SERIALIZE);

        $ghostCalls = 0;
        $ghost = LazyObject::ghost(ServiceFixture::class, static function (ServiceFixture $object) use (&$ghostCalls): void {
            ++$ghostCalls;
            $object->__construct('ghost');
        }, ReflectionClass::SKIP_INITIALIZATION_ON_SERIALIZE);

        $proxySerialized = serialize($proxy);
        $ghostSerialized = serialize($ghost);

        self::assertSame(0, $proxyCalls);
        self::assertSame(0, $ghostCalls);
        self::assertStringNotContainsString('proxy', $proxySerialized);
        self::assertStringNotContainsString('ghost', $ghostSerialized);
    }

    public function testRepeatedCallsForSameClassRemainIndependent(): void
    {
        $first = LazyObject::ghost(ServiceFixture::class, static fn (ServiceFixture $object) => $object->__construct('first'));
        $second = LazyObject::ghost(ServiceFixture::class, static fn (ServiceFixture $object) => $object->__construct('second'));

        self::assertSame('first', $first->value);
        self::assertSame('second', $second->value);
        self::assertNotSame($first, $second);
    }
}
