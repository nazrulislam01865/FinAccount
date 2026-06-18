<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PusherChannelsService
{
    public function enabled(): bool
    {
        return filled(config('services.pusher.key'))
            && filled(config('services.pusher.secret'))
            && filled(config('services.pusher.app_id'))
            && filled(config('services.pusher.cluster'));
    }

    /** @return array{auth: string} */
    public function authenticate(string $socketId, string $channelName): array
    {
        $signature = hash_hmac(
            'sha256',
            $socketId.':'.$channelName,
            (string) config('services.pusher.secret')
        );

        return [
            'auth' => config('services.pusher.key').':'.$signature,
        ];
    }

    public function triggerUser(int $userId, string $event, array $payload): bool
    {
        return $this->trigger('private-hisebghor.user.'.$userId, $event, $payload);
    }

    public function trigger(string $channel, string $event, array $payload): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $body = json_encode([
            'name' => $event,
            'channels' => [$channel],
            'data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            return false;
        }

        $path = '/apps/'.rawurlencode((string) config('services.pusher.app_id')).'/events';
        $query = [
            'auth_key' => (string) config('services.pusher.key'),
            'auth_timestamp' => (string) time(),
            'auth_version' => '1.0',
            'body_md5' => md5($body),
        ];
        ksort($query);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $query['auth_signature'] = hash_hmac(
            'sha256',
            "POST\n{$path}\n{$queryString}",
            (string) config('services.pusher.secret')
        );

        $cluster = trim((string) config('services.pusher.cluster'));
        $host = trim((string) config('services.pusher.host'));
        $baseUrl = $host !== '' ? rtrim($host, '/') : "https://api-{$cluster}.pusher.com";
        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            $baseUrl = 'https://'.$baseUrl;
        }

        try {
            $response = Http::timeout(5)
                ->retry(1, 150)
                ->withBody($body, 'application/json')
                ->post($baseUrl.$path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));

            if (! $response->successful()) {
                Log::warning('HisebGhor Pusher delivery failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'channel' => $channel,
                    'event' => $event,
                ]);
            }

            return $response->successful();
        } catch (Throwable $exception) {
            Log::warning('HisebGhor Pusher delivery exception.', [
                'message' => $exception->getMessage(),
                'channel' => $channel,
                'event' => $event,
            ]);

            return false;
        }
    }
}
