<?php

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/*
 * Web Push is deliberately best-effort.
 * A delivery failure must never affect the gas-slip workflow
 * or the normal in-app notification record.
 */
function sendWebPushToUser(
    mysqli $conn,
    int $userId,
    string $title,
    string $message,
    array $data = []
): bool
{
    if ($userId <= 0) {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration / Composer
    |--------------------------------------------------------------------------
    */

    $configPath = __DIR__ . '/web_push_config.php';
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

    if (!is_file($configPath) || !is_file($autoloadPath)) {
        error_log(
            '[WEB PUSH] CONFIG ERROR | User: ' . $userId .
            ' | Config exists: ' . (is_file($configPath) ? 'YES' : 'NO') .
            ' | Autoload exists: ' . (is_file($autoloadPath) ? 'YES' : 'NO')
        );

        return false;
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | Load VAPID Configuration
        |--------------------------------------------------------------------------
        */

        $config = require $configPath;

        if (
            !is_array($config) ||
            empty($config['subject']) ||
            empty($config['public_key']) ||
            empty($config['private_key'])
        ) {
            error_log(
                '[WEB PUSH] INVALID VAPID CONFIG | User: ' . $userId
            );

            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Composer Autoloader
        |--------------------------------------------------------------------------
        */

        require_once $autoloadPath;

        /*
        |--------------------------------------------------------------------------
        | Get User Push Subscriptions
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            'SELECT endpoint, p256dh_key, auth_key
             FROM push_subscriptions
             WHERE user_id = ?'
        );

        if (!$stmt) {
            error_log(
                '[WEB PUSH] DATABASE ERROR | User: ' . $userId .
                ' | Unable to prepare subscription query'
            );

            return false;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();

        $result = $stmt->get_result();

        $subscriptions = [];

        while ($row = $result->fetch_assoc()) {
            $subscriptions[] = $row;
        }

        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | No Subscription
        |--------------------------------------------------------------------------
        */

        if (empty($subscriptions)) {
            error_log(
                '[WEB PUSH] NO SUBSCRIPTION | User: ' . $userId
            );

            // No subscription is not considered a workflow failure.
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Build Notification Payload
        |--------------------------------------------------------------------------
        */

        $payload = json_encode([
            'title' => mb_substr($title, 0, 160),
            'body' => mb_substr($message, 0, 500),
            'url' => isset($data['url'])
                ? (string) $data['url']
                : '',
            'gas_slip_id' => isset($data['gas_slip_id'])
                ? (int) $data['gas_slip_id']
                : 0,
            'type' => isset($data['type'])
                ? mb_substr((string) $data['type'], 0, 64)
                : '',
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            error_log(
                '[WEB PUSH] PAYLOAD ERROR | User: ' . $userId
            );

            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Initialize Web Push
        |--------------------------------------------------------------------------
        */

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $config['subject'],
                'publicKey' => $config['public_key'],
                'privateKey' => $config['private_key'],
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Queue Notifications
        |--------------------------------------------------------------------------
        */

        foreach ($subscriptions as $subscription) {

            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription['endpoint'],
                    'keys' => [
                        'p256dh' => $subscription['p256dh_key'],
                        'auth' => $subscription['auth_key'],
                    ],
                ]),
                $payload
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Send Notifications / Diagnostic Logging
        |--------------------------------------------------------------------------
        */

        $hasFailure = false;

        foreach ($webPush->flush() as $report) {

            $endpoint = $report->getEndpoint();

            /*
            |--------------------------------------------------------------------------
            | Successful Delivery
            |--------------------------------------------------------------------------
            */

            if ($report->isSuccess()) {

                error_log(
                    '[WEB PUSH] SUCCESS' .
                    ' | User: ' . $userId .
                    ' | Endpoint: ' . $endpoint
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Failed Delivery
            |--------------------------------------------------------------------------
            */

            $hasFailure = true;

            error_log(
                '[WEB PUSH] FAILED' .
                ' | User: ' . $userId .
                ' | Endpoint: ' . $endpoint .
                ' | Reason: ' . $report->getReason()
            );

            /*
            |--------------------------------------------------------------------------
            | Remove Expired Subscription
            |--------------------------------------------------------------------------
            */

            if ($report->isSubscriptionExpired()) {

                error_log(
                    '[WEB PUSH] EXPIRED SUBSCRIPTION' .
                    ' | User: ' . $userId .
                    ' | Removing endpoint from database'
                );

                $delete = $conn->prepare(
                    'DELETE FROM push_subscriptions
                     WHERE user_id = ?
                     AND endpoint = ?'
                );

                if ($delete) {
                    $delete->bind_param(
                        'is',
                        $userId,
                        $endpoint
                    );

                    $delete->execute();
                    $delete->close();
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Final Result
        |--------------------------------------------------------------------------
        */

        if ($hasFailure) {
            error_log(
                '[WEB PUSH] COMPLETED WITH FAILURE(S)' .
                ' | User: ' . $userId
            );
        }

        return true;

    } catch (Throwable $exception) {

        /*
        |--------------------------------------------------------------------------
        | Unexpected Exception
        |--------------------------------------------------------------------------
        */

        error_log(
            '[WEB PUSH] EXCEPTION' .
            ' | User: ' . $userId .
            ' | Message: ' . $exception->getMessage()
        );

        return false;
    }
}