<?php

namespace App\Services;

use App\Models\PushSubscription as PushSubscriptionModel;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushService
{
    public function notifyAll(array $payload): void
    {
        $publicKey = config('webpush.vapid_public_key');
        $privateKey = config('webpush.vapid_private_key');
        if (!$publicKey || !$privateKey) {
            return;
        }

        $subs = PushSubscriptionModel::query()->get();
        if ($subs->isEmpty()) {
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => config('webpush.vapid_subject', config('app.url')),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth);
        $webPush->setDefaultOptions([
            'TTL' => 3600,
        ]);

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

        foreach ($subs as $s) {
            $subscription = Subscription::create([
                'endpoint' => $s->endpoint,
                'publicKey' => $s->public_key,
                'authToken' => $s->auth_token,
                'contentEncoding' => $s->content_encoding ?: 'aesgcm',
            ]);
            $webPush->queueNotification($subscription, $payloadJson);
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $statusCode = $report->getResponse()?->getStatusCode();
            if (in_array($statusCode, [404, 410], true)) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                PushSubscriptionModel::where('endpoint', $endpoint)->delete();
            }
        }
    }
}
