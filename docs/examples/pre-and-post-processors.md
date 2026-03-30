# Pre- и Post-процессоры

---

## Описание

Каждый API-метод может иметь произвольное количество пре- и пост-процессоров.

- **PreProcessor** — вызывается **до** выполнения метода `call()`. Полезен для логирования запросов, проверки предусловий, подготовки данных.
- **PostProcessor** — вызывается **после** выполнения метода `call()`. Полезен для логирования ответов, отправки уведомлений, сбора метрик.

Процессоры подключаются через реализацию интерфейсов `PreProcessorInterface` и `PostProcessorInterface`.

---

## Request и Response

```php
<?php
// src/RPC/V1/GetProduct/Request.php

namespace App\RPC\V1\GetProduct;

class Request
{
    private int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
```

```php
<?php
// src/RPC/V1/GetProduct/Response.php

namespace App\RPC\V1\GetProduct;

class Response
{
    private bool $success;
    private string $title;
    private int $price;

    public function __construct(bool $success = true)
    {
        $this->success = $success;
    }

    public function isSuccess(): bool { return $this->success; }
    public function setSuccess(bool $success): void { $this->success = $success; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): void { $this->price = $price; }
}
```

## Трейты процессоров

Рекомендуемый подход — выносить логику процессоров в трейты. Это позволяет переиспользовать их в разных API-методах без дублирования кода.

Трейты могут использовать setter-инъекцию зависимостей через атрибут `#[Required]`.

### PreProcessor

Метод `getPreProcessors()` возвращает массив, где ключ — имя класса, а значение — массив имён методов-обработчиков.

Сигнатура обработчика: `function(string $processorClass, ?object $requestInstance = null): void`

```php
<?php
// src/RPC/RpcPreProcessorTrait.php

namespace App\RPC;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait RpcPreProcessorTrait
{
    private LoggerInterface $logger;

    #[Required]
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getPreProcessors(): array
    {
        return [
            static::class => ['logBeforeCall'],
        ];
    }

    public function logBeforeCall(string $processorClass, ?object $requestInstance = null): void
    {
        $this->logger->info(sprintf('PreProcessor: вызван метод %s', $processorClass));
    }
}
```

### PostProcessor

Сигнатура обработчика: `function(string $processorClass, ?object $requestInstance = null, ?OvResponseInterface $response = null): void`

PostProcessor дополнительно получает объект ответа `$response`.

```php
<?php
// src/RPC/RpcPostProcessorTrait.php

namespace App\RPC;

use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait RpcPostProcessorTrait
{
    private LoggerInterface $postLogger;

    #[Required]
    public function setPostLogger(LoggerInterface $postLogger): void
    {
        $this->postLogger = $postLogger;
    }

    public function getPostProcessors(): array
    {
        return [
            static::class => ['logAfterCall'],
        ];
    }

    public function logAfterCall(string $processorClass, ?object $requestInstance = null, ?OvResponseInterface $response = null): void
    {
        $this->postLogger->info(sprintf('PostProcessor: метод %s выполнен', $processorClass));
    }
}
```

## Метод API с процессорами

Класс метода должен реализовать интерфейсы `PreProcessorInterface` и/или `PostProcessorInterface`.

```php
<?php
// src/RPC/V1/GetProductMethod.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use OV\JsonRPCAPIBundle\Core\PostProcessorInterface;
use App\RPC\V1\GetProduct\Request;
use App\RPC\V1\GetProduct\Response;
use App\RPC\RpcPreProcessorTrait;
use App\RPC\RpcPostProcessorTrait;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod implements PreProcessorInterface, PostProcessorInterface
{
    use RpcPreProcessorTrait;
    use RpcPostProcessorTrait;

    public function call(Request $request): Response
    {
        $response = new Response();
        $response->setTitle('Iphone 15');
        $response->setPrice(2000);
        return $response;
    }
}
```

## Порядок выполнения

```
PreProcessor::logBeforeCall()     <-- ДО вызова метода
    |
GetProductMethod::call()          <-- Основная логика
    |
PostProcessor::logAfterCall()     <-- ПОСЛЕ вызова метода
```

> **Важно:** PostProcessor выполняется в блоке `finally`, поэтому он будет вызван даже если метод `call()` выбросит исключение.
