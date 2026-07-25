<?php

declare(strict_types=1);

use Omegaalfa\LazyObject\LazyObject;

require dirname(__DIR__) . '/vendor/autoload.php';

final class MemoryFixture { public function __construct(public string $payload) {} }

$strategy = $argv[1] ?? '';
$count = filter_var($argv[2] ?? 10000, FILTER_VALIDATE_INT);
if (!is_int($count) || $count < 1) { throw new InvalidArgumentException('Count must be positive.'); }
$baseline = memory_get_usage();
$objects = [];
for ($i = 0; $i < $count; ++$i) {
    $objects[] = match ($strategy) {
        'eager' => new MemoryFixture(str_repeat('x', 1024)),
        'proxy' => LazyObject::lazyProxy(MemoryFixture::class, static fn (MemoryFixture $p): MemoryFixture => new MemoryFixture(str_repeat('x', 1024))),
        'ghost' => LazyObject::lazyGhost(MemoryFixture::class, static fn (MemoryFixture $g): mixed => $g->__construct(str_repeat('x', 1024))),
        default => throw new InvalidArgumentException('Strategy must be eager, proxy, or ghost.'),
    };
}
printf("%s count=%d allocated=%d peak_delta=%d\n", $strategy, $count, memory_get_usage() - $baseline, memory_get_peak_usage() - $baseline);
