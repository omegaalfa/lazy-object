<div align="center">

# ⚡ Lazy Object

### Uma fachada pequena, tipada e fiel aos lazy objects nativos do PHP 8.4

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/8.4/)
[![CI](https://github.com/omegaalfa/lazy-object/actions/workflows/ci.yml/badge.svg)](https://github.com/omegaalfa/lazy-object/actions/workflows/ci.yml)
[![PHPStan max](https://img.shields.io/badge/PHPStan-max-4F5B93)](https://phpstan.org/)
[![Coverage 100%](https://img.shields.io/badge/coverage-100%25-brightgreen)](#-desenvolvimento)
[![License MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

</div>

> [!TIP]
> Use a API moderna `LazyObject::proxy()` e `LazyObject::ghost()` sem criar uma implementação paralela das regras do PHP.

O pacote não implementa proxies próprios, não substitui regras do engine, não oferece compatibilidade artificial e não converte indiscriminadamente erros nativos.

## 📋 Requisitos

- PHP 8.4 ou superior;
- Composer.

## 📦 Instalação

```bash
composer require omegaalfa/lazy-object
```

## 🆚 Proxy ou ghost?

| 🔍 Característica | 🔀 Proxy | 👻 Ghost |
|---|---|---|
| 📞 Callback | `Closure(T): object` | `Closure(T): void` |
| ⚙️ Inicialização | A factory fornece outra instância | O initializer configura o próprio objeto |
| 🪪 Identidade | O proxy mantém identidade própria e delega para uma instância real | O próprio objeto é inicializado in-place |
| 🎯 Uso típico | Factory, container ou criação delegada | Hidratação ou inicialização in-place |

## 🚀 Exemplos

### 1. 🔀 Lazy proxy completo

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Omegaalfa\LazyObject\LazyObject;

final class DatabaseConnection
{
    public function __construct(public string $dsn)
    {
        echo "Conexão aberta\n";
    }
}

$connection = LazyObject::proxy(
    DatabaseConnection::class,
    static fn (DatabaseConnection $_proxy): object =>
        new DatabaseConnection('mysql:host=localhost;dbname=app'),
);

echo "Proxy criado\n";
echo $connection->dsn; // Executa a factory.
```

> [!NOTE]
> A factory recebe obrigatoriamente o proxy, mesmo quando não precisa utilizá-lo, e retorna a instância real. `Closure(T): object` é intencional: o engine aceita a mesma classe e certos casos de superclasse compatível. A biblioteca não duplica essas regras complexas. No PHP 8.4.16, retornar uma subclasse para um proxy da classe pai é rejeitado pelo engine.

### 2. 📦 Proxy criado por container

Considerando um container PSR-11 disponível em `$container`:

```php
/** @var Psr\Container\ContainerInterface $container */
$mailer = LazyObject::proxy(
    Mailer::class,
    static fn (Mailer $_proxy): object => $container->get(Mailer::class),
);
```

### 3. ⏳ Serviço caro usado condicionalmente

```php
$reports = LazyObject::proxy(
    ReportGenerator::class,
    static fn (ReportGenerator $_proxy): object => new ReportGenerator(loadTemplates()),
);

if ($request->wantsReport()) {
    echo $reports->generate();
}
```

### 4. 👻 Lazy ghost completo

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Omegaalfa\LazyObject\LazyObject;

final class UserProfile
{
    public function __construct(public int $id, public string $name) {}
}

$profile = LazyObject::ghost(
    UserProfile::class,
    static function (UserProfile $profile): void {
        $profile->__construct(42, 'Ada');
    },
);

echo $profile->name; // Executa o initializer.
```

### 5. 💧 Ghost hidratando propriedades

```php
$invoice = LazyObject::ghost(
    Invoice::class,
    static function (Invoice $invoice): void {
        $row = fetchInvoice(100);
        $invoice->id = $row['id'];
        $invoice->total = $row['total'];
    },
);
```

### 6. 🏷️ Nome da classe ou objeto

```php
$fromClassName = LazyObject::ghost(
    Service::class,
    static fn (Service $service): void => $service->__construct('class-string'),
);

$typeExample = new Service('apenas informa o tipo');
$fromObject = LazyObject::ghost(
    $typeExample,
    static fn (Service $service): void => $service->__construct('novo objeto'),
);

var_dump($fromObject !== $typeExample); // true
```

A instância passada em `$class` serve para resolver o tipo. Ela não é reaproveitada como ghost nem como instância real do proxy.

### 7. 🔁 Exceção e nova tentativa

```php
$attempts = 0;
$service = LazyObject::ghost(
    Service::class,
    static function (Service $service) use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('Falha temporária');
        }
        $service->__construct('recuperado');
    },
);

try {
    echo $service->value;
} catch (RuntimeException) {
    echo $service->value; // O estado lazy foi restaurado; tenta novamente.
}
```

### 8. 💾 Evitar inicialização na serialização

```php
$service = LazyObject::ghost(
    Service::class,
    static fn (Service $service): void => $service->__construct('carregado'),
    \ReflectionClass::SKIP_INITIALIZATION_ON_SERIALIZE,
);

$payload = serialize($service); // O initializer não é executado.
```

> [!IMPORTANT]
> `SKIP_INITIALIZATION_ON_SERIALIZE` é a opção disponível para `newLazyProxy()` e `newLazyGhost()`. Não use `SKIP_DESTRUCTOR`: essa flag pertence aos métodos `resetAsLazyProxy()` e `resetAsLazyGhost()` e é rejeitada aqui.

### 9. 🧬 Clonagem

```php
$service = LazyObject::ghost(
    Service::class,
    static fn (Service $service): void => $service->__construct('original'),
);

$clone = clone $service; // Inicializa antes de clonar.
$clone->value = 'clone';
```

### 10. 🔎 Inspeção explícita

```php
$reflection = new \ReflectionClass($service);

if ($reflection->isUninitializedLazyObject($service)) {
    $reflection->initializeLazyObject($service);
}
```

## 🪶 Classes sem propriedades

Classes sem propriedades de instância — ou somente com propriedades estáticas ou virtuais — podem resultar em um objeto normal. Quando o engine retorna uma instância normal nesses casos, a callback não é executada. Consumidores não devem presumir que todo retorno esteja necessariamente lazy e não inicializado.

```php
$reflection = new \ReflectionClass($object);
var_dump($reflection->isUninitializedLazyObject($object));
```

> [!NOTE]
> No PHP 8.4.16 testado, `stdClass` e uma classe de usuário sem propriedades retornaram objetos normais.

## ⚠️ Exceções

A biblioteca lança `InvalidArgumentException` para:

- classe inexistente (preservando `ReflectionException` como exceção anterior);
- interface;
- trait;
- enum;
- classe abstrata.

As validações restantes são delegadas ao PHP. A biblioteca não converte erros do engine relacionados a classes internas incompatíveis, descendentes de classes internas, factory incompatível, retorno do próprio proxy ou outras regras nativas.

> [!WARNING]
> No PHP 8.4.16, classes/factories incompatíveis resultaram em `Error`/`TypeError`, enquanto uma opção inválida resultou em `ReflectionException`. Esses tipos são propagados sem conversão.

## ⚡ Cache de `ReflectionClass`

O pacote mantém um cache interno de `ReflectionClass` por nome de classe. A presença de uma reflexão no cache significa apenas que a classe foi refletida e passou pelas verificações estruturais locais. A compatibilidade completa com lazy objects, incluindo herança de classes internas, opções e compatibilidade entre proxy e instância real, continua sendo validada pelo engine do PHP em cada chamada.

O cache não possui API pública de controle. Consulte `benchmarks/reflection-cache.php` para medir seu impacto no ambiente de destino.

## 🎯 Gatilhos de inicialização

Operações sobre propriedades, como leitura, escrita, `isset()`, `unset()` e determinadas operações via Reflection, podem disparar a inicialização conforme as regras nativas do PHP. Clonagem e serialização inicializam por padrão, salvo quando uma opção nativa aplicável altera esse comportamento. Uma chamada de método pode não inicializar quando o método não observa nem modifica o estado.

## 🛠️ Desenvolvimento

```bash
composer install
composer check
XDEBUG_MODE=coverage composer coverage
composer benchmark -- 20000 7
php -d opcache.enable_cli=1 benchmarks/reflection-cache.php 20000 7
```

`composer check` executa sintaxe/estilo básico, PHPUnit e PHPStan. O benchmark usa `hrtime(true)`, warm-up, múltiplas rodadas e apresenta média, mediana, mínimo, máximo, desvio padrão, operações por segundo e memória do cache.

O `@phpstan-ignore-next-line` em `LazyObject::proxy()` é intencional: o stub atual do PHPStan exige `callable(T): T`, enquanto a API nativa declara uma factory que retorna `object` e deixa a compatibilidade concreta da instância retornada ser validada pelo engine. A anotação pode ser removida quando o stub refletir esse contrato.

Metodologia, ambiente e instruções detalhadas estão em [benchmarks/README.md](benchmarks/README.md).

## 🔗 Compatibilidade

A série v1 exige PHP `^8.4` e usa diretamente `ReflectionClass::newLazyProxy()` e `ReflectionClass::newLazyGhost()`.

Documentação oficial: [Lazy Objects](https://www.php.net/language.oop5.lazy-objects.php), [`newLazyProxy()`](https://www.php.net/manual/en/reflectionclass.newlazyproxy.php) e [`newLazyGhost()`](https://www.php.net/manual/en/reflectionclass.newlazyghost.php).

## 📄 Licença

🟢 Distribuído sob a licença **MIT**. Consulte o arquivo [LICENSE](LICENSE).
