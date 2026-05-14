<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface;
use OV\JsonRPCAPIBundle\Core\Request\PartialUpdateRequest;
use OV\JsonRPCAPIBundle\Core\Request\TracksProvidedFieldsTrait;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PartialRequestHydrationTestPartialRequest extends PartialUpdateRequest
{
    private ?int $id = null;
    private ?string $email = null;
    private ?string $name = null;
    private ?string $bio = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }
}

class PartialRequestHydrationTestLegacyRequest
{
    private ?int $id = null;
    private ?string $email = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }
}

class PartialRequestHydrationTestNestedChild implements PartialRequestInterface
{
    use TracksProvidedFieldsTrait;

    private ?string $street = null;
    private ?string $city = null;

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): void
    {
        $this->street = $street;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }
}

class PartialRequestHydrationTestNestedRequest extends PartialUpdateRequest
{
    private ?int $id = null;
    private ?PartialRequestHydrationTestNestedChild $address = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getAddress(): ?PartialRequestHydrationTestNestedChild
    {
        return $this->address;
    }

    public function setAddress(?PartialRequestHydrationTestNestedChild $address): void
    {
        $this->address = $address;
    }
}

class PartialRequestHydrationTestMethod
{
    public mixed $captured = null;

    public function call(mixed $request): array
    {
        $this->captured = $request;

        return ['ok' => true];
    }
}

final class PartialRequestHydrationTest extends TestCase
{
    private PartialRequestHydrationTestMethod $methodInstance;

    protected function setUp(): void
    {
        $this->methodInstance = new PartialRequestHydrationTestMethod();
    }

    private function buildHandler(MethodSpecCollection $specCollection): RequestHandler
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $headersPreparer = new HeadersPreparer(['*']);
        $responseService = new ResponseService($headersPreparer);

        $container = $this->createMock(Container::class);
        $container->method('get')->willReturnMap([
            [PartialRequestHydrationTestMethod::class, 1, $this->methodInstance],
        ]);

