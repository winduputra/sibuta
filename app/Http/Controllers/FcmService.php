<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Exchanges the service account for a short lived OAuth2 access token.
     *
     * The token is cached for 50 minutes because Google rejects re-use past
     * one hour and each round trip would otherwise add ~300ms to every send.
     */
    public function getFCMAuthorizationToken($path = null)
    {
        $path = $path ?: config('firebase.credentials');

        if (!$path || !file_exists($path)) {
            throw new Exception('File kredensial Firebase tidak ditemukan');
        }

        $cacheKey = 'fcm.access_token.' . md5($path);

        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }

        $json = json_decode(file_get_contents($path));
        if ($json === null || !isset($json->private_key)) {
            throw new Exception('File kredensial Firebase tidak valid');
        }

        $now = Carbon::now();
        $expiresAt = Carbon::now()->addMinutes(59);

        $claimSet = [
            'iss' => $json->client_email,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $json->token_uri,
            'iat' => $now->timestamp,
            'exp' => $expiresAt->timestamp,
        ];

        $assertion = JWT::encode(
            $claimSet,
            $json->private_key,
            'RS256',
            $json->private_key_id
        );

        $client = new Client(['base_uri' => 'https://oauth2.googleapis.com']);

        $response = $client->request('POST', 'token', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Gagal mendapatkan token FCM');
        }

        $body = json_decode($response->getBody()->getContents());
        $accessToken = $body->access_token ?? null;

        if (empty($accessToken)) {
            throw new Exception('Token FCM tidak diterima');
        }

        Cache::put($cacheKey, $accessToken, now()->addMinutes(50));

        return $accessToken;
    }

    /**
     * Sends one FCM HTTP v1 message.
     *
     * The project id must match the Firebase project that issued the Android
     * google-services.json, otherwise Google answers 403 SENDER_ID_MISMATCH.
     */
    public function sendNotification($token, $payloads)
    {
        $projectId = $this->projectId();

        $client = new Client(['base_uri' => 'https://fcm.googleapis.com']);

        $response = $client->request(
            'POST',
            '/v1/projects/' . $projectId . '/messages:send',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'json' => $payloads,
            ]
        );

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode > 299) {
            throw new Exception('Response FCM bukan 2xx');
        }

        return $response->getBody()->getContents();
    }

    /**
     * Resolves the Firebase project id from config, falling back to the
     * service account file so a missing env var cannot silently send to the
     * wrong project again.
     */
    public function projectId()
    {
        $configured = config('firebase.project_id');

        if (!empty($configured)) {
            return $configured;
        }

        $path = config('firebase.credentials');

        if ($path && file_exists($path)) {
            $json = json_decode(file_get_contents($path));

            if (isset($json->project_id) && !empty($json->project_id)) {
                return $json->project_id;
            }
        }

        throw new Exception('Firebase project id tidak dikonfigurasi');
    }

    /**
     * Sends a notification to every registered device token of a user and
     * prunes tokens Google reports as permanently invalid.
     *
     * Returns the number of successful sends. Failures are logged, never
     * thrown, so a dead push service can never block the caller's request.
     */
    public function sendToUser($userId, array $notification, array $data = [])
    {
        $devices = Device::where('user_id', '=', $userId)->get();

        if ($devices->isEmpty()) {
            return 0;
        }

        try {
            $accessToken = $this->getFCMAuthorizationToken();
        } catch (\Throwable $e) {
            Log::warning('FCM: gagal mendapatkan access token', ['error' => $e->getMessage()]);

            return 0;
        }

        $sent = 0;

        foreach ($devices as $device) {
            if (empty($device->device_token)) {
                continue;
            }

            $payload = [
                'message' => [
                    'token' => $device->device_token,
                    'notification' => [
                        'title' => $notification['title'] ?? '',
                        'body' => $notification['body'] ?? '',
                    ],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'sibuta_visitor_requests',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ];

            try {
                $this->sendNotification($accessToken, $payload);
                $sent++;
            } catch (\GuzzleHttp\Exception\BadResponseException $e) {
                $this->handleSendFailure($device, $e);
            } catch (\Throwable $e) {
                Log::warning('FCM: gagal mengirim notifikasi', [
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Drops device rows whose token Google reports as unregistered or
     * mismatched, otherwise every future send keeps failing on a dead token.
     */
    private function handleSendFailure($device, $exception)
    {
        $response = $exception->getResponse();
        $body = $response ? (string) $response->getBody() : '';
        $decoded = json_decode($body, true);

        $status = $decoded['error']['status'] ?? null;
        $errorCode = $decoded['error']['details'][0]['errorCode'] ?? null;

        $permanent = in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'SENDER_ID_MISMATCH'], true)
            || in_array($status, ['NOT_FOUND', 'INVALID_ARGUMENT'], true);

        if ($permanent) {
            $device->delete();

            Log::info('FCM: device token dihapus karena tidak valid', [
                'device_id' => $device->id,
                'error_code' => $errorCode,
                'status' => $status,
            ]);

            return;
        }

        Log::warning('FCM: gagal mengirim notifikasi', [
            'device_id' => $device->id,
            'status' => $status,
            'error' => $body,
        ]);
    }
}
