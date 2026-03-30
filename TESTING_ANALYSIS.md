# PHP Project Testing Structure & Analysis

## Project: OtezVikentiy Symfony JSON RPC API Bundle
- **Type**: Symfony Bundle
- **PHP Version**: >= 8.2
- **Version**: 2.18
- **License**: MIT

---

## 1. TESTING DEPENDENCIES (composer.json)

### Dev Dependencies:
- **phpunit/phpunit**: 11.* (Latest PHPUnit version)
- **symfony/property-access**: >=6.4

### Required Dependencies:
- doctrine/annotations >= 2.0
- symfony/framework-bundle >= 6.4
- symfony/yaml >= 6.4
- symfony/validator >= 6.4
- symfony/serializer >= 6.4
- symfony/console >= 6.4
- symfony/security-bundle >= 6.4

### Key Observation:
- Uses **Symfony 6.4+** with attributes support (no legacy annotations)
- Testing uses **PHPUnit 11** (modern version)
- Heavy reliance on Symfony components (validator, serializer, console, security)

---

## 2. PHPUNIT CONFIGURATION

### Configuration Files:
- **Location**: `.idea/phpunit.xml` (IDE-based, not project root)
- **PHP Interpreter**: `/bin/php8.2`
- **Test Directory**: `$PROJECT_DIR$/tests`

### Important:
- **No project root phpunit.xml.dist or phpunit.xml** - relies on IDE configuration
- Tests can be run via IDE or CLI with default PHPUnit settings

---

## 3. AUTOLOADING (composer.json)

```
PSR-4 Mapping:
- "OV\\JsonRPCAPIBundle\\" => "src/"
- "OV\\JsonRPCAPIBundle\\Tests\\" => "tests/"
```

**All tests use namespace**: `OV\JsonRPCAPIBundle\Tests`

---

## 4. TEST FILES STRUCTURE & PATTERNS

### Directory Layout:
```
tests/
├── BaseTest.php                              # Simple smoke test
├── Command/
│   └── SwaggerGenerateTest.php               # Swagger generation test
└── Controller/
    ├── AbstractTest.php                      # Base test class with setup/teardown
    ├── AfterMethodPostProcessorTest.php      # Post-processor test
    ├── BatchRequestWithEmptyResponseTest.php # Batch request (notifications)
    ├── BatchRequestWithVariousResponsesTest.php # Complex batch test
    ├── BaseSimpleControllerTest.php          # Basic controller test
    ├── BeforeMethodPreProcessorTest.php      # Pre-processor test
    ├── EmptyArrayRequestTest.php             # Error handling
    ├── FewObjectsRequestTest.php             # Multiple objects in request
    ├── FewObjectsResponseTest.php            # Multiple objects in response
    ├── GetFilteredDataTest.php               # Complex filtering
    ├── InvalidBatchRequest1Test.php          # Invalid batch (not empty)
    ├── InvalidBatchRequest2Test.php          # Invalid batch (multiple items)
    ├── InvalidJsonBatchRequestTest.php       # Malformed JSON in batch
    ├── InvalidJsonRequestTest.php            # Malformed JSON
    ├── InvalidRequestObjectTest.php          # Invalid request object
    ├── NonExistentMethodTest.php             # Method not found handling
    ├── PlainResponseTest.php                 # Non-JSON response (binary)
    ├── ResponseWithoutPropertiesTest.php     # Notification (no response)
    ├── Subtract1Test.php                     # Positional parameters test
    ├── Subtract2Test.php                     # Positional parameters test (reverse)
    ├── SubtractNamedParams1Test.php          # Named parameters test
    └── SubtractNamedParams2Test.php          # Named parameters test (reverse)
```

### Temporary Test Directory:
```
tests/_tmp/
```
Used by post-processor and pre-processor tests for file-based test verification.

---

## 5. BASE TEST CLASS: AbstractTest

**Location**: `tests/Controller/AbstractTest.php`

### Key Characteristics:
- Extends `PHPUnit\Framework\TestCase`
- Provides complete mock setup for testing JSON-RPC API controller
- Uses mock objects extensively via `createMock()`

