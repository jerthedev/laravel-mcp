<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AsyncRequestCompleted
{
    use Dispatchable, SerializesModels;

    public string $requestId;

    public mixed $result;

    public \DateTime $timestamp;

    public function __construct(string $requestId, mixed $result)
    {
        $this->requestId = $requestId;
        $this->result = $result;
        $this->timestamp = now();
    }
}
