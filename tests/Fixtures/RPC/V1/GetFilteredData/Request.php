<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData;

final class Request
{
    private Filter $filter;
    private int $limit;
    private int $offset;

    public function getFilter(): Filter
    {
        return $this->filter;
    }

    public function setFilter(Filter $filter): Request
    {
        $this->filter = $filter;
        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): Request
    {
        $this->limit = $limit;
        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): Request
    {
        $this->offset = $offset;
        return $this;
    }
}