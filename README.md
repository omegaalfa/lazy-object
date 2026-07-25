# Lazy Object

Pequena fachada tipada para criar lazy proxies e lazy ghosts com a API nativa do PHP 8.4.

## Requisitos

- PHP 8.4 ou superior
- Composer

Reflection faz parte do núcleo do PHP, portanto `ext-reflection` não precisa ser declarada separadamente.

## Instalação

```bash
composer require omegaalfa/lazy-object
```

## Proxy ou ghost?

| Característica | Lazy proxy | Lazy ghost |
|---|---|---|
| Inicialização | A factory cria outra instância | O initializer configura o próprio objeto |
| Callback | `Closure(T): T` | `Closure(T): void` |
| Identidade | Proxy e instância real são distintos | A identidade é preservada |
| Melhor uso | Criação delegada a factory ou container | Inicialização in-place do estado |

## Exemplos de lazy proxy

### 1. Proxy básico

A factory não roda durante a criação. O acesso à propriedade dispara a inicialização.

```php
<?php

declare(strict_types=1);

use Omegaalfa\LazyObject\LazyObject;

final class DatabaseConnection
{
    public function __construct(public string $dsn)
    {
        echo "Conexão criada\n";
    }
}

$connection = LazyObject::lazyProxy(
    DatabaseConnection::class,
    static fn (DatabaseConnection $proxy): DatabaseConnection =>
        new DatabaseConnection('mysql:host=localhost;dbname=app'),
);

echo "Proxy criado\n";
echo $connection->dsn; // Inicializa aqui.
```

### 2. Serviço caro usado condicionalmente

```php
final class ReportGenerator
{
    public function __construct(public array $templates) {}

    public function generate(): string
    {
        return 'Relatório com ' . count($this->templates) . ' templates';
    }
}

$reports = LazyObject::lazyProxy(
    ReportGenerator::class,
    static fn (ReportGenerator $proxy): ReportGenerator =>
        new ReportGenerator(loadTemplatesFromDisk()),
);

if ($userRequestedReport) {
    echo $reports->generate();
}
```

Se a condição for falsa e nada observar o estado, o trabalho caro não será executado.

### 3. Integração com container de dependências

```php
final class Mailer
{
    public function __construct(public string $transport) {}
}

$mailer = LazyObject::lazyProxy(
    Mailer::class,
    static fn (Mailer $proxy): Mailer => $container->get(Mailer::class),
);
```

O container precisa retornar uma instância de `Mailer` ou de uma subclasse compatível.

### 4. Factory com dependências capturadas

```php
$config = ['endpoint' => 'https://api.example.com'];
$logger = new Logger();

$client = LazyObject::lazyProxy(
    ApiClient::class,
    static function (ApiClient $proxy) use ($config, $logger): ApiClient {
        return new ApiClient($config['endpoint'], $logger);
    },
);
```

### 5. Recebendo o próprio proxy

```php
$receivedProxy = null;

$service = LazyObject::lazyProxy(
    ExpensiveService::class,
    static function (ExpensiveService $proxy) use (&$receivedProxy): ExpensiveService {
        $receivedProxy = $proxy;

        return new ExpensiveService('ready');
    },
);

echo $service->status; // Dispara a factory.
var_dump($receivedProxy === $service); // true
```

O argumento da factory é o proxy que está sendo inicializado. A factory deve retornar outra instância válida, nunca o próprio proxy.

## Exemplos de lazy ghost

### 6. Ghost básico chamando o construtor

```php
<?php

declare(strict_types=1);

use Omegaalfa\LazyObject\LazyObject;

final class ExpensiveService
{
    public function __construct(public string $status) {}
}

$service = LazyObject::lazyGhost(
    ExpensiveService::class,
    static function (ExpensiveService $service): void {
        $service->__construct('ready');
    },
);

echo "Ghost criado\n";
echo $service->status; // Inicializa aqui.
```

### 7. Inicialização direta de propriedades

```php
final class UserProfile
{
    public int $id;
    public string $name;
}

$profile = LazyObject::lazyGhost(
    UserProfile::class,
    static function (UserProfile $profile): void {
        $row = fetchUserFromDatabase(42);
        $profile->id = $row['id'];
        $profile->name = $row['name'];
    },
);

echo $profile->name;
```

### 8. Classe com construtor privado

A criação lazy ignora o construtor. O initializer ainda precisa preencher um estado válido usando operações permitidas pela classe.

```php
final class Token
{
    private function __construct(public string $value = '') {}

    public function restore(string $value): void
    {
        $this->value = $value;
    }
}

$token = LazyObject::lazyGhost(
    Token::class,
    static function (Token $token): void {
        $token->restore('secret-token');
    },
);

echo $token->value;
```

### 9. Passando um objeto em vez do nome da classe

O objeto serve apenas para indicar sua classe; ele não é transformado no lazy object retornado.

```php
$original = new ExpensiveService('original');

$lazy = LazyObject::lazyGhost(
    $original,
    static function (ExpensiveService $service): void {
        $service->__construct('lazy');
    },
);

var_dump($lazy !== $original); // true
```

