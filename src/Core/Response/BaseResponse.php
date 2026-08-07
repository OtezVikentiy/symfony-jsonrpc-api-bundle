<?php

declare(strict_types=1);
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Response;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Serialization\SerialisesThroughPublicGetters;
use SplObjectStorage;

final readonly class BaseResponse implements OvResponseInterface, BaseJsonResponseInterface
{
    use SerialisesThroughPublicGetters;

    public function __construct(
        private mixed $result,
        private mixed $id = null,
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
    public function getId(): mixed
    {
        return $this->id;
    }

    /**
     * @throws JRPCException
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => $this->jsonrpc,
            'result' => $this->normaliseValue($this->result, new SplObjectStorage()),
            'id' => $this->id,
        ];
    }
}
