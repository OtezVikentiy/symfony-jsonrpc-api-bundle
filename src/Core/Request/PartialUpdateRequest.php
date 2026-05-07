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
 * Optional convenience base class for partial-update Request DTOs.
 *
 * Combines {@see JsonRpcRequest} (for the recursive toArray() helper) with
 * {@see PartialRequestInterface} + {@see TracksProvidedFieldsTrait} to give
 * JSON Merge Patch (RFC 7396) semantics out of the box.
 *
 * Use this when you want the framework to track which fields were actually
 * present in the JSON-RPC payload, so service code can distinguish
 * "not provided" from "explicitly null" (= clear).
 *
 * Inheritance is optional — applying {@see TracksProvidedFieldsTrait} to any
 * class that implements {@see PartialRequestInterface} works equally well.
 */
abstract class PartialUpdateRequest extends JsonRpcRequest implements PartialRequestInterface
{
    use TracksProvidedFieldsTrait;
}
