<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Request;

/**
 * Default implementation of {@see PartialRequestInterface}.
 *
 * Stores provided field names in an associative array (used as a set for O(1) lookup).
 */
trait TracksProvidedFieldsTrait
{
    /** @var array<string, true> */
    private array $providedFields = [];

    public function markProvided(string $field): void
    {
        $this->providedFields[$field] = true;
    }

    public function wasProvided(string $field): bool
    {
        return isset($this->providedFields[$field]);
    }

    /**
     * @return list<string>
     */
    public function getProvidedFields(): array
    {
        return array_keys($this->providedFields);
    }
}
