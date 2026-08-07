<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Security;

use ArgumentCountError;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\Tests\Security\Fixtures\NestedDto;
use OV\JsonRPCAPIBundle\Tests\Security\Fixtures\PrivateSetterDto;
use OV\JsonRPCAPIBundle\Tests\Security\Fixtures\RequiredConstructorArgDto;
use OV\JsonRPCAPIBundle\Tests\Security\Fixtures\ScalarConstructorDto;
use OV\JsonRPCAPIBundle\Tests\Security\Fixtures\ScalarSetterDto;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
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

    public function testWeakTypeCoercionIsRejected(): void
    {
        // This is the actual BC-break the 5.0 commit message promises: a
        // numeric string ("42") sent for a scalar `int` DTO setter must be
        // rejected as -32602, not silently coerced the way weak typing used
        // to allow. $values is passed as a plain array here, which already
        // matches prepareParametersFromClass()'s `array|string` parameter
        // type exactly, so no coercion happens on the call boundary itself
        // (unlike ReflectionMethod::invoke() with a mismatched scalar,
        // which coerces regardless of strict_types -- see
        // testNestedDtoAsRawScalarIsRejected below for that separate case).
        // The TypeError is raised inside ScalarSetterDto::setAmount(int)
        // and is expected to be caught and converted by
        // prepareParametersFromClass() itself.
        $handler = $this->buildHandler();

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_PARAMS);

        $this->invokePrepare($handler, ScalarSetterDto::class, ['amount' => '42']);
    }

    public function testNestedDtoAsRawScalarIsRejected(): void
    {
        // Distinct from the case above: here the scalar mismatch is inside
        // prepareParametersFromClass()'s own is_string($values) branch
        // (`new $class($values)`), reached when a caller passes a bare
        // string for a field whose type is a DTO with an incompatible
        // constructor -- not a mismatched setter argument inside an
        // already-hydrated object.
        $handler = $this->buildHandler();

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_PARAMS);

        $this->invokePrepare($handler, ScalarConstructorDto::class, 'not-a-number');
    }

    public function testDtoWithRequiredConstructorArgIsRejected(): void
    {
        // Deliberately NOT converted to JRPCException, unlike the is_string
        // branch above. The array-values path always instantiates via
        // `new $class()` with no arguments; if the DTO's own constructor
        // requires one, that's a defect in how the developer declared the
        // DTO, not something any client input could trigger -- it fails
        // the same way for every request regardless of payload. Converting
        // it to JRPCException would make ErrorSanitizer::sanitize() return
        // it before logging (JRPCException short-circuits before the
        // logger call), silently swallowing the only stack trace that
        // could lead a developer to the misconfigured DTO. So this stays
        // an uncaught ArgumentCountError (a TypeError subtype), reported
        // as -32603 and logged, on purpose.
        $handler = $this->buildHandler();

        $this->expectException(ArgumentCountError::class);

        $this->invokePrepare($handler, RequiredConstructorArgDto::class, ['requiredArg' => 5]);
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

    private function invokePrepare(RequestHandler $handler, string $class, array|string $values): object
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
        $responseService = new ResponseService($headersPreparer, new ErrorSanitizer());

        return new RequestHandler(
            $security,
            new MethodSpecCollection(),
            $validator,
            $headersPreparer,
            $this->createMock(ServiceLocator::class),
            $responseService,
            new NullJsonRpcCallLogger(),
            maxDtoDepth: $maxDtoDepth,
            maxArrayParamSize: $maxArrayParamSize,
        );
    }
}
