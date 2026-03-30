# Testing Quick Reference Guide

## Project: JSON-RPC API Bundle

### Quick Facts
- **Test Framework**: PHPUnit 11
- **Test Location**: `tests/`
- **Base Test Class**: `AbstractTest` in `tests/Controller/AbstractTest.php`
- **Total Tests**: 25 (23 controller + 1 command + 1 utility)
- **Coverage**: ~30% estimated

---

## Running Tests

### All Tests
```bash
./vendor/bin/phpunit tests/
```

### Specific Test File
```bash
./vendor/bin/phpunit tests/Controller/BaseSimpleControllerTest.php
```

### Specific Test Directory
```bash
./vendor/bin/phpunit tests/Controller/
```

### Verbose Output
```bash
./vendor/bin/phpunit tests/ -v
```

### Code Coverage Report
```bash
./vendor/bin/phpunit tests/ --coverage-html coverage/
```

### IDE (PhpStorm/IntelliJ)
- Right-click on test file or folder → "Run Tests"
- Configuration in `.idea/phpunit.xml`

---

## Test Structure Overview

### Core Test Base Class
**File**: `tests/Controller/AbstractTest.php`

```php
// Usage in child test class
protected function executeControllerTest(
    array|string $data,           // JSON request (array or string)
    ?MethodSpec $methodSpec = null,
    array $methodSpecs = [],      // Multiple methods for batch tests
    int $version = 1,             // API version
    array $violationList = []     // Validation violations to simulate
): mixed
```

### Setting Validation Expectations
```php
// Don't expect validation for error tests
$this->setValidateMethodExpectation('never');

// Expect validation to run (default)
$this->setValidateMethodExpectation('atLeastOnce');
```

---

## Test Categories

### 1. Basic Functionality
- `BaseSimpleControllerTest.php` - Simple request/response
- `FewObjectsResponseTest.php` - Multiple objects in response
- `FewObjectsRequestTest.php` - Multiple objects in request

### 2. Parameters
- `Subtract1Test.php` - Positional params `[42, 23]`
- `Subtract2Test.php` - Positional params `[23, 42]`
- `SubtractNamedParams1Test.php` - Named params (order 1)
- `SubtractNamedParams2Test.php` - Named params (order 2)

### 3. Error Handling
- `InvalidJsonRequestTest.php` - Parse error (-32700)
- `InvalidJsonBatchRequestTest.php` - Batch parse error
- `InvalidRequestObjectTest.php` - Invalid request (-32600)
- `InvalidBatchRequest1Test.php` - Invalid batch `[1]`
- `InvalidBatchRequest2Test.php` - Invalid batch `[1,2,3]`
- `EmptyArrayRequestTest.php` - Empty batch `[]`
- `NonExistentMethodTest.php` - Method not found (-32601)

### 4. Batch Requests
- `BatchRequestWithEmptyResponseTest.php` - Notifications batch
- `BatchRequestWithVariousResponsesTest.php` - Mixed requests
- `GetFilteredDataTest.php` - Complex filtering

### 5. Special Responses
- `PlainResponseTest.php` - Binary response (image/png)
- `ResponseWithoutPropertiesTest.php` - Notification (no ID)

### 6. Hooks
- `BeforeMethodPreProcessorTest.php` - Pre-processor
- `AfterMethodPostProcessorTest.php` - Post-processor

### 7. Tools
- `SwaggerGenerateTest.php` - Swagger YAML generation

---

## Key Components

### Mocked Objects
- `Request` - HTTP request with JSON content
- `Validator` - Returns violations
- `Security` - Role checking
- `ServiceLocator` - Service access
- `Container` - Method/service lookup

### Real (Non-Mocked) Objects
- `ApiController` - Main controller
- `RequestHandler` - Request parsing & routing
- `ResponseService` - Response building
- `Serializer` - JSON serialization
- `MethodSpecs` - Method metadata

---

## Test Patterns

### Basic Test Template
```php
final class MyTest extends AbstractTest
{
    public function testSomething()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'myMethod',
            'params' => ['param' => 'value'],
            'id' => '1',
        ];

        $methodSpec = new MethodSpec(
            methodClass: MyMethod::class,
            requestType: 'POST',
            methodName: 'myMethod',
            // ... other properties
        );

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($expectedData), $result->getContent());
    }
}
```

