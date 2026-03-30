<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Response;

use ReflectionClass;
use ReflectionException;

final readonly class BaseResponse implements OvResponseInterface, BaseJsonResponseInterface
{
    public function __construct(
        private mixed $result,
        private ?string $id = null,
        private string $jsonrpc = '2.0'
    ) {
    }

    /** @noinspection PhpUnused */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /** @noinspection PhpUnused */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /** @noinspection PhpUnused */
    public function getId(): ?string
    {
        return $this->id;
    }
}