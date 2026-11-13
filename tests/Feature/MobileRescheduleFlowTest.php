<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Answering a rescheduled visit and the Riwayat summary endpoints.
 *
 * A rescheduled visit keeps status 2 until the ASN decides, so it must remain
 * acceptable and rejectable. Regression cover for the "Data Sudah Diberi Aksi"
 * response that used to appear because the guard only allowed status 0.
 *
 * Every test runs inside a transaction that is rolled back.
 */
class MobileRescheduleFlowTest extends TestCase
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
            'schedule' => now()->format('Y-m-d'),
            'status' => $status,
        ]);
    }

    private function rescheduledGuest(User $owner, string $name = 'Tamu Dijadwalkan Ulang'): Guest
    {
        return Guest::create([
            'name' => $name,
            'email' => 'tamu.jadwal@example.test',
            'phone' => '081234567891',
            'guest_type' => 1,
            'division_id' => 1,
            'user_id' => $owner->id,
            'ticket_type' => 1,
            'selfie' => 'http://localhost/image/test.jpg',
            'description' => 'Koordinasi pekerjaan',
            'schedule' => now()->format('Y-m-d'),
            'status' => 2,
            'reschedule' => now()->addDays(3)->format('Y-m-d'),
            'reschedule_reason' => 'Menunggu jadwal pimpinan',
        ]);
    }

    /**
     * A visit the ASN already closed, with an explicit completion moment.
     *
     * completed_at is written straight to the column because the model cast
     * would otherwise round-trip the value through the app timezone.
     */
    private function completedGuest(User $owner, string $name, $completedAt): Guest
    {
        $guest = $this->createGuest($owner, 3, $name);

        Guest::where('id', $guest->id)->update(['completed_at' => $completedAt]);

        return $guest->fresh();
    }

    public function test_a_rescheduled_visit_can_still_be_accepted(): void
    {
        $owner = $this->createAsn('198501012010011001');
        $guest = $this->rescheduledGuest($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/accept/' . $guest->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 1);

        $fresh = $guest->fresh();

        $this->assertSame(1, (int) $fresh->status);
        $this->assertNull($fresh->reschedule_reason);
        $this->assertNotNull($fresh->reschedule);
    }

    public function test_a_rescheduled_visit_can_still_be_rejected(): void
    {
        $owner = $this->createAsn('198501012010011002');
        $guest = $this->rescheduledGuest($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/reject/' . $guest->id, [
                'rejection_reason' => 'Pimpinan tidak tersedia',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', -1);

        $this->assertSame(-1, (int) $guest->fresh()->status);
    }

    public function test_an_accepted_visit_can_no_longer_be_rescheduled(): void
    {
        $owner = $this->createAsn('198501012010011003');
        $guest = $this->createGuest($owner, 1, 'Sudah Diterima');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/accept/' . $guest->id)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Data Sudah Diberi Aksi');

        $this->assertSame(1, (int) $guest->fresh()->status);
    }

    public function test_a_completed_visit_rejects_every_action(): void
    {
        $owner = $this->createAsn('198501012010011004');
        $guest = $this->createGuest($owner, 3, 'Sudah Selesai');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/accept/' . $guest->id)
            ->assertStatus(409);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/reject/' . $guest->id, ['rejection_reason' => 'Batal'])
            ->assertStatus(409);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/reschedule/' . $guest->id, [
                'reschedule_reason' => 'Geser',
                'reschedule' => now()->addDay()->format('Y-m-d'),
            ])
            ->assertStatus(409);

        $this->assertSame(3, (int) $guest->fresh()->status);
    }

    public function test_another_asn_cannot_accept_a_rescheduled_visit(): void
    {
        $owner = $this->createAsn('198501012010011005');
        $stranger = $this->createAsn('198501012010011006');
        $guest = $this->rescheduledGuest($owner);

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/guest/accept/' . $guest->id)
            ->assertStatus(404);

        $this->assertSame(2, (int) $guest->fresh()->status);
    }

    public function test_default_list_includes_rescheduled_visits(): void
    {
        $owner = $this->createAsn('198501012010011007');

        $this->createGuest($owner, 0, 'Masih Menunggu');
        $this->rescheduledGuest($owner, 'Sudah Dijadwalkan Ulang');
        $this->createGuest($owner, 1, 'Sudah Diterima');

        $names = collect(
            $this->actingAs($owner, 'sanctum')->getJson('/api/guest/list')->json()
        )->pluck('name');

        $this->assertTrue($names->contains('Masih Menunggu'));
        $this->assertTrue($names->contains('Sudah Dijadwalkan Ulang'));
        $this->assertFalse($names->contains('Sudah Diterima'));
    }

    public function test_the_answered_filter_returns_the_same_undecided_set(): void
    {
        $owner = $this->createAsn('198501012010011008');

        $this->createGuest($owner, 0, 'Masih Menunggu');
        $this->rescheduledGuest($owner, 'Sudah Dijadwalkan Ulang');
        $this->createGuest($owner, 3, 'Sudah Selesai');

        $names = collect(
            $this->actingAs($owner, 'sanctum')->getJson('/api/guest/list?status=answered')->json()
        )->pluck('name');

        $this->assertTrue($names->contains('Masih Menunggu'));
        $this->assertTrue($names->contains('Sudah Dijadwalkan Ulang'));
        $this->assertFalse($names->contains('Sudah Selesai'));
    }

    public function test_the_status_endpoint_returns_the_live_row(): void
    {
        $owner = $this->createAsn('198501012010011009');
        $guest = $this->rescheduledGuest($owner);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/guest/' . $guest->id . '/status')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $guest->id)
            ->assertJsonPath('data.status', 2)
            ->assertJsonPath('data.reschedule', $guest->reschedule);
    }

    public function test_the_status_endpoint_hides_another_asns_visit(): void
    {
        $owner = $this->createAsn('198501012010011010');
        $stranger = $this->createAsn('198501012010011011');
        $guest = $this->rescheduledGuest($owner);

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/guest/' . $guest->id . '/status')
            ->assertStatus(404);
    }

    public function test_the_status_endpoint_requires_authentication(): void
    {
        $owner = $this->createAsn('198501012010011012');
        $guest = $this->rescheduledGuest($owner);

        $this->getJson('/api/guest/' . $guest->id . '/status')->assertStatus(401);
    }

    public function test_monthly_stats_count_only_the_callers_completions(): void
    {
        $owner = $this->createAsn('198501012010011013');
        $stranger = $this->createAsn('198501012010011014');

        $this->completedGuest($owner, 'Selesai Bulan Ini', now());
        $this->completedGuest($stranger, 'Milik Orang Lain', now());
        $this->createGuest($owner, 1, 'Belum Selesai');

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/guest/history/stats?period=month');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'month')
            ->assertJsonPath('data.total', 1);

        $this->assertSame(now()->format('Y-m-01'), $response->json('data.start_date'));
    }

    public function test_stats_ignore_old_completions_outside_the_month(): void
    {
        $owner = $this->createAsn('198501012010011015');

        $this->completedGuest($owner, 'Selesai Bulan Lalu', now()->subMonths(2));
        $this->completedGuest($owner, 'Selesai Bulan Ini', now());

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/guest/history/stats?period=month')
            ->assertStatus(200)
            ->assertJsonPath('data.total', 1);
    }

    public function test_custom_range_stats_use_the_requested_days(): void
    {
        $owner = $this->createAsn('198501012010011016');

        $this->completedGuest($owner, 'Dalam Rentang', '2026-03-10 09:00:00');
        $this->completedGuest($owner, 'Di Luar Rentang', '2026-03-20 09:00:00');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/guest/history/stats?period=custom&start_date=2026-03-01&end_date=2026-03-15')
            ->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.start_date', '2026-03-01')
            ->assertJsonPath('data.end_date', '2026-03-15');
    }

    public function test_custom_range_without_dates_is_rejected(): void
    {
        $owner = $this->createAsn('198501012010011017');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/guest/history/stats?period=custom')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_an_unknown_period_is_rejected(): void
    {
        $owner = $this->createAsn('198501012010011018');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/guest/history/stats?period=year')
            ->assertStatus(422);
    }

    public function test_today_history_lists_only_visits_closed_today(): void
    {
        $owner = $this->createAsn('198501012010011019');
        $stranger = $this->createAsn('198501012010011020');

        $today = $this->completedGuest($owner, 'Selesai Hari Ini', now());

        $this->completedGuest($owner, 'Selesai Kemarin', now()->subDay());

        $this->completedGuest($stranger, 'Milik Orang Lain', now());

        $names = collect(
            $this->actingAs($owner, 'sanctum')->getJson('/api/guest/history/today')->json()
        )->pluck('name');

        $this->assertTrue($names->contains('Selesai Hari Ini'));
        $this->assertFalse($names->contains('Selesai Kemarin'));
        $this->assertFalse($names->contains('Milik Orang Lain'));
    }

    public function test_history_endpoints_require_authentication(): void
    {
        $this->getJson('/api/guest/history/stats')->assertStatus(401);
        $this->getJson('/api/guest/history/today')->assertStatus(401);
    }
}
