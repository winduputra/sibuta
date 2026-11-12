<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Visit completion and visitor photo access from the mobile app.
 *
 * Every test runs inside a transaction that is rolled back, so the local
 * database is never modified.
 */
class MobileVisitCompletionTest extends TestCase
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

    private function createGuest(User $owner, int $status = 1, string $name = 'Ahmad Fauzi', ?string $selfie = null): Guest
    {
        return Guest::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
            'phone' => '081234567890',
            'guest_type' => 1,
            'division_id' => 1,
            'user_id' => $owner->id,
            'ticket_type' => 1,
            'selfie' => $selfie ?? 'http://localhost/image/test.jpg',
            'description' => 'Konsultasi sistem',
            'schedule' => now()->format('Y-m-d H:i:s'),
            'status' => $status,
        ]);
    }

    public function test_accepted_visit_can_be_completed(): void
    {
        $owner = $this->createAsn('199001012005011001');
        $guest = $this->createGuest($owner, 1);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/complete/' . $guest->id, [
                'meeting_result' => 'Sistem sudah dijelaskan dan tamu memahami alurnya.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 3)
            ->assertJsonPath('data.meeting_result', 'Sistem sudah dijelaskan dan tamu memahami alurnya.');

        $fresh = $guest->fresh();

        $this->assertSame(3, (int) $fresh->status);
        $this->assertSame('Sistem sudah dijelaskan dan tamu memahami alurnya.', $fresh->meeting_result);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_meeting_result_is_required(): void
    {
        $owner = $this->createAsn('199001012005011002');
        $guest = $this->createGuest($owner, 1);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/complete/' . $guest->id, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('meeting_result');

        $this->assertSame(1, (int) $guest->fresh()->status);
        $this->assertNull($guest->fresh()->meeting_result);
    }

    public function test_pending_visit_cannot_be_completed(): void
    {
        $owner = $this->createAsn('199001012005011003');
        $guest = $this->createGuest($owner, 0);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/complete/' . $guest->id, ['meeting_result' => 'Sudah bertemu'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(0, (int) $guest->fresh()->status);
    }

    public function test_rejected_visit_cannot_be_completed(): void
    {
        $owner = $this->createAsn('199001012005011004');
        $guest = $this->createGuest($owner, -1);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/complete/' . $guest->id, ['meeting_result' => 'Sudah bertemu'])
            ->assertStatus(409);

        $this->assertSame(-1, (int) $guest->fresh()->status);
    }

    public function test_completed_visit_cannot_be_completed_again(): void
    {
        $owner = $this->createAsn('199001012005011005');
        $guest = $this->createGuest($owner, 1);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/complete/' . $guest->id, ['meeting_result' => 'Hasil pertama'])
            ->assertStatus(200);

        $firstCompletedAt = $guest->fresh()->completed_at;

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/guest/complete/' . $guest->id, ['meeting_result' => 'Hasil kedua'])
            ->assertStatus(409);

        $fresh = $guest->fresh();

        $this->assertSame(3, (int) $fresh->status);
        $this->assertSame('Hasil pertama', $fresh->meeting_result);
        $this->assertTrue($firstCompletedAt->equalTo($fresh->completed_at));
    }

    public function test_another_asn_cannot_complete_the_visit(): void
    {
        $owner = $this->createAsn('199001012005011006');
        $stranger = $this->createAsn('199001012005011007');
        $guest = $this->createGuest($owner, 1);

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/guest/complete/' . $guest->id, ['meeting_result' => 'Menyusup'])
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertSame(1, (int) $guest->fresh()->status);
        $this->assertNull($guest->fresh()->meeting_result);
    }

    public function test_completing_requires_authentication(): void
    {
        $owner = $this->createAsn('199001012005011008');
        $guest = $this->createGuest($owner, 1);

        $this->postJson('/api/guest/complete/' . $guest->id, ['meeting_result' => 'Tanpa login'])
            ->assertStatus(401);

        $this->assertSame(1, (int) $guest->fresh()->status);
    }

    public function test_accepted_visits_are_listed_with_status_filter(): void
    {
        $owner = $this->createAsn('199001012005011009');
        $stranger = $this->createAsn('199001012005011010');

        $this->createGuest($owner, 0, 'Masih Menunggu');
        $this->createGuest($owner, 1, 'Sudah Diterima');
        $this->createGuest($owner, 3, 'Sudah Selesai');
        $this->createGuest($stranger, 1, 'Milik Orang Lain');

        $accepted = collect(
            $this->actingAs($owner, 'sanctum')->getJson('/api/guest/list?status=1')->json()
        )->pluck('name');

        $this->assertTrue($accepted->contains('Sudah Diterima'));
        $this->assertFalse($accepted->contains('Masih Menunggu'));
        $this->assertFalse($accepted->contains('Sudah Selesai'));
        $this->assertFalse($accepted->contains('Milik Orang Lain'));
    }

    public function test_default_list_still_returns_only_pending_visits(): void
    {
        $owner = $this->createAsn('199001012005011011');

        $this->createGuest($owner, 0, 'Masih Menunggu');
        $this->createGuest($owner, 1, 'Sudah Diterima');

        $names = collect(
            $this->actingAs($owner, 'sanctum')->getJson('/api/guest/list')->json()
        )->pluck('name');

        $this->assertTrue($names->contains('Masih Menunggu'));
        $this->assertFalse($names->contains('Sudah Diterima'));
    }

    public function test_guest_list_exposes_an_authenticated_photo_url(): void
    {
        $owner = $this->createAsn('199001012005011012');
        $guest = $this->createGuest($owner, 0, 'Ahmad Fauzi');

        $row = collect(
            $this->actingAs($owner, 'sanctum')->getJson('/api/guest/list')->json()
        )->firstWhere('id', $guest->id);

        $this->assertArrayHasKey('photo_url', $row);
        $this->assertStringContainsString('/api/guest/' . $guest->id . '/photo', $row['photo_url']);
        $this->assertStringNotContainsString('localhost/image', $row['photo_url']);
    }

    public function test_photo_url_is_null_when_the_guest_has_no_photo(): void
    {
        $owner = $this->createAsn('199001012005011013');
        $guest = $this->createGuest($owner, 0, 'Tanpa Foto', '');

        $this->assertNull($guest->fresh()->photo_url);
    }

    public function test_photo_is_served_to_the_owning_asn(): void
    {
        Storage::fake('local');

        $owner = $this->createAsn('199001012005011014');
        $guest = $this->createGuest($owner, 0, 'Ahmad Fauzi');

        $directory = storage_path('app/image');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file = $directory . DIRECTORY_SEPARATOR . 'photo-test-' . $guest->id . '.jpg';
        file_put_contents($file, base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
            . 'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        ));

        $guest->update(['selfie' => 'http://localhost/image/photo-test-' . $guest->id . '.jpg']);

        try {
            $response = $this->actingAs($owner, 'sanctum')
                ->get('/api/guest/' . $guest->id . '/photo');

            $response->assertStatus(200);
            $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function test_photo_of_another_asn_returns_not_found(): void
    {
        $owner = $this->createAsn('199001012005011015');
        $stranger = $this->createAsn('199001012005011016');
        $guest = $this->createGuest($owner, 0, 'Ahmad Fauzi');

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/guest/' . $guest->id . '/photo')
            ->assertStatus(404);
    }

    public function test_photo_of_a_guest_without_a_file_returns_not_found(): void
    {
        $owner = $this->createAsn('199001012005011017');
        $guest = $this->createGuest($owner, 0, 'Tanpa Berkas', 'http://localhost/image/tidak-ada.jpg');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/guest/' . $guest->id . '/photo')
            ->assertStatus(404);
    }

    public function test_photo_route_cannot_be_traversed_out_of_the_image_directory(): void
    {
        $owner = $this->createAsn('199001012005011018');
        $guest = $this->createGuest($owner, 0, 'Path Traversal', 'http://localhost/image/../../.env');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/guest/' . $guest->id . '/photo')
            ->assertStatus(404);
    }
}
