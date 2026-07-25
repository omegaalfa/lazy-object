<?php

declare(strict_types=1);

final class BenchmarkService
{
    public string $value;
}

$iterations = filter_var($argv[1] ?? 20_000, FILTER_VALIDATE_INT);
$rounds = filter_var($argv[2] ?? 7, FILTER_VALIDATE_INT);
if (!is_int($iterations) || $iterations < 100 || !is_int($rounds) || $rounds < 3) {
    throw new InvalidArgumentException('Usage: php benchmarks/reflection-cache.php [iterations>=100] [rounds>=3]');
}

for ($index = 0; $index < 128; ++$index) {
    eval(sprintf('class BenchmarkService%d { public string $value; }', $index));
}
/** @var list<class-string> $manyClasses */
$manyClasses = array_map(static fn (int $index): string => 'BenchmarkService' . $index, range(0, 127));
$initializer = static function (object $object): void { $object->value = 'ready'; };
$factory = static function (object $object): object { return new BenchmarkService(); };

/** @return array{mean: float, median: float, min: float, max: float, stdev: float, ops: float} */
function measure(Closure $operation, int $iterations, int $rounds): array
{
    for ($warmup = 0; $warmup < min(2_000, $iterations); ++$warmup) { $operation($warmup); }
    $samples = [];
    for ($round = 0; $round < $rounds; ++$round) {
        gc_collect_cycles();
        $start = hrtime(true);
        for ($iteration = 0; $iteration < $iterations; ++$iteration) { $operation($iteration); }
        $samples[] = (hrtime(true) - $start) / $iterations;
    }
    sort($samples);
    $mean = array_sum($samples) / count($samples);
    $variance = array_sum(array_map(static fn (float $sample): float => ($sample - $mean) ** 2, $samples)) / count($samples);
    return [
        'mean' => $mean,
        'median' => $samples[intdiv(count($samples), 2)],
        'min' => min($samples),
        'max' => max($samples),
        'stdev' => sqrt($variance),
        'ops' => 1_000_000_000 / $mean,
    ];
}

/** @var array<string, array{without: Closure, with: Closure}> $scenarios */
$scenarios = [];
$reflectionCache = [];
$scenarios['reflection_same_hot'] = [
    'without' => static fn (int $i): ReflectionClass => new ReflectionClass(BenchmarkService::class),
    'with' => static function (int $i) use (&$reflectionCache): ReflectionClass { return $reflectionCache[BenchmarkService::class] ??= new ReflectionClass(BenchmarkService::class); },
];
$coldCache = [];
$scenarios['reflection_same_cold'] = [
    'without' => static fn (int $i): ReflectionClass => new ReflectionClass(BenchmarkService::class),
    'with' => static function (int $i) use (&$coldCache): ReflectionClass { $coldCache = []; return $coldCache[BenchmarkService::class] ??= new ReflectionClass(BenchmarkService::class); },
];
$manyReflectionCache = [];
$scenarios['reflection_many_classes'] = [
    'without' => static fn (int $i): ReflectionClass => new ReflectionClass($manyClasses[$i % count($manyClasses)]),
    'with' => static function (int $i) use (&$manyReflectionCache, $manyClasses): ReflectionClass { $class = $manyClasses[$i % count($manyClasses)]; return $manyReflectionCache[$class] ??= new ReflectionClass($class); },
];
$ghostCache = [];
$scenarios['ghost_same_hot'] = [
    'without' => static fn (int $i): object => (new ReflectionClass(BenchmarkService::class))->newLazyGhost($initializer),
    'with' => static function (int $i) use (&$ghostCache, $initializer): object { $reflection = $ghostCache[BenchmarkService::class] ??= new ReflectionClass(BenchmarkService::class); return $reflection->newLazyGhost($initializer); },
];
$ghostManyCache = [];
$scenarios['ghost_many_classes'] = [
    'without' => static function (int $i) use ($manyClasses, $initializer): object { return (new ReflectionClass($manyClasses[$i % count($manyClasses)]))->newLazyGhost($initializer); },
    'with' => static function (int $i) use (&$ghostManyCache, $manyClasses, $initializer): object { $class = $manyClasses[$i % count($manyClasses)]; $reflection = $ghostManyCache[$class] ??= new ReflectionClass($class); return $reflection->newLazyGhost($initializer); },
];
$proxyCache = [];
$scenarios['proxy_same_hot'] = [
    'without' => static fn (int $i): object => (new ReflectionClass(BenchmarkService::class))->newLazyProxy($factory),
    'with' => static function (int $i) use (&$proxyCache, $factory): object { $reflection = $proxyCache[BenchmarkService::class] ??= new ReflectionClass(BenchmarkService::class); return $reflection->newLazyProxy($factory); },
];

printf("PHP %s | %s | OPcache CLI: %s | JIT: %s\n", PHP_VERSION, PHP_OS_FAMILY, ini_get('opcache.enable_cli') ? 'on' : 'off', ini_get('opcache.jit') ?: 'off');
printf("Iterations: %d | rounds: %d | values in nanoseconds/op\n\n", $iterations, $rounds);
echo "| Scenario | Cache | Mean | Median | Min | Max | Stddev | Ops/s | Difference |\n";
echo "|---|---:|---:|---:|---:|---:|---:|---:|---:|\n";
foreach ($scenarios as $name => $pair) {
    $without = measure($pair['without'], $iterations, $rounds);
    $with = measure($pair['with'], $iterations, $rounds);
    $difference = (($with['mean'] - $without['mean']) / $without['mean']) * 100;
    printf("| %s | no | %.1f | %.1f | %.1f | %.1f | %.1f | %.0f | baseline |\n", $name, $without['mean'], $without['median'], $without['min'], $without['max'], $without['stdev'], $without['ops']);
    printf("| %s | yes | %.1f | %.1f | %.1f | %.1f | %.1f | %.0f | %+.1f%% |\n", $name, $with['mean'], $with['median'], $with['min'], $with['max'], $with['stdev'], $with['ops'], $difference);
}

$before = memory_get_usage();
$memoryCache = [];
foreach ($manyClasses as $class) { $memoryCache[$class] = new ReflectionClass($class); }
printf("\nCache memory for %d classes: %d bytes (%.1f bytes/class)\n", count($memoryCache), memory_get_usage() - $before, (memory_get_usage() - $before) / count($memoryCache));
