<?php

namespace JTD\LaravelMCP\Integration;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Inject
{
    public function __construct(public ?string $service = null) {}
}
