<?php

namespace JTD\LaravelMCP\Exceptions;

class CachedResultException extends \Exception
{
    private mixed $cachedResult;

    public function __construct(mixed $cachedResult)
    {
        parent::__construct('Cached result available');
        $this->cachedResult = $cachedResult;
    }

    public function getCachedResult(): mixed
    {
        return $this->cachedResult;
    }
}
