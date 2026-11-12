<?php

namespace App\Support;

use App\Http\Controllers\FcmService;
use App\Models\Guest;
use Illuminate\Support\Facades\Log;

class GuestNotifier
{
    /**
     * Notifies every ASN device that a visitor is waiting for a decision.
     *
     * The data payload carries type/guest_id/status so the app can deep link
     * to the exact request instead of only showing body text.
     */
    public static function visitorRequestCreated(Guest $guest): int
    {
        $visitorName = $guest->name ?: 'Tamu';

        return self::send(
            (int) $guest->user_id,
            [
                'title' => 'Permintaan Kunjungan Baru',
                'body' => $visitorName . ' ingin bertemu dengan Anda.',
            ],
            [
                'type' => 'guest_request',
                'guest_id' => (string) $guest->id,
                'status' => 'pending',
            ]
        );
    }

    /**
     * Called after an ASN decides on a request, so the app can refresh counts
     * on any other device the same ASN is signed in on.
     */
    public static function visitorDecisionCreated(Guest $guest, string $title, string $body): int
    {
        return self::send(
            (int) $guest->user_id,
            [
                'title' => $title,
                'body' => $body,
            ],
            [
                'type' => 'guest_decision',
                'guest_id' => (string) $guest->id,
                'status' => (string) $guest->status,
            ]
        );
    }

    /**
     * Sends through a resolved FcmService instance so the sender can be
     * swapped with a fake in tests instead of reaching Google.
     */
    public static function sendWith(FcmService $fcm, int $userId, array $notification, array $data): int
    {
        try {
            return $fcm->sendToUser($userId, $notification, $data);
        } catch (\Throwable $e) {
            Log::warning('GuestNotifier: gagal mengirim notifikasi', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Wraps the push so a broken FCM configuration can never break the HTTP
     * request that triggered it: the guest row is already committed by then.
     */
    private static function send(int $userId, array $notification, array $data): int
    {
        return self::sendWith(app(FcmService::class), $userId, $notification, $data);
    }
}
