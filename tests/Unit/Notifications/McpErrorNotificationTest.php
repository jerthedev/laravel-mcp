<?php

namespace JTD\LaravelMCP\Tests\Unit\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use JTD\LaravelMCP\Notifications\McpErrorNotification;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for McpErrorNotification.
 *
 * @ticket LARAVELINTEGRATION-022
 *
 * @epic Laravel Integration
 *
 * @sprint Sprint-3
 *
 * @covers \JTD\LaravelMCP\Notifications\McpErrorNotification
 */
#[Group('ticket-022')]
#[Group('notifications')]
#[Group('unit')]
class McpErrorNotificationTest extends TestCase
{
    #[Test]
    public function it_creates_notification_with_required_parameters(): void
    {
        $notification = new McpErrorNotification(
            'TestError',
            'An error occurred during processing'
        );

        $this->assertEquals('TestError', $notification->errorType);
        $this->assertEquals('An error occurred during processing', $notification->errorMessage);
        $this->assertNull($notification->method);
        $this->assertEmpty($notification->parameters);
        $this->assertEmpty($notification->context);
        $this->assertNull($notification->exception);
        $this->assertEquals('error', $notification->severity);
        $this->assertNotEmpty($notification->occurredAt);
    }

    #[Test]
    public function it_creates_notification_with_all_parameters(): void
    {
        $exception = new \Exception('Test exception');

        $notification = new McpErrorNotification(
            'CriticalError',
            'Critical system failure',
            'tools/calculator',
            ['operation' => 'divide', 'a' => 1, 'b' => 0],
            ['request_id' => '123'],
            $exception,
            'critical'
        );

        $this->assertEquals('CriticalError', $notification->errorType);
        $this->assertEquals('Critical system failure', $notification->errorMessage);
        $this->assertEquals('tools/calculator', $notification->method);
        $this->assertEquals(['operation' => 'divide', 'a' => 1, 'b' => 0], $notification->parameters);
        $this->assertEquals(['request_id' => '123'], $notification->context);
        $this->assertSame($exception, $notification->exception);
        $this->assertEquals('critical', $notification->severity);
    }

    #[Test]
    public function it_determines_notification_channels_based_on_severity(): void
    {
        $notifiable = new \stdClass;

        // Regular error - database only
        $errorNotification = new McpErrorNotification('Error', 'Test', null, [], [], null, 'error');
        $channels = $errorNotification->via($notifiable);
        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);

        // Critical error - database and mail
        $criticalNotification = new McpErrorNotification('Critical', 'Test', null, [], [], null, 'critical');
        $channels = $criticalNotification->via($notifiable);
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    #[Test]
    public function it_creates_mail_message(): void
    {
        $exception = new \Exception('Test exception');
        $notification = new McpErrorNotification(
            'TestError',
            'Error message',
            'tools/test',
            ['param' => 'value'],
            [],
            $exception,
            'error'
        );

        $notifiable = new \stdClass;
        $notifiable->email = 'test@example.com';

        $message = $notification->toMail($notifiable);

        $this->assertInstanceOf(MailMessage::class, $message);
        $this->assertEquals('[ERROR] MCP Error: TestError', $message->subject);
        $this->assertContains('Error message', $message->introLines);
        $this->assertStringContainsString('tools/test', implode(' ', $message->introLines));
    }

    #[Test]
    public function it_creates_array_representation(): void
    {
        $exception = new \Exception('Test exception');
        $notification = new McpErrorNotification(
            'TestError',
            'Error message',
            'tools/test',
            ['param' => 'value'],
            ['context' => 'test'],
            $exception,
            'warning'
        );

        $notifiable = new \stdClass;
        $array = $notification->toArray($notifiable);

        $this->assertIsArray($array);
        $this->assertEquals('TestError', $array['error_type']);
        $this->assertEquals('Error message', $array['error_message']);
        $this->assertEquals('tools/test', $array['method']);
        $this->assertEquals(['param' => 'value'], $array['parameters']);
        $this->assertEquals(['context' => 'test'], $array['context']);
        $this->assertEquals('warning', $array['severity']);
        $this->assertNotEmpty($array['occurred_at']);

        $this->assertIsArray($array['exception']);
        $this->assertEquals(\Exception::class, $array['exception']['class']);
        $this->assertEquals('Test exception', $array['exception']['message']);
        $this->assertNotEmpty($array['exception']['file']);
        $this->assertIsInt($array['exception']['line']);
    }

    #[Test]
    public function it_creates_slack_message(): void
    {
        $notification = new McpErrorNotification(
            'CriticalError',
            'System is down',
            'tools/critical',
            ['action' => 'process'],
            [],
            null,
            'critical'
        );

        $notifiable = new \stdClass;
        $message = $notification->toSlack($notifiable);

        $this->assertInstanceOf(SlackMessage::class, $message);
        $this->assertStringContainsString('System is down', $message->content);
    }

