<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AsyncRequestFailed
{
    use Dispatchable, SerializesModels;

    public string $requestId;

    public string $error;

    public int $attempts;

    public \DateTime $timestamp;

    public function __construct(string $requestId, string $error, int $attempts)
    {
        $this->requestId = $requestId;
        $this->error = $error;
        $this->attempts = $attempts;
        $this->timestamp = now();
    }
}