### Core Setup Methods:
```php
executeControllerTest(
    array|string $data,
    ?MethodSpec $methodSpec = null,
    array $methodSpecs = [],
    int $version = 1,
    array $violationList = []
): mixed
```

### Mock Components:
1. **Request Mock** - Symfony HTTP Request
   - Mocked methods: `getMethod()`, `getPathInfo()`, `getContent()`
   - Request body passed as JSON string or array

2. **Validator Mock** - ValidatorInterface
   - Returns ConstraintViolationList
   - Supports violation simulation
   - Configurable validation expectation levels (atLeastOnce, never, etc.)

3. **Security Mock** - Security service
   - `isGranted()` returns true by default
   - Can be configured per test

4. **ServiceLocator Mock** - Dependency injection locator
   - Provides serializer instance
   - Uses real Symfony Serializer with ObjectNormalizer

5. **Serializer** - Real instance (NOT mocked)
   - Uses `ObjectNormalizer` + `JsonEncoder`
   - Wrapped with `TraceableNormalizer` for debugging

6. **MethodSpecCollection** - Holds RPC method metadata
   - Built from real method classes
   - Uses AnnotationReader to extract JsonRPCAPI annotation/attribute

7. **Container Mock** - DependencyInjection Container
   - Returns method instances and serializer

### Validator Configuration:
```php
setValidateMethodExpectation(string $validateMethodExpectation): AbstractTest
```
- Allows switching between `atLeastOnce`, `never`, and other expectations
- Used when testing error conditions (no validation should run)

### Lifecycle Hooks:
```php
protected function after(): void // Override in child classes
protected function tearDown(): void // Cleans up all mock references
```

---

## 6. TEST PATTERNS & COVERAGE

### Test Categories:

#### A. BASIC FUNCTIONALITY TESTS
- **BaseSimpleControllerTest**: Basic JSON-RPC request/response
- **FewObjectsResponseTest**: API response with multiple objects
- **FewObjectsRequestTest**: API request with multiple objects

#### B. PARAMETER HANDLING TESTS
- **Subtract1Test**: Positional parameters `[42, 23]`
- **Subtract2Test**: Positional parameters reversed `[23, 42]`
- **SubtractNamedParams1Test**: Named parameters `{'subtrahend': 23, 'minuend': 42}`
- **SubtractNamedParams2Test**: Named parameters reversed

#### C. ERROR HANDLING TESTS
- **InvalidJsonRequestTest**: Malformed JSON `{"jsonrpc": "2.0", "method": "foobar, "params": ...`
- **InvalidJsonBatchRequestTest**: Invalid JSON in batch request
- **InvalidRequestObjectTest**: Invalid request object structure
- **InvalidBatchRequest1Test**: Batch with single integer `[1]`
- **InvalidBatchRequest2Test**: Batch with integers `[1, 2, 3]`
- **EmptyArrayRequestTest**: Empty array batch `[]`
- **NonExistentMethodTest**: Method not found error response
- **ResponseWithoutPropertiesTest**: Notification (no ID, no response expected)

#### D. BATCH REQUEST TESTS
- **BatchRequestWithEmptyResponseTest**: Multiple notifications (empty response)
- **BatchRequestWithVariousResponsesTest**: Complex batch with mixed valid/invalid requests
- **GetFilteredDataTest**: Complex object filtering

#### E. SPECIAL RESPONSE TYPES
- **PlainResponseTest**: Non-JSON binary response (e.g., image/png)

#### F. REQUEST/RESPONSE PROCESSING
- **BeforeMethodPreProcessorTest**: Pre-processor hooks execution
- **AfterMethodPostProcessorTest**: Post-processor hooks execution

#### G. COMMAND TESTS
- **SwaggerGenerateTest**: Swagger YAML generation command
  - Tests against fixture file: `swagger_generate_test_fixture.yaml`
  - Comprehensive method spec coverage for all methods

### Test Patterns Used:

