# Benchmark do cache de ReflectionClass

Este benchmark compara resolução de `ReflectionClass` e criação de lazy objects com e sem cache. O setup e o autoload acontecem antes do trecho medido.

## Ambiente de referência

- CPU: Intel Core i3-9100F, 4 cores, 3,60 GHz;
- RAM disponível para o WSL: 11 GiB;
- sistema operacional: Ubuntu 24.04.3 LTS sobre WSL/Hyper-V;
- PHP 8.4.16;
- medição principal com OPcache CLI e JIT desativados.

## Metodologia

O script usa `hrtime(true)`, warm-up e múltiplas rodadas. Ele informa média, mediana, mínimo, máximo, desvio padrão, operações por segundo e memória ocupada pelo cache.

Os cenários incluem:

- Reflection isolada com cache quente e frio;
- mesma classe repetida e 128 classes diferentes;
- criação completa de lazy ghosts;
- criação completa de lazy proxies;
- custo de memória das entradas cacheadas.

## Execução

```bash
composer benchmark -- 20000 7
php -d opcache.enable_cli=1 benchmarks/reflection-cache.php 20000 7
```

Execute várias vezes no ambiente de destino. Microbenchmarks variam conforme CPU, sistema operacional, extensões, OPcache, JIT e carga concorrente.

## Resultado de referência

Na medição principal de 20.000 operações por rodada e sete rodadas, o cache quente reduziu o tempo médio em aproximadamente 29,5% para Reflection isolada, 16,4% para ghosts e 18,2% para proxies. Um cache deliberadamente frio foi cerca de 31,6% mais lento. O cache de 128 classes ocupou aproximadamente 20.536 bytes, ou 160,4 bytes por classe.

Esses números justificam o cache simples para workloads repetitivos, mas não constituem garantia de desempenho para outros ambientes.
