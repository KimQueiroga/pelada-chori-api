<?php

namespace App\Services;

use App\Models\PushSubscription as PushSubscriptionModel;
use Illuminate\Support\Facades\Log;
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
        if (!$this->isValidVapidKey($publicKey, 65) || !$this->isValidVapidKey($privateKey, 32)) {
            Log::warning('WebPush: VAPID key inválida. Notificação ignorada.');
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

        try {
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
                    'contentEncoding' => $s->content_encoding ?: 'aes128gcm',
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
        } catch (\Throwable $e) {
            Log::warning('WebPush falhou: ' . $e->getMessage());
        }
    }

    private function isValidVapidKey(string $key, int $expectedLen): bool
    {
        $decoded = $this->base64UrlDecode($key);
        if ($decoded === null) return false;
        return strlen($decoded) === $expectedLen;
    }

    private function base64UrlDecode(string $input): ?string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($input, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