### Error Test Template
```php
public function testError()
{
    $data = ['invalid' => 'request'];
    
    $this->setValidateMethodExpectation('never');
    $result = $this->executeControllerTest($data, $methodSpec);
    
    $expectedError = [
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32600,
            'message' => 'Invalid Request.'
        ],
    ];
    
    $this->assertEquals(json_encode($expectedError), $result->getContent());
}
```

---

## JSON-RPC 2.0 Error Codes

| Code | Message | When |
|------|---------|------|
| -32700 | Parse error | Malformed JSON |
| -32600 | Invalid Request | Missing required fields |
| -32601 | Method not found | Method doesn't exist |
| -32603 | Internal error | Server error |

---

## Fixtures

### Swagger Generation Fixture
- Location: `tests/Command/swagger_generate_test_fixture.yaml`
- Used for regression testing YAML generation
- Updated when swagger schema changes

### Temporary Test Files
- Location: `tests/_tmp/`
- Used by pre/post-processor tests
- Cleaned up between tests

---

## Common Issues & Solutions

### Mock Configuration
```php
// Mock expects method call
$mock->expects($this->any())
    ->method('methodName')
    ->willReturn($value);

// Mock with specific arguments
$mock->expects($this->once())
    ->method('validate')
    ->with($this->anything())
    ->willReturn($violations);
```

### JSON Comparison
```php
// Always encode expected data
$expectedJson = json_encode($expectedData);
$this->assertEquals($expectedJson, $result->getContent());
```

### File Verification
```php
// For processor tests
$this->assertStringEqualsFile(
    './tests/_tmp/TestFile.log',
    'Expected content'
);
```

---

## Adding New Tests

1. **Create test file** in `tests/Controller/`
2. **Extend AbstractTest** (not TestCase)
3. **Build data array** with JSON-RPC format
4. **Create MethodSpec** with method metadata
5. **Call executeControllerTest()**
6. **Assert response** (status, content)

### Template
```php
<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\YourMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Your\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

final class YourTest extends AbstractTest
{
    public function testYourScenario()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'yourMethod',
            'params' => [],
            'id' => '1',
        ];

        $methodSpec = new MethodSpec(
            methodClass: YourMethod::class,
            requestType: 'POST',
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: 'yourMethod',
            allParameters: [],
            requiredParameters: [],
            request: Request::class,
            requestGetters: [],
            requestSetters: [],
            requestAdders: [],
            validators: []
        );

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
    }
}
```

---

## Missing Test Coverage

### High Priority
- Unit tests for `RequestHandler`
- Unit tests for `ResponseService`
- Actual validator behavior tests
- Security/role-based access tests
- Project-level `phpunit.xml`

### Medium Priority
- DependencyInjection tests
- Serialization edge cases
- Performance tests
- Error recovery tests

### Low Priority
- API version compatibility
- End-to-end tests
- Mutation testing

---

## CI/CD Status

❌ **NOT CONFIGURED**
- No GitHub Actions workflow
- No automated test runs
- No coverage reporting
- No pre-commit hooks

✅ **SHOULD ADD**
1. `.github/workflows/phpunit.yml`
2. Project root `phpunit.xml`
3. Coverage report upload
4. Pre-commit test validation

---

## Documentation References

- Full Analysis: `TESTING_ANALYSIS.md`
- Quick Reference: This file
- PHPUnit Docs: https://phpunit.de/
- JSON-RPC Spec: https://www.jsonrpc.org/specification
- Symfony Testing: https://symfony.com/doc/current/testing.html

---

## Useful Commands

```bash
# Run single test
vendor/bin/phpunit tests/Controller/BaseSimpleControllerTest.php

# Run test with filter
vendor/bin/phpunit --filter testRpcCallWithPositionalParameters

# Run with output on stop
vendor/bin/phpunit tests/ --stop-on-failure

# Run with process isolation
vendor/bin/phpunit tests/ --process-isolation

# Generate coverage
vendor/bin/phpunit --coverage-clover coverage.xml

# List available tests
vendor/bin/phpunit --list-tests
```

---

Last Updated: 2026-03-30
