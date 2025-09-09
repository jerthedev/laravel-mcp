# NotificationHandler Implementation

The NotificationHandler provides real-time notification capabilities for the MCP (Model Context Protocol) Laravel package. It supports both Server-Sent Events (SSE) for HTTP transport and direct stdio notifications for MCP client communication.

## Features

### Core Functionality
- **Real-time notifications** using Laravel's event system
- **Server-Sent Events (SSE)** support for HTTP transport
- **Direct stdio notifications** for MCP transport
- **Notification queuing** for asynchronous delivery
- **Delivery status tracking** with success/failure monitoring
- **Subscription management** with client filtering
- **Notification filtering** based on custom criteria

### Supported Notification Types
- `notifications/tools/list_changed` - Tool registry changes
- `notifications/resources/list_changed` - Resource registry changes
- `notifications/resources/updated` - Resource content updates
- `notifications/prompts/list_changed` - Prompt registry changes
- `notifications/message` - Logging and general messages
- `notifications/progress` - Progress updates for long operations
- `notifications/cancelled` - Operation cancellation notifications

## Architecture

### Class Structure
```
NotificationHandler implements NotificationHandlerInterface
├── Event System Integration
├── Transport Management
├── Subscription Management
├── Delivery Tracking
└── SSE Support
```

### Dependencies
- `Illuminate\Contracts\Events\Dispatcher` - Laravel event dispatcher
- `JsonRpcHandlerInterface` - JSON-RPC message creation
- `Queue` (optional) - Asynchronous processing
- `TransportInterface` - Direct transport delivery

## Usage Examples

### Basic Notification Broadcasting
```php
$notificationHandler = new NotificationHandler($eventDispatcher, $jsonRpcHandler);

// Broadcast to all subscribers
$notificationId = $notificationHandler->broadcast(
    NotificationHandler::TYPE_TOOLS_LIST_CHANGED,
    ['tools' => ['calculator', 'file-reader']],
    ['priority' => 'normal']
);
```

### Client Subscription
```php
// Subscribe to specific notification types
$notificationHandler->subscribe('client-1', [
    NotificationHandler::TYPE_RESOURCES_UPDATED,
    NotificationHandler::TYPE_PROGRESS
]);

// Subscribe with transport for direct delivery
$notificationHandler->subscribe('client-2', [], $transport);
```

### Server-Sent Events (SSE)
```php
// Create SSE response for HTTP clients
$response = $notificationHandler->createSseResponse('web-client-1', [
    NotificationHandler::TYPE_PROGRESS,
    NotificationHandler::TYPE_LOGGING_MESSAGE
]);

return $response; // Stream real-time notifications
```

### Notification Filtering
```php
// Set up client-specific filters
$notificationHandler->updateFilter('client-1', [
    'options.priority' => 'high',
    'params.operation' => ['file-processing', 'data-sync']
]);
```

### Queued Processing
```php
$notificationHandler = new NotificationHandler(
    $eventDispatcher,
    $jsonRpcHandler,
    $queue,
    ['queue_notifications' => true]
);

// Notifications will be queued for async processing
$notificationHandler->broadcast('notifications/progress', $data);
```

## Configuration Options

```php
$config = [
    'queue_notifications' => false,           // Enable async processing
    'queue_connection' => 'default',          // Queue connection
    'queue_name' => 'mcp-notifications',      // Queue name
    'delivery_timeout' => 30,                 // Delivery timeout (seconds)
    'max_pending_notifications' => 1000,      // Max pending notifications
    'sse_heartbeat_interval' => 30,           // SSE heartbeat (seconds)
    'enable_delivery_tracking' => true,       // Track delivery status
    'log_notifications' => true,              // Enable notification logging
];
```

## HTTP Endpoints

The package includes a `NotificationController` with the following endpoints:

### SSE Streaming
- `GET /mcp/notifications/stream?client_id=client1&types=progress,message`

### Subscription Management
- `POST /mcp/notifications/subscribe` - Subscribe to notifications
- `POST /mcp/notifications/unsubscribe` - Unsubscribe from notifications
- `GET /mcp/notifications/subscriptions` - Get active subscriptions

### Notification Operations
- `POST /mcp/notifications/notify` - Send targeted notification
- `POST /mcp/notifications/broadcast` - Broadcast to all subscribers
- `GET /mcp/notifications/status/{id}` - Get delivery status

### Management
- `PUT /mcp/notifications/filter` - Update client filter
- `DELETE /mcp/notifications/pending` - Clear pending notifications
- `GET /mcp/notifications/stats` - Get system statistics

## Event System Integration

The NotificationHandler dispatches Laravel events for monitoring and extension:

### Events
- `NotificationBroadcast` - When notification is broadcast
- `NotificationQueued` - When notification is queued
- `NotificationSent` - When notification is sent to transport
- `NotificationDelivered` - When delivery is confirmed
- `NotificationFailed` - When delivery fails

### Automatic Event Listeners
The handler automatically listens for MCP component changes:
- `mcp.tools.registered` → `notifications/tools/list_changed`
- `mcp.resources.registered` → `notifications/resources/list_changed`
- `mcp.resources.updated` → `notifications/resources/updated`
- `mcp.prompts.registered` → `notifications/prompts/list_changed`

## Integration with Transport Layer

### Direct Transport Integration
```php
// Subscribe with transport for immediate delivery
$notificationHandler->subscribe($clientId, [], $stdioTransport);

// Notifications are delivered directly via transport
$notificationHandler->notify($clientId, 'notifications/progress', $data);
```

### SSE for HTTP Transport
```php
// Client connects to SSE endpoint
// Browser: EventSource('/mcp/notifications/stream?client_id=web1')

// Server creates SSE response
$response = $notificationHandler->createSseResponse('web1');

// Real-time notifications streamed to browser
```

## Error Handling and Reliability

### Delivery Tracking
- Track notification status: `pending`, `queued`, `sent`, `delivered`, `failed`
- Per-client delivery status tracking
- Automatic retry for transient failures

### Failure Handling
- Graceful handling of transport failures
- Pending notification queue for offline clients
- Configurable retry logic for queued notifications
- Comprehensive error logging

### Resource Management
- Connection cleanup for SSE clients
- Pending notification limits to prevent memory issues
- Automatic subscription cleanup on disconnect

## Performance Considerations

### Scalability
- Async processing via Laravel queues
- Efficient event-driven architecture
- Minimal memory footprint for SSE connections
- Configurable resource limits

### Optimization
- Lazy evaluation of notification filters
- Batch processing for multiple subscribers
- Transport-specific delivery optimization
- Connection pooling for direct transports

## Testing

The implementation includes comprehensive unit tests covering:
- Notification broadcasting and targeted delivery
- Subscription management and filtering
- SSE response generation
- Queue integration
- Event dispatching
- Error handling scenarios

Run tests with:
```bash
./vendor/bin/phpunit tests/Unit/Protocol/NotificationHandlerTest.php
```

## Future Enhancements

Potential improvements for future versions:
- WebSocket transport support
- Push notification integration
- Notification templating system
- Advanced filtering with custom predicates
- Metrics and monitoring integration
- Clustering support for multi-server deployments