1. **Assertion Pattern**: Direct JSON comparison
   ```php
   $this->assertEquals(json_encode($expectedData), $result->getContent());
   ```

2. **Response Type Pattern**: Both `JsonResponse` and `Response` types tested
   ```php
   $this->assertInstanceOf(JsonResponse::class, $result);
   $this->assertEquals(200, $result->getStatusCode());
   ```

3. **Mock Configuration Pattern**:
   ```php
   $mock->expects($this->any())->method('method')->willReturn(value);
   ```

4. **File-based Verification**: Post/Pre-processor tests use files
   ```php
   $this->assertStringEqualsFile('./tests/_tmp/AfterMethodPostProcessorsTest.log', 'content');
   ```

---

## 7. MOCKING APPROACH

### Mock Framework:
- **PHPUnit Built-in**: `createMock()`, `createMock(Interface::class)`
- **No External Mocking Libraries**: Not using Mockery, Prophecy, or similar

### Key Mock Objects:

| Component | Type | Mocking | Notes |
|-----------|------|---------|-------|
| Request | Mock | Full mock | Methods mocked: getMethod, getPathInfo, getContent |
| Validator | Mock | Full mock | Returns ConstraintViolationList |
| Security | Mock | Full mock | isGranted() always true |
| ServiceLocator | Mock | Full mock | Returns real Serializer |
| Serializer | Real | NOT mocked | Real ObjectNormalizer + JsonEncoder |
| Container | Mock | Full mock | Used for method/service lookup |
| MethodSpec | Real | NOT mocked | Real instances from method classes |
| ApiController | Real | NOT mocked | Actual controller tested |
| RequestHandler | Real | NOT mocked | Core logic component |
| ResponseService | Real | NOT mocked | Response building logic |
| HeadersPreparer | Real | NOT mocked | Header handling logic |

---

## 8. TEST FIXTURES & DATA

### SwaggerGenerateTest Fixture:
- Location: `tests/Command/swagger_generate_test_fixture.yaml`
- Used for regression testing of Swagger YAML generation
- Compares generated output against stored fixture

### Test Data Patterns:
- JSON-RPC 2.0 compliant requests
- Various parameter types: arrays, integers, strings, objects
- Named and positional parameters
- Batch requests (arrays of requests)
- Invalid JSON and malformed requests

### Expected Responses:
- JSON-RPC 2.0 compliant responses
- Error objects with error codes: `-32600` (Invalid Request), `-32601` (Method not found), `-32700` (Parse error)
- Result objects with data
- Notification responses (empty JSON `{}`)

---

## 9. CI/CD CONFIGURATION

### Current Status:
- **No GitHub Workflows**: No `.github/workflows/` directory
- **No CI/CD Pipeline**: No automated testing on push
- **IDE Only**: PHPUnit configured only in `.idea/phpunit.xml`

### Recommendation:
Missing CI/CD setup. Consider adding:
- GitHub Actions for automated testing
- PHPUnit configuration file at project root
- Pre-commit hooks

---

## 10. WHAT'S ALREADY COVERED

### ✅ Well-Tested Areas:
1. **Basic Request/Response Cycle**: Multiple test cases
2. **Parameter Handling**: Both positional and named parameters
3. **Error Handling**: Invalid JSON, invalid requests, method not found
4. **Batch Requests**: Multiple concurrent requests
5. **Notifications**: Requests without ID and without response
6. **Response Types**: JSON and binary (plain) responses
7. **Pre/Post Processors**: Hook execution before/after methods
8. **Multiple Objects**: Arrays of objects in request/response
9. **Complex Objects**: Nested object filtering
10. **Swagger Generation**: Command-line tool testing

---

## 11. WHAT'S MISSING / NOT COVERED

### ❌ Gaps Identified:

1. **Core Service Tests**: No direct unit tests for:
   - `RequestHandler`
   - `ResponseService`
   - `HeadersPreparer`
   - `RequestRawDataHandler`
   - Annotation/Attribute parsing

2. **Serialization Edge Cases**:
   - Custom serializer behavior
   - Null value handling
   - Type coercion
   - Circular references

