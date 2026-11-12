<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Guest;
use App\Models\Role;
use App\Models\User;
use App\Support\GuestNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * End-to-end mobile visitor flow: device registration, guest list scoping,
 * decision handling and the push notification trigger.
 *
 * Every test runs inside a transaction that is rolled back, therefore the
 * production-like seed data in the local database is never modified.
 *
 * Sanctum's config lists the "web" guard first, so a bearer token is resolved
 * against the session left behind by the previous request in the same test.
 * actingAs($user, 'sanctum') is used instead of hand-rolled bearer headers so
 * each request is authenticated as exactly the user under test.
 */
class MobileGuestFlowTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'password';

    private function createAsn(string $nip): User
    {
        $role = Role::firstOrCreate(
            ['code' => Role::ASN],
            ['name' => 'ASN']
        );

        return User::create([
            'name' => 'ASN ' . $nip,
            'username' => $nip,
            'nip' => $nip,
            'password' => Hash::make(self::PASSWORD),
            'role_id' => $role->id,
        ]);
    }

    private function createGuest(User $owner, int $status = 0, string $name = 'Ahmad Fauzi'): Guest
    {
        return Guest::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
            'phone' => '081234567890',
            'guest_type' => 1,
            'division_id' => 1,
            'user_id' => $owner->id,
            'ticket_type' => 1,
            'selfie' => 'http://localhost/image/test.jpg',
            'description' => 'Konsultasi sistem',
            'schedule' => now()->format('Y-m-d H:i:s'),
            'status' => $status,
        ]);
    }

    public function test_device_token_is_registered_for_the_authenticated_asn(): void
    {
        $user = $this->createAsn('198001012005011001');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/devices', [
            'device_token' => 'token-abc',
            'device_name' => 'Xiaomi 12',
            'platform' => 'android',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('devices', [
            'device_token' => 'token-abc',
            'user_id' => $user->id,
            'platform' => 'android',
        ]);
    }

    public function test_device_registration_requires_authentication(): void
    {
        $this->postJson('/api/devices', ['device_token' => 'token-anon'])
            ->assertStatus(401);
    }

    public function test_repeated_device_registration_does_not_duplicate_rows(): void
    {
        $user = $this->createAsn('198001012005011002');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/devices', ['device_token' => 'token-dup'])
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/devices', ['device_token' => 'token-dup'])
            ->assertStatus(200);

        $this->assertSame(1, Device::where('device_token', '=', 'token-dup')->count());
    }

    public function test_device_token_moves_to_the_new_account_on_shared_phone(): void
    {
        $first = $this->createAsn('198001012005011003');
        $second = $this->createAsn('198001012005011004');

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/devices', ['device_token' => 'token-shared'])
            ->assertStatus(200);

        $this->actingAs($second, 'sanctum')
            ->postJson('/api/devices', ['device_token' => 'token-shared'])
            ->assertStatus(200);

        $this->assertDatabaseHas('devices', [
            'device_token' => 'token-shared',
            'user_id' => $second->id,
        ]);

        $this->assertDatabaseMissing('devices', [
            'device_token' => 'token-shared',
            'user_id' => $first->id,
        ]);
    }

    public function test_logout_removes_only_the_callers_device_row(): void
    {
        $owner = $this->createAsn('198001012005011005');
        $stranger = $this->createAsn('198001012005011006');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/devices', ['device_token' => 'token-owner'])
            ->assertStatus(200);

        $this->actingAs($stranger, 'sanctum')
            ->deleteJson('/api/devices', ['device_token' => 'token-owner'])
            ->assertStatus(200);

        $this->assertDatabaseHas('devices', ['device_token' => 'token-owner', 'user_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/devices', ['device_token' => 'token-owner'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('devices', ['device_token' => 'token-owner']);
    }

    public function test_guest_list_only_returns_requests_owned_by_the_asn(): void
    {
        $owner = $this->createAsn('198001012005011007');
        $stranger = $this->createAsn('198001012005011008');

        $this->createGuest($owner, 0, 'Tamu Sendiri');
        $this->createGuest($stranger, 0, 'Tamu Orang Lain');

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/guest/list');

        $response->assertStatus(200);

        $names = collect($response->json())->pluck('name');

        $this->assertTrue($names->contains('Tamu Sendiri'));
        $this->assertFalse($names->contains('Tamu Orang Lain'));
    }

    public function test_guest_list_excludes_already_decided_requests(): void
    {
        $owner = $this->createAsn('198001012005011016');

        $this->createGuest($owner, 0, 'Masih Menunggu');
        $this->createGuest($owner, 1, 'Sudah Diterima');
        $this->createGuest($owner, -1, 'Sudah Ditolak');

        $names = collect(
            $this->actingAs($owner, 'sanctum')->getJson('/api/guest/list')->json()
        )->pluck('name');

        $this->assertTrue($names->contains('Masih Menunggu'));
        $this->assertFalse($names->contains('Sudah Diterima'));
        $this->assertFalse($names->contains('Sudah Ditolak'));
    }

    public function test_accept_marks_the_request_approved(): void
    {
        $owner = $this->createAsn('198001012005011009');
        $guest = $this->createGuest($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/accept/' . $guest->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame(1, (int) $guest->fresh()->status);
    }

    public function test_an_asn_cannot_act_on_another_asn_request(): void
    {
        $owner = $this->createAsn('198001012005011010');
        $stranger = $this->createAsn('198001012005011011');
        $guest = $this->createGuest($owner);

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/guest/accept/' . $guest->id)
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertSame(0, (int) $guest->fresh()->status);
    }

    public function test_acting_twice_returns_conflict_and_keeps_the_first_decision(): void
    {
        $owner = $this->createAsn('198001012005011012');
        $guest = $this->createGuest($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/accept/' . $guest->id)
            ->assertStatus(200);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/reject/' . $guest->id, ['rejection_reason' => 'Terlambat'])
            ->assertStatus(409);

        $this->assertSame(1, (int) $guest->fresh()->status);
    }

    public function test_reject_requires_a_reason(): void
    {
        $owner = $this->createAsn('198001012005011017');
        $guest = $this->createGuest($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/reject/' . $guest->id, [])
            ->assertStatus(422);

        $this->assertSame(0, (int) $guest->fresh()->status);
    }

    public function test_reschedule_stores_new_schedule_and_its_own_status(): void
    {
        $owner = $this->createAsn('198001012005011013');
        $guest = $this->createGuest($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/reschedule/' . $guest->id, [
                'reschedule_reason' => 'Agenda bentrok',
                'reschedule' => '2026-12-01 09:00:00',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $fresh = $guest->fresh();

        $this->assertSame('2026-12-01 09:00:00', $fresh->reschedule);
        $this->assertSame(2, (int) $fresh->status);
    }

    public function test_new_visitor_request_pushes_to_every_asn_device(): void
    {
        $owner = $this->createAsn('198001012005011014');
        $guest = $this->createGuest($owner);

        Device::create([
            'device_token' => 'token-one',
            'device_name' => 'HP Satu',
            'platform' => 'android',
            'user_id' => $owner->id,
        ]);

        Device::create([
            'device_token' => 'token-two',
            'device_name' => 'HP Dua',
            'platform' => 'android',
            'user_id' => $owner->id,
        ]);

        // Google is unreachable from the test suite, so only the fan-out is
        // asserted: both registered devices must be addressed.
        $sentTokens = [];

        $fake = new class($sentTokens) extends \App\Http\Controllers\FcmService {
            public array $tokens = [];

            public function __construct(array &$tokens)
            {
                $this->tokens = &$tokens;
            }

            public function sendToUser($userId, array $notification, array $data = [])
            {
                $devices = Device::where('user_id', '=', $userId)->get();

                foreach ($devices as $device) {
                    $this->tokens[] = $device->device_token;
                }

                return $devices->count();
            }
        };

        $sent = GuestNotifier::sendWith(
            $fake,
            (int) $owner->id,
            ['title' => 'Permintaan Kunjungan Baru', 'body' => 'Ahmad ingin bertemu.'],
            ['type' => 'guest_request', 'guest_id' => (string) $guest->id, 'status' => 'pending']
        );

        $this->assertSame(2, $sent);
        $this->assertEqualsCanonicalizing(['token-one', 'token-two'], $sentTokens);
    }

    public function test_user_without_devices_reports_zero_sends(): void
    {
        $owner = $this->createAsn('198001012005011015');

        $this->assertSame(0, (new \App\Http\Controllers\FcmService())->sendToUser(
            (int) $owner->id,
            ['title' => 'Tes', 'body' => 'Tes'],
            ['type' => 'test']
        ));
    }
}
