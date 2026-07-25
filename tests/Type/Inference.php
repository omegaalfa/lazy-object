<?php

declare(strict_types=1);

namespace Omegaalfa\LazyObject\Tests\Type;

use Omegaalfa\LazyObject\LazyObject;

use function PHPStan\Testing\assertType;

$proxy = LazyObject::proxy(
    TypeService::class,
    static fn (TypeService $object): object => new TypeService(),
);
$ghost = LazyObject::ghost(
    TypeService::class,
    static function (TypeService $object): void { $object->value = 'ready'; },
);

assertType(TypeService::class, $proxy);
assertType(TypeService::class, $ghost);
