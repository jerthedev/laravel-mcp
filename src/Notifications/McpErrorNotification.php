<?php

namespace JTD\LaravelMCP\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for MCP errors and critical events.
 *
 * This notification can be sent through multiple channels (mail, database, slack)
 * to alert administrators about MCP errors, failures, or other critical events.
 */
class McpErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The error type or category.
     */
    public string $errorType;

    /**
     * The error message.
     */
    public string $errorMessage;

    /**
     * The MCP method that caused the error.
     */
    public ?string $method;

    /**
     * The request parameters when the error occurred.
     */
    public array $parameters;

    /**
     * Additional context about the error.
     */
    public array $context;

    /**
     * The exception that caused the error (if applicable).
     */
    public ?\Throwable $exception;

    /**
     * The severity level of the error.
     */
    public string $severity;

    /**
     * The timestamp when the error occurred.
     */
    public string $occurredAt;

    /**
     * Create a new notification instance.
     *
     * @param  string  $errorType  The type of error
     * @param  string  $errorMessage  The error message
     * @param  string|null  $method  The MCP method that caused the error
     * @param  array  $parameters  Request parameters
     * @param  array  $context  Additional context
     * @param  \Throwable|null  $exception  The exception that was thrown
     * @param  string  $severity  The severity level (critical, error, warning)
     */
    public function __construct(
        string $errorType,
        string $errorMessage,
        ?string $method = null,
        array $parameters = [],
        array $context = [],
        ?\Throwable $exception = null,
        string $severity = 'error'
    ) {
        $this->errorType = $errorType;
        $this->errorMessage = $errorMessage;
        $this->method = $method;
        $this->parameters = $parameters;
        $this->context = $context;
        $this->exception = $exception;
        $this->severity = $severity;
        $this->occurredAt = $this->getCurrentTimestamp();
    }

    /**
     * Get the current timestamp.
     */
    protected function getCurrentTimestamp(): string
    {
        // Use now() if available (Laravel is bootstrapped)
        if (function_exists('now')) {
            return now()->toISOString();
        }

        // Fallback to native PHP
        return (new \DateTime)->format('c');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        // Add mail channel for critical errors
        if ($this->severity === 'critical') {
            $channels[] = 'mail';
        }

        // Add Slack if configured
        if (config('laravel-mcp.notifications.slack.enabled', false)) {
            $channels[] = 'slack';
        }

        // Allow customization via notifiable preferences
        if (method_exists($notifiable, 'mcpNotificationChannels')) {
            return $notifiable->mcpNotificationChannels($this->severity);
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->error()
            ->subject($this->getEmailSubject())
            ->greeting($this->getEmailGreeting())
            ->line($this->errorMessage);

        if ($this->method) {
            $message->line("**MCP Method:** {$this->method}");
        }

        if (! empty($this->parameters)) {
            $message->line('**Parameters:**')
                ->line('```'.json_encode($this->parameters, JSON_PRETTY_PRINT).'```');
        }

        if ($this->exception) {
            $message->line('**Exception:** '.get_class($this->exception))
                ->line('**File:** '.$this->exception->getFile().':'.$this->exception->getLine());
        }

        $message->line('**Occurred At:** '.$this->occurredAt);

        // Add action button if appropriate
        if ($url = $this->getActionUrl()) {
            $message->action('View Details', $url);
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            'error_type' => $this->errorType,
            'error_message' => $this->errorMessage,
            'method' => $this->method,
            'parameters' => $this->parameters,
            'context' => $this->context,
            'exception' => $this->exception ? [
                'class' => get_class($this->exception),
                'message' => $this->exception->getMessage(),
                'file' => $this->exception->getFile(),
                'line' => $this->exception->getLine(),
            ] : null,
            'severity' => $this->severity,
            'occurred_at' => $this->occurredAt,
        ];
    }

    /**
     * Get the database representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toDatabase($notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toSlack($notifiable): SlackMessage
    {
        $message = (new SlackMessage)
            ->from(config('laravel-mcp.notifications.slack.username', 'MCP Error Bot'))
            ->to(config('laravel-mcp.notifications.slack.channel', '#mcp-errors'))
            ->content($this->getSlackContent());

        // Set color based on severity
        $color = match ($this->severity) {
            'critical' => 'danger',
            'error' => 'warning',
            'warning' => 'warning',
            default => 'good',
        };

        $message->attachment(function ($attachment) use ($color) {
            $attachment->title($this->errorType)
                ->color($color)
                ->fields($this->getSlackFields());

            if ($this->exception) {
                $attachment->field('Exception', get_class($this->exception), false)
                    ->field('Location', $this->exception->getFile().':'.$this->exception->getLine(), false);
            }

            $attachment->timestamp(now());
        });

        return $message;
    }

    /**
     * Get the email subject.
     */
    protected function getEmailSubject(): string
    {
        $prefix = match ($this->severity) {
            'critical' => '[CRITICAL]',
            'error' => '[ERROR]',
            'warning' => '[WARNING]',
            default => '[MCP]',
        };

        return "{$prefix} MCP Error: {$this->errorType}";
    }

    /**
     * Get the email greeting.
     */
    protected function getEmailGreeting(): string
    {
        return match ($this->severity) {
            'critical' => 'Critical MCP Error!',
            'error' => 'MCP Error Detected',
            'warning' => 'MCP Warning',
            default => 'MCP Notification',
        };
    }

    /**
     * Get the Slack message content.
     */
    protected function getSlackContent(): string
    {
        $emoji = match ($this->severity) {
            'critical' => ':rotating_light:',
            'error' => ':x:',
            'warning' => ':warning:',
            default => ':information_source:',
        };

        return "{$emoji} MCP {$this->severity}: {$this->errorMessage}";
    }

    /**
     * Get the Slack attachment fields.
     */
    protected function getSlackFields(): array
    {
        $fields = [
            'Error Type' => $this->errorType,
            'Severity' => ucfirst($this->severity),
        ];

        if ($this->method) {
            $fields['MCP Method'] = $this->method;
        }

        if (! empty($this->parameters)) {
            $fields['Parameters'] = substr(json_encode($this->parameters), 0, 200).'...';
        }

        $fields['Occurred At'] = $this->occurredAt;

        return $fields;
    }

    /**
     * Get the action URL for viewing details.
     */
    protected function getActionUrl(): ?string
    {
        if (config('laravel-mcp.notifications.dashboard_url')) {
            return config('laravel-mcp.notifications.dashboard_url').'/errors/'.urlencode($this->occurredAt);
        }

        return null;
    }

    /**
     * Determine if the notification should be sent.
     *
     * @param  mixed  $notifiable
     * @param  string  $channel
     */
    public function shouldSend($notifiable, $channel): bool
    {
        // Check if notifications are enabled
        if (! config('laravel-mcp.notifications.enabled', true)) {
            return false;
        }

        // Check severity threshold
        $threshold = config('laravel-mcp.notifications.severity_threshold', 'warning');
        $severityLevels = ['info' => 0, 'warning' => 1, 'error' => 2, 'critical' => 3];

        if (($severityLevels[$this->severity] ?? 0) < ($severityLevels[$threshold] ?? 0)) {
            return false;
        }

        // Check channel-specific settings
        if ($channel === 'slack' && ! config('laravel-mcp.notifications.slack.enabled', false)) {
            return false;
        }

        return true;
    }

    /**
     * Get the tags for queued notifications.
     */
    public function tags(): array
    {
        return [
            'mcp-notification',
            'mcp-error',
            $this->severity,
            $this->errorType,
        ];
    }
}
