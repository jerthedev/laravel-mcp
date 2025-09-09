<?php

namespace JTD\LaravelMCP\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JTD\LaravelMCP\Protocol\Contracts\NotificationHandlerInterface;
use JTD\LaravelMCP\Protocol\NotificationHandler;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for handling MCP notification HTTP endpoints.
 *
 * This controller provides HTTP endpoints for managing MCP notifications,
 * including SSE streaming, subscription management, and notification status.
 */
class NotificationController extends Controller
{
    /**
     * The notification handler instance.
     */
    protected NotificationHandlerInterface $notificationHandler;

    /**
     * Create a new controller instance.
     */
    public function __construct(NotificationHandlerInterface $notificationHandler)
    {
        $this->notificationHandler = $notificationHandler;
    }

    /**
     * Create a Server-Sent Events stream for real-time notifications.
     *
     * @param  Request  $request
     * @return StreamedResponse
     */
    public function stream(Request $request): StreamedResponse
    {
        $clientId = $request->input('client_id', $request->ip() . '_' . time());
        $types = $request->input('types', []);
        
        if (is_string($types)) {
            $types = explode(',', $types);
        }

        // Apply the SSE middleware for proper headers
        $request->headers->set('Accept', 'text/event-stream');

        return $this->notificationHandler->createSseResponse($clientId, $types);
    }

    /**
     * Subscribe a client to notifications.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|string|max:255',
            'types' => 'sometimes|array',
            'types.*' => 'string',
            'filter' => 'sometimes|array',
        ]);

        $clientId = $request->input('client_id');
        $types = $request->input('types', []);
        $filter = $request->input('filter', []);

        $this->notificationHandler->subscribe($clientId, $types);

        if (!empty($filter)) {
            $this->notificationHandler->updateFilter($clientId, $filter);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully subscribed to notifications',
            'client_id' => $clientId,
            'types' => $types,
            'filter' => $filter,
        ]);
    }

    /**
     * Unsubscribe a client from notifications.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|string|max:255',
        ]);

        $clientId = $request->input('client_id');

        $this->notificationHandler->unsubscribe($clientId);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully unsubscribed from notifications',
            'client_id' => $clientId,
        ]);
    }

    /**
     * Send a notification to a specific client.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function notify(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'params' => 'sometimes|array',
            'options' => 'sometimes|array',
        ]);

        $clientId = $request->input('client_id');
        $type = $request->input('type');
        $params = $request->input('params', []);
        $options = $request->input('options', []);

        $notificationId = $this->notificationHandler->notify($clientId, $type, $params, $options);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification sent successfully',
            'notification_id' => $notificationId,
            'client_id' => $clientId,
            'type' => $type,
        ]);
    }

    /**
     * Broadcast a notification to all subscribers.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function broadcast(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|max:255',
            'params' => 'sometimes|array',
            'options' => 'sometimes|array',
        ]);

        $type = $request->input('type');
        $params = $request->input('params', []);
        $options = $request->input('options', []);

        $notificationId = $this->notificationHandler->broadcast($type, $params, $options);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification broadcast successfully',
            'notification_id' => $notificationId,
            'type' => $type,
            'subscriber_count' => count($this->notificationHandler->getActiveSubscriptions()),
        ]);
    }

    /**
     * Get delivery status for a notification.
     *
     * @param  Request  $request
     * @param  string  $notificationId
     * @return JsonResponse
     */
    public function status(Request $request, string $notificationId): JsonResponse
    {
        $status = $this->notificationHandler->getDeliveryStatus($notificationId);

        if ($status === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notification not found',
                'notification_id' => $notificationId,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'notification_id' => $notificationId,
            'delivery_status' => $status,
        ]);
    }

    /**
     * Get active subscriptions.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $subscriptions = $this->notificationHandler->getActiveSubscriptions();

        // Filter out sensitive transport information
        $safeSubscriptions = [];
        foreach ($subscriptions as $clientId => $subscription) {
            $safeSubscriptions[$clientId] = [
                'client_id' => $subscription['client_id'],
                'types' => $subscription['types'],
                'subscribed_at' => $subscription['subscribed_at'],
                'active' => $subscription['active'],
                'has_transport' => isset($subscription['transport']),
            ];
        }

        return response()->json([
            'status' => 'success',
            'subscriptions' => $safeSubscriptions,
            'total_count' => count($safeSubscriptions),
        ]);
    }

    /**
     * Update notification filter for a client.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function updateFilter(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|string|max:255',
            'filter' => 'required|array',
        ]);

        $clientId = $request->input('client_id');
        $filter = $request->input('filter');

        $this->notificationHandler->updateFilter($clientId, $filter);

        return response()->json([
            'status' => 'success',
            'message' => 'Filter updated successfully',
            'client_id' => $clientId,
            'filter' => $filter,
        ]);
    }

    /**
     * Clear pending notifications for a client.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function clearPending(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|string|max:255',
        ]);

        $clientId = $request->input('client_id');
        $clearedCount = $this->notificationHandler->clearPendingNotifications($clientId);

        return response()->json([
            'status' => 'success',
            'message' => 'Pending notifications cleared',
            'client_id' => $clientId,
            'cleared_count' => $clearedCount,
        ]);
    }

    /**
     * Get system statistics about notifications.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $subscriptions = $this->notificationHandler->getActiveSubscriptions();
        $pendingCount = $this->notificationHandler->getPendingNotificationsCount();

        // Calculate subscription statistics
        $typeStats = [];
        foreach ($subscriptions as $subscription) {
            $types = $subscription['types'];
            if (empty($types)) {
                $typeStats['all'] = ($typeStats['all'] ?? 0) + 1;
            } else {
                foreach ($types as $type) {
                    $typeStats[$type] = ($typeStats[$type] ?? 0) + 1;
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'statistics' => [
                'active_subscriptions' => count($subscriptions),
                'pending_notifications' => $pendingCount,
                'subscription_types' => $typeStats,
                'available_notification_types' => [
                    NotificationHandler::TYPE_TOOLS_LIST_CHANGED,
                    NotificationHandler::TYPE_RESOURCES_LIST_CHANGED,
                    NotificationHandler::TYPE_RESOURCES_UPDATED,
                    NotificationHandler::TYPE_PROMPTS_LIST_CHANGED,
                    NotificationHandler::TYPE_LOGGING_MESSAGE,
                    NotificationHandler::TYPE_PROGRESS,
                    NotificationHandler::TYPE_CANCELLED,
                ],
            ],
        ]);
    }
}