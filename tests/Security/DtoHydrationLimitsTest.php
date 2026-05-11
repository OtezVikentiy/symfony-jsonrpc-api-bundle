<?php

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\Tests\Security\Fixtures\NestedDto;
use OV\JsonRPCAPIBundle\Tests\Security\Fixtures\PrivateSetterDto;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DtoHydrationLimitsTest extends TestCase
{
    public function testNestingBeyondDepthLimitIsRejected(): void
    {
        $handler = $this->buildHandler(maxDtoDepth: 3);
        $payload = $this->nest(5);

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_PARAMS);

        $this->invokePrepare($handler, NestedDto::class, $payload);
    }

    public function testNestingAtDepthLimitIsAccepted(): void
    {
        $handler = $this->buildHandler(maxDtoDepth: 10);
        $payload = $this->nest(3);

        $instance = $this->invokePrepare($handler, NestedDto::class, $payload);

        $this->assertInstanceOf(NestedDto::class, $instance);
        $this->assertInstanceOf(NestedDto::class, $instance->getChild());
    }

    public function testPrivateSetterIsRejected(): void
    {
        $handler = $this->buildHandler();

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_PARAMS);

        $this->invokePrepare($handler, PrivateSetterDto::class, ['secret' => 'leak']);
    }

    private function nest(int $depth): array
    {
        $payload = ['child' => []];
        $cursor = &$payload;
        for ($i = 1; $i < $depth; $i++) {
            $cursor['child'] = ['child' => []];
            $cursor = &$cursor['child'];
        }

        return $payload;
    }

    private function invokePrepare(RequestHandler $handler, string $class, array $values): object
    {
        $ref = new ReflectionMethod(RequestHandler::class, 'prepareParametersFromClass');

        return $ref->invoke($handler, $class, $values);
    }

    private function buildHandler(int $maxDtoDepth = 10, int $maxArrayParamSize = 1000): RequestHandler
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $headersPreparer = new HeadersPreparer(['*']);
        $responseService = new ResponseService($headersPreparer);

        return new RequestHandler(
            $security,
            new MethodSpecCollection(),
            $validator,
            $headersPreparer,
            $this->createMock(Container::class),
            $responseService,
            maxDtoDepth: $maxDtoDepth,
            maxArrayParamSize: $maxArrayParamSize,
        );
    }
}