### 10. Nova tentativa depois de uma falha

Se o initializer lançar uma exceção, o PHP restaura o estado lazy. Um acesso posterior tenta inicializar novamente.

```php
$attempts = 0;

$service = LazyObject::lazyGhost(
    ExpensiveService::class,
    static function (ExpensiveService $service) use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('Falha temporária');
        }

        $service->__construct('recovered');
    },
);

try {
    echo $service->status;
} catch (RuntimeException) {
    echo $service->status; // Segunda tentativa: sucesso.
}
```

## Exemplos de ciclo de vida

### 11. Verificando se ainda está lazy

```php
$reflection = new ReflectionClass(ExpensiveService::class);
$service = LazyObject::lazyGhost(
    ExpensiveService::class,
    static fn (ExpensiveService $service) => $service->__construct('ready'),
);

var_dump($reflection->isUninitializedLazyObject($service)); // true
$reflection->initializeLazyObject($service);
var_dump($reflection->isUninitializedLazyObject($service)); // false
```

### 12. Clonagem

Clonar inicializa o objeto antes de criar o clone.

```php
$service = LazyObject::lazyGhost(
    ExpensiveService::class,
    static fn (ExpensiveService $service) => $service->__construct('original'),
);

$clone = clone $service; // Inicializa.
$clone->status = 'clone';

var_dump($service->status); // original
var_dump($clone->status);   // clone
```

### 13. Serialização

A serialização também inicializa por padrão.

```php
$service = LazyObject::lazyGhost(
    ExpensiveService::class,
    static fn (ExpensiveService $service) => $service->__construct('serialized'),
);

$payload = serialize($service); // Inicializa antes de serializar.
```

### 14. Classe sem propriedades

```php
final class StatelessService
{
    public function ping(): string
    {
        return 'pong';
    }
}

$calls = 0;
$service = LazyObject::lazyGhost(
    StatelessService::class,
    static function (StatelessService $service) use (&$calls): void {
        ++$calls;
    },
);

echo $service->ping();
var_dump($calls); // 0
```

Classes sem propriedades de instância, ou somente com propriedades estáticas ou virtuais, retornam um objeto normal. A callback não é executada.

## Tratamento de erros

### Factory retorna tipo incompatível

```php
$service = LazyObject::lazyProxy(
    ExpensiveService::class,
    static fn (ExpensiveService $proxy): stdClass => new stdClass(),
);

try {
    echo $service->status;
} catch (InvalidArgumentException $exception) {
    echo $exception->getMessage();
}
```

### Classe inexistente

```php
try {
    LazyObject::lazyGhost(
        'App\\MissingService',
        static function (object $service): void {},
    );
} catch (InvalidArgumentException $exception) {
    var_dump($exception->getPrevious() instanceof ReflectionException); // true
}
```

### Retorno inválido no ghost

O initializer precisa retornar `void` ou `null`. Um valor não nulo causa `TypeError` nativo.

```php
$service = LazyObject::lazyGhost(
    ExpensiveService::class,
    static function (ExpensiveService $service): ExpensiveService {
        $service->__construct('ready');

        return new ExpensiveService('invalid');
    },
);

echo $service->status; // TypeError durante a inicialização.
```

## API

```php
LazyObject::lazyProxy(class-string<T>|T $class, Closure(T): T $factory): T
LazyObject::lazyGhost(class-string<T>|T $class, Closure(T): void $initializer): T
```

Classes concretas de usuário e `stdClass` são aceitas, inclusive classes finais e classes com construtor privado ou protegido. Interfaces, traits, enums, classes abstratas e classes internas — exceto `stdClass` — são rejeitadas com `InvalidArgumentException`.

Uma classe inexistente preserva a `ReflectionException` como exceção anterior. A factory precisa retornar uma instância da classe solicitada ou uma subclasse. Exceções lançadas pelas callbacks são propagadas sem conversão.

## Gatilhos de inicialização

Estas operações normalmente inicializam o objeto:

- leitura ou escrita de propriedades;
- `isset()` e `unset()` em propriedades;
- reflexão sobre propriedades;
- clonagem;
- serialização.

Uma chamada de método pode não inicializar o objeto caso o método não observe ou modifique seu estado.

Consulte a [documentação oficial de lazy objects](https://www.php.net/language.oop5.lazy-objects.php), [`newLazyProxy()`](https://www.php.net/manual/en/reflectionclass.newlazyproxy.php) e [`newLazyGhost()`](https://www.php.net/manual/en/reflectionclass.newlazyghost.php).

## Testes, análise estática e benchmark

```bash
composer test
XDEBUG_MODE=coverage composer coverage
composer phpstan
php benchmarks/memory.php eager 10000
php benchmarks/memory.php proxy 10000
php benchmarks/memory.php ghost 10000
```

O benchmark mede a memória alocada antes da inicialização. Execute cada estratégia em um processo separado. Os resultados variam conforme o build, as extensões e o allocator do PHP.

## Licença

MIT. Consulte o arquivo [LICENSE](LICENSE).
