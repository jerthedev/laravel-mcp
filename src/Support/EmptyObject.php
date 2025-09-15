<?php

namespace JTD\LaravelMCP\Support;

/**
 * Empty object that always serializes to {} in JSON
 */
class EmptyObject implements \JsonSerializable
{
    public function jsonSerialize(): object
    {
        return new \stdClass();
    }
}