        return new RequestHandler(
            $security,
            $specCollection,
            $validator,
            $headersPreparer,
            $container,
            $responseService,
            new NullJsonRpcCallLogger(),
        );
    }

    private function buildFlatSpec(string $requestClass): MethodSpecCollection
    {
        $methodSpec = new MethodSpec(
            methodClass: PartialRequestHydrationTestMethod::class,
            requestType: 'POST',
            methodName: 'partialUpdate',
            requestMetadata: new RequestMetadata(
                request: $requestClass,
                allParameters: [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'email', 'type' => 'string'],
                    ['name' => 'name', 'type' => 'string'],
                    ['name' => 'bio', 'type' => 'string'],
                ],
                requiredParameters: [],
                requestGetters: [
                    'id' => 'getId',
                    'email' => 'getEmail',
                    'name' => 'getName',
                    'bio' => 'getBio',
                ],
                requestSetters: [
                    'id' => 'setId',
                    'email' => 'setEmail',
                    'name' => 'setName',
                    'bio' => 'setBio',
                ],
                requestAdders: [],
                validators: [
                    'id' => ['allowsNull' => true, 'type' => 'int'],
                    'email' => ['allowsNull' => true, 'type' => 'string'],
                    'name' => ['allowsNull' => true, 'type' => 'string'],
                    'bio' => ['allowsNull' => true, 'type' => 'string'],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );

        $specCollection = new MethodSpecCollection();
        $specCollection->addMethodSpec(1, 'partialUpdate', $methodSpec);

        return $specCollection;
    }

    private function dispatch(MethodSpecCollection $specCollection, array $params): mixed
    {
        $handler = $this->buildHandler($specCollection);

        $batch = [
            'jsonrpc' => '2.0',
            'method' => 'partialUpdate',
            'params' => $params,
            'id' => '1',
        ];

        $handler->processBatch($batch, 1, 'POST');

        return $this->methodInstance->captured;
    }

    public function testKeyPresentWithValueIsTracked(): void
    {
        $captured = $this->dispatch(
            $this->buildFlatSpec(PartialRequestHydrationTestPartialRequest::class),
            ['id' => 42, 'email' => 'user@example.com'],
        );

        $this->assertInstanceOf(PartialRequestHydrationTestPartialRequest::class, $captured);
        $this->assertTrue($captured->wasProvided('id'));
        $this->assertTrue($captured->wasProvided('email'));
        $this->assertSame(42, $captured->getId());
        $this->assertSame('user@example.com', $captured->getEmail());
    }

    public function testKeyPresentWithExplicitNullIsTracked(): void
    {
        $captured = $this->dispatch(
            $this->buildFlatSpec(PartialRequestHydrationTestPartialRequest::class),
            ['id' => 42, 'bio' => null],
        );

        $this->assertTrue($captured->wasProvided('bio'), 'Field provided as null must be tracked as provided');
        $this->assertNull($captured->getBio());
    }

    public function testAbsentKeyIsNotTracked(): void
    {
        $captured = $this->dispatch(
            $this->buildFlatSpec(PartialRequestHydrationTestPartialRequest::class),
            ['id' => 42],
        );

        $this->assertTrue($captured->wasProvided('id'));
        $this->assertFalse($captured->wasProvided('email'));
        $this->assertFalse($captured->wasProvided('name'));
        $this->assertFalse($captured->wasProvided('bio'));
    }

    public function testProvidedFieldsListMatchesPayload(): void
    {
        $captured = $this->dispatch(
            $this->buildFlatSpec(PartialRequestHydrationTestPartialRequest::class),
            ['id' => 1, 'email' => null, 'name' => 'Joe'],
        );

        $this->assertEqualsCanonicalizing(
            ['id', 'email', 'name'],
            $captured->getProvidedFields(),
        );
    }

    public function testDefaultValueBranchDoesNotTrack(): void
    {
        $methodSpec = new MethodSpec(
            methodClass: PartialRequestHydrationTestMethod::class,
            requestType: 'POST',
            methodName: 'partialUpdate',
            requestMetadata: new RequestMetadata(
                request: PartialRequestHydrationTestPartialRequest::class,
                allParameters: [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'email', 'type' => 'string', 'defaultValue' => 'fallback@example.com'],
                ],
                requiredParameters: [],
                requestGetters: ['id' => 'getId', 'email' => 'getEmail'],
                requestSetters: ['id' => 'setId', 'email' => 'setEmail'],
                requestAdders: [],
                validators: [
                    'id' => ['allowsNull' => true, 'type' => 'int'],
                    'email' => ['allowsNull' => true, 'type' => 'string'],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );

        $specCollection = new MethodSpecCollection();
        $specCollection->addMethodSpec(1, 'partialUpdate', $methodSpec);

        $captured = $this->dispatch($specCollection, ['id' => 1]);

        $this->assertTrue($captured->wasProvided('id'));
        $this->assertFalse(
            $captured->wasProvided('email'),
            'Field filled by defaultValue (not by client) must not count as provided',
        );
        $this->assertSame('fallback@example.com', $captured->getEmail());
    }

    public function testDtoWithoutInterfaceIsUnaffected(): void
    {
        $methodSpec = new MethodSpec(
            methodClass: PartialRequestHydrationTestMethod::class,
            requestType: 'POST',
            methodName: 'partialUpdate',
            requestMetadata: new RequestMetadata(
                request: PartialRequestHydrationTestLegacyRequest::class,
                allParameters: [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'email', 'type' => 'string'],
                ],
                requiredParameters: [],
                requestGetters: ['id' => 'getId', 'email' => 'getEmail'],
                requestSetters: ['id' => 'setId', 'email' => 'setEmail'],
                requestAdders: [],
                validators: [
                    'id' => ['allowsNull' => true, 'type' => 'int'],
                    'email' => ['allowsNull' => true, 'type' => 'string'],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );

        $specCollection = new MethodSpecCollection();
        $specCollection->addMethodSpec(1, 'partialUpdate', $methodSpec);

        $captured = $this->dispatch($specCollection, ['id' => 1, 'email' => null]);

        $this->assertInstanceOf(PartialRequestHydrationTestLegacyRequest::class, $captured);
        $this->assertSame(1, $captured->getId());
        $this->assertNull($captured->getEmail());
        $this->assertNotInstanceOf(PartialRequestInterface::class, $captured);
    }

    public function testNestedDtoTracksProvidedFields(): void
    {
        $methodSpec = new MethodSpec(
            methodClass: PartialRequestHydrationTestMethod::class,
            requestType: 'POST',
            methodName: 'partialUpdate',
            requestMetadata: new RequestMetadata(
                request: PartialRequestHydrationTestNestedRequest::class,
                allParameters: [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'address', 'type' => PartialRequestHydrationTestNestedChild::class],
                ],
                requiredParameters: [],
                requestGetters: ['id' => 'getId', 'address' => 'getAddress'],
                requestSetters: ['id' => 'setId', 'address' => 'setAddress'],
                requestAdders: [],
                validators: [
                    'id' => ['allowsNull' => true, 'type' => 'int'],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );

        $specCollection = new MethodSpecCollection();
        $specCollection->addMethodSpec(1, 'partialUpdate', $methodSpec);

        $captured = $this->dispatch($specCollection, [
            'id' => 1,
            'address' => ['city' => 'Moscow'],
        ]);

        $this->assertTrue($captured->wasProvided('id'));
        $this->assertTrue($captured->wasProvided('address'));

        $address = $captured->getAddress();
        $this->assertInstanceOf(PartialRequestHydrationTestNestedChild::class, $address);
        $this->assertTrue($address->wasProvided('city'));
        $this->assertFalse($address->wasProvided('street'));
        $this->assertSame('Moscow', $address->getCity());
        $this->assertNull($address->getStreet());
    }
}
