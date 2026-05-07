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
 * Opt-in contract for Request DTOs that need PATCH (JSON Merge Patch, RFC 7396) semantics.
 *
 * When a Request DTO implements this interface, the framework records which top-level
 * parameters were actually present in the raw JSON-RPC payload. Service code can then
 * distinguish three states for any field:
 *
 *  - key present with a value     → wasProvided() === true,  getter returns the value
 *  - key present with explicit null → wasProvided() === true,  getter returns null   (= clear)
 *  - key absent from payload      → wasProvided() === false, getter returns default (= leave unchanged)
 *
 * DTOs not implementing this interface are unaffected: tracking is skipped entirely.
 */
interface PartialRequestInterface
{
    /**
     * Marks a top-level field as having been provided in the raw JSON-RPC payload.
     * Called by the framework during request hydration.
     */
    public function markProvided(string $field): void;

    /**
     * Returns true when the field was present in the raw JSON-RPC payload (including null).
     * Returns false when the field was absent.
     */
    public function wasProvided(string $field): bool;

    /**
     * Returns the list of all field names that were present in the raw JSON-RPC payload.
     *
     * @return list<string>
     */
    public function getProvidedFields(): array;
}
