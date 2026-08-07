<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;
use OV\JsonRPCAPIBundle\Core\Response\BaseResponse;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RecursiveArrayRequest extends JsonRpcRequest
{
    private array $data;

    public function __construct()
    {
        $data = ['k' => 1];
        $data['self'] = &$data;
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }
}

/**
 * Cycle detection recognises a repeated object by identity, which arrays do not have - they are
 * values, and `$a['self'] = &$a` produces a structure SplObjectStorage cannot see. Both directions
 * used to recurse into a segmentation fault: the worker died, the caller got a dropped connection,
 * and no sanitiser ran because a stack overflow is not a catchable error. A depth bound covers what
 * identity cannot, and covers unbounded-but-acyclic graphs at the same time.
 *
 * Every case runs in its own process: a regression here crashes the interpreter rather than failing.
 */
final class RecursiveArraySerializationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testSelfReferencingArrayInAResponseRaisesAnError(): void
    {
        $data = ['k' => 1];
        $data['self'] = &$data;

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INTERNAL_ERROR);

        (new BaseResponse(result: $data, id: 1))->toArray();
    }

    #[RunInSeparateProcess]
    public function testSelfReferencingArrayInARequestRaisesAnError(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INTERNAL_ERROR);

        (new RecursiveArrayRequest())->toArray();
    }

    #[RunInSeparateProcess]
    public function testDeepButFiniteArrayIsRejectedRatherThanExhaustingTheStack(): void
    {
        $deep = 'leaf';
        for ($level = 0; $level < 500; ++$level) {
            $deep = ['down' => $deep];
        }

        $this->expectException(JRPCException::class);

        (new BaseResponse(result: $deep, id: 1))->toArray();
    }

    public function testOrdinaryNestingIsUnaffected(): void
    {
        $result = (new BaseResponse(result: ['a' => ['b' => ['c' => 'leaf']]], id: 1))->toArray();

        self::assertSame(['a' => ['b' => ['c' => 'leaf']]], $result['result']);
    }
}