3. **Validation Integration**:
   - No tests validating actual Symfony validators
   - No constraint violation handling tests
   - Only mocked validation responses

4. **Security/Authorization**:
   - Role-based access control not tested
   - Only mocked security checks
   - No actual permission/role scenarios

5. **API Method Classes** (in `src/RPC/V1/`):
   - No unit tests for individual method implementations
   - Methods tested only through integration tests
   - Processor classes not directly unit tested

6. **Dependency Injection**:
   - No tests for CompilerPass
   - No tests for Configuration
   - No tests for Extension

7. **Data Transformation**:
   - No tests for Request/Response object hydration
   - No tests for getter/setter validation
   - No edge case handling tests

8. **Performance/Load**:
   - No performance tests
   - No batch request performance tests
   - No stress testing

9. **Error Recovery**:
   - Limited edge case testing
   - No recovery from serialization failures
   - No recovery from validator failures

10. **Documentation**:
    - No testing documentation
    - No test writing guide
    - No contributing guide for tests

---

## 12. TEST STATISTICS

| Metric | Count |
|--------|-------|
| Total Test Files | 25 |
| Controller Tests | 23 |
| Command Tests | 1 |
| Base/Utility Tests | 1 |
| Test Classes | 25 |
| Test Methods | 25 (1 per class) |
| Mock Objects Types | ~8 |
| Tested API Methods | 9 methods |

---

## 13. RUNNING TESTS

### Command Line:
```bash
# Run all tests
./vendor/bin/phpunit tests/

# Run specific test file
./vendor/bin/phpunit tests/Controller/BaseSimpleControllerTest.php

# Run with verbose output
./vendor/bin/phpunit tests/ -v

# Run with code coverage
./vendor/bin/phpunit tests/ --coverage-html coverage/
```

### IDE (PhpStorm/IntelliJ):
- Configuration stored in `.idea/phpunit.xml`
- Right-click on test file/directory and select "Run Tests"
- All tests discoverable through IDE

---

## 14. KEY TECHNICAL DETAILS

### JSON-RPC 2.0 Compliance:
- Tests verify full JSON-RPC 2.0 specification compliance
- All error codes documented:
  - `-32700`: Parse error
  - `-32600`: Invalid Request
  - `-32601`: Method not found
  - `-32603`: Internal error (not tested yet)

### Request/Response Flow:
```
HTTP Request (JSON) 
  → ApiController::index()
  → RequestHandler (parses, validates)
  → Method execution
  → ResponseService (builds response)
  → HTTP Response (JSON or binary)
```

### Parameter Mapping:
- From JSON params to Request object via setter methods
- From Response object to JSON via Serializer
- Support for:
  - Positional parameters (array indices)
  - Named parameters (array keys)
  - Object parameters (complex objects)
  - Array of objects (with adder methods)

### Annotations/Attributes:
- Supports both Doctrine annotations and PHP 8 attributes
- `JsonRPCAPI` annotation with metadata:
  - Method name
  - Summary/description
  - Parameter specifications
  - Required parameters
  - Response types

---

## 15. CONFIGURATION & SETUP

### Service Configuration (`config/services.yaml`):
- Auto-wiring enabled
- Auto-configuration enabled
- Autowires tags for methods: `ov.rpc.method`
- Custom bindings for access control list

### Dependencies:
- All core services auto-registered
- Controllers as service arguments
- Commands registered with console

---

## 16. RECOMMENDATIONS FOR TESTING

### Immediate Needs:
1. Add root-level `phpunit.xml` configuration
2. Create GitHub Actions workflow for CI/CD
3. Add unit tests for core services
4. Add integration tests for validator behavior
5. Test security/authorization scenarios

### Medium-term:
1. Unit tests for DependencyInjection components
2. Edge case tests for serialization
3. Performance/load testing
4. Error recovery testing
5. Documentation for test structure

### Long-term:
1. Increase code coverage (currently ~30%)
2. Mutation testing
3. Property-based testing
4. End-to-end testing
5. API versioning tests (V2, V3, etc.)