    #[Test]
    public function it_determines_if_notification_should_be_sent(): void
    {
        config(['laravel-mcp.notifications.enabled' => true]);
        config(['laravel-mcp.notifications.severity_threshold' => 'warning']);

        $notifiable = new \stdClass;

        // Critical - should send
        $critical = new McpErrorNotification('Error', 'Test', null, [], [], null, 'critical');
        $this->assertTrue($critical->shouldSend($notifiable, 'database'));

        // Error - should send
        $error = new McpErrorNotification('Error', 'Test', null, [], [], null, 'error');
        $this->assertTrue($error->shouldSend($notifiable, 'database'));

        // Warning - should send (equals threshold)
        $warning = new McpErrorNotification('Error', 'Test', null, [], [], null, 'warning');
        $this->assertTrue($warning->shouldSend($notifiable, 'database'));

        // Info - should not send (below threshold)
        $info = new McpErrorNotification('Error', 'Test', null, [], [], null, 'info');
        $this->assertFalse($info->shouldSend($notifiable, 'database'));

        // Test with notifications disabled
        config(['laravel-mcp.notifications.enabled' => false]);
        $this->assertFalse($critical->shouldSend($notifiable, 'database'));
    }

    #[Test]
    public function it_checks_slack_channel_configuration(): void
    {
        $notification = new McpErrorNotification('Error', 'Test');
        $notifiable = new \stdClass;

        // Slack disabled
        config(['laravel-mcp.notifications.enabled' => true]);
        config(['laravel-mcp.notifications.slack.enabled' => false]);
        $this->assertFalse($notification->shouldSend($notifiable, 'slack'));

        // Slack enabled
        config(['laravel-mcp.notifications.slack.enabled' => true]);
        $this->assertTrue($notification->shouldSend($notifiable, 'slack'));
    }

    #[Test]
    public function it_has_correct_tags(): void
    {
        $notification = new McpErrorNotification(
            'ValidationError',
            'Validation failed',
            null,
            [],
            [],
            null,
            'warning'
        );

        $tags = $notification->tags();

        $this->assertIsArray($tags);
        $this->assertContains('mcp-notification', $tags);
        $this->assertContains('mcp-error', $tags);
        $this->assertContains('warning', $tags);
        $this->assertContains('ValidationError', $tags);
    }

    #[Test]
    public function it_generates_correct_email_subject_based_on_severity(): void
    {
        $reflection = new \ReflectionClass(McpErrorNotification::class);
        $method = $reflection->getMethod('getEmailSubject');
        $method->setAccessible(true);

        $critical = new McpErrorNotification('Error', 'Test', null, [], [], null, 'critical');
        $this->assertEquals('[CRITICAL] MCP Error: Error', $method->invoke($critical));

        $error = new McpErrorNotification('TestError', 'Test', null, [], [], null, 'error');
        $this->assertEquals('[ERROR] MCP Error: TestError', $method->invoke($error));

        $warning = new McpErrorNotification('Warning', 'Test', null, [], [], null, 'warning');
        $this->assertEquals('[WARNING] MCP Error: Warning', $method->invoke($warning));
    }

    #[Test]
    public function it_generates_correct_email_greeting_based_on_severity(): void
    {
        $reflection = new \ReflectionClass(McpErrorNotification::class);
        $method = $reflection->getMethod('getEmailGreeting');
        $method->setAccessible(true);

        $critical = new McpErrorNotification('Error', 'Test', null, [], [], null, 'critical');
        $this->assertEquals('Critical MCP Error!', $method->invoke($critical));

        $error = new McpErrorNotification('Error', 'Test', null, [], [], null, 'error');
        $this->assertEquals('MCP Error Detected', $method->invoke($error));

        $warning = new McpErrorNotification('Error', 'Test', null, [], [], null, 'warning');
        $this->assertEquals('MCP Warning', $method->invoke($warning));
    }

    #[Test]
    public function it_formats_slack_content_with_correct_emoji(): void
    {
        $reflection = new \ReflectionClass(McpErrorNotification::class);
        $method = $reflection->getMethod('getSlackContent');
        $method->setAccessible(true);

        $critical = new McpErrorNotification('Error', 'Critical issue', null, [], [], null, 'critical');
        $content = $method->invoke($critical);
        $this->assertStringContainsString(':rotating_light:', $content);
        $this->assertStringContainsString('critical', $content);
        $this->assertStringContainsString('Critical issue', $content);

        $error = new McpErrorNotification('Error', 'Error occurred', null, [], [], null, 'error');
        $content = $method->invoke($error);
        $this->assertStringContainsString(':x:', $content);

        $warning = new McpErrorNotification('Error', 'Warning', null, [], [], null, 'warning');
        $content = $method->invoke($warning);
        $this->assertStringContainsString(':warning:', $content);
    }
}
