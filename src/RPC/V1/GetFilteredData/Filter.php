<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData;

class Filter
{
    private string $title;
    private int $id;
    private bool $finished;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): Filter
    {
        $this->title = $title;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): Filter
    {
        $this->id = $id;
        return $this;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function setFinished(bool $finished): Filter
    {
        $this->finished = $finished;
        return $this;
    }
}