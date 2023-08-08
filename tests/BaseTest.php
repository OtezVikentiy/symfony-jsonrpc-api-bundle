<?php

namespace OV\JsonRPCAPIBundle\Tests;

use OV\JsonRPCAPIBundle\Controller\ApiController;
use OV\JsonRPCAPIBundle\DependencyInjection\{MethodSpec, MethodSpecCollection};
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BaseTest extends TestCase
{
    public function testCreateRequest()
    {
        $request = new TestRequest(1);

        $this->assertSame(1, $request->getId());
    }

    public function testController()
    {
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'jsonrpc' => '2.0',
                'method' => 'test',
                'params' => [
                    'title' => 'AZAZAZA',
                ],
                'id' => '1',
            ]);

        $methodSpecCollection = $this->createMock(MethodSpecCollection::class);
        $methodSpec = new MethodSpec(
            TestMethod::class,
            ['id', 'title'],
            ['id'],
            TestRequest::class,
            ['id' => 'setId', 'title' => 'setTitle'],
            ['id' => 'int', 'title' => 'string']
        );
        $methodSpecCollection
            ->expects($this->once())
            ->method('getMethodSpec')
            ->willReturn($methodSpec);

        $validator = $this->createMock(ValidatorInterface::class);
        $violations = new ConstraintViolationList();
        $validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $container = $this->createMock(ServiceLocator::class);
        $container
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);
        $serializer = $this->createMock(TraceableSerializer::class);
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->willReturn(json_encode([
                'title' => 'AZAZAZA',
                'success' => true,
            ]));
        $container
            ->expects($this->once())
            ->method('get')
            ->willReturn($serializer);

        $controller = new ApiController();
        $controller->setContainer($container);

        $result = $controller->index($request, $methodSpecCollection, $validator);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode([
            'title' => 'AZAZAZA',
            'success' => true,
        ]), $result->getContent());
    }
}