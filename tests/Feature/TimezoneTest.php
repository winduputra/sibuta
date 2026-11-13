<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * WIB timezone consistency across the application, the guest API and the
 * mobile dashboard payload.
 *
 * Every test runs inside a transaction that is rolled back, so the local
 * database is never modified.
 */
class TimezoneTest extends TestCase
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

    private function createGuest(User $owner, array $overrides = []): Guest
    {
        return Guest::create(array_merge([
            'name' => 'Ahmad Fauzi',
            'email' => 'ahmad.fauzi@example.test',
            'phone' => '081234567890',
            'guest_type' => 1,
            'division_id' => 1,
            'user_id' => $owner->id,
            'ticket_type' => 1,
            'selfie' => 'http://localhost/image/test.jpg',
            'description' => 'Konsultasi sistem',
            'status' => 0,
        ], $overrides));
    }

    public function test_application_timezone_is_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    public function test_now_returns_wib_wall_clock(): void
    {
        $now = now();

        $this->assertSame('Asia/Jakarta', $now->getTimezone()->getName());
        $this->assertSame(
            (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i'),
            $now->format('Y-m-d H:i')
        );
    }

    public function test_guest_saved_timestamp_matches_the_wib_clock_without_shifting(): void
    {
        $owner = $this->createAsn('199001012005011001');

        $guest = $this->createGuest($owner);

        $raw = DB::table('guests')->where('id', $guest->id)->first();

        $this->assertSame(
            now()->format('Y-m-d H:i'),
            \Illuminate\Support\Carbon::parse($raw->created_at)->format('Y-m-d H:i')
        );
    }

    public function test_submitted_at_accessor_is_wib_formatted(): void
    {
        $owner = $this->createAsn('199001012005011002');

        $guest = $this->createGuest($owner, [
            'created_at' => \Illuminate\Support\Carbon::create(2026, 11, 12, 20, 15, 0, 'Asia/Jakarta'),
        ]);

        $guest = Guest::find($guest->id);

        $this->assertSame('2026-11-12 20:15:00', $guest->submitted_at);
    }

    public function test_guest_list_returns_wib_submission_time(): void
    {
        $owner = $this->createAsn('199001012005011003');

        $this->createGuest($owner, [
            'created_at' => \Illuminate\Support\Carbon::create(2026, 11, 12, 9, 45, 0, 'Asia/Jakarta'),
        ]);

        $this->actingAs($owner, 'sanctum');

        $response = $this->getJson('/api/guest/list');

        $response->assertStatus(200);

        $row = collect($response->json())->firstWhere('name', 'Ahmad Fauzi');

        $this->assertNotNull($row);
        $this->assertSame('2026-11-12 09:45:00', $row['submitted_at']);
    }

    public function test_guest_list_orders_newest_request_first(): void
    {
        $owner = $this->createAsn('199001012005011004');

        $this->createGuest($owner, [
            'name' => 'Tamu Lama',
            'created_at' => \Illuminate\Support\Carbon::create(2026, 11, 10, 8, 0, 0, 'Asia/Jakarta'),
        ]);
        $this->createGuest($owner, [
            'name' => 'Tamu Baru',
            'created_at' => \Illuminate\Support\Carbon::create(2026, 11, 12, 16, 30, 0, 'Asia/Jakarta'),
        ]);

        $this->actingAs($owner, 'sanctum');

        $names = collect($this->getJson('/api/guest/list')->json())->pluck('name')->all();

        $this->assertSame(['Tamu Baru', 'Tamu Lama'], $names);
    }

    public function test_photo_url_is_absolute_on_the_request_host(): void
    {
        $owner = $this->createAsn('199001012005011005');

        $guest = $this->createGuest($owner);

        $this->actingAs($owner, 'sanctum');

        $row = collect($this->getJson('/api/guest/list')->json())->firstWhere('id', $guest->id);

        $this->assertNotNull($row);
        $this->assertSame(
            'http://localhost/api/guest/' . $guest->id . '/photo',
            $row['photo_url']
        );
        $this->assertStringNotContainsString('localhost/localhost', $row['photo_url']);
    }

    public function test_dashboard_counts_guests_using_the_wib_date(): void
    {
        $owner = $this->createAsn('199001012005011006');

        $this->createGuest($owner, [
            'status' => 1,
            'schedule' => now()->timezone('Asia/Jakarta')->format('Y-m-d'),
        ]);

        $this->actingAs($owner, 'sanctum');

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.today_guest'));
        $this->assertSame(1, $response->json('data.this_week_guest'));
    }
}
