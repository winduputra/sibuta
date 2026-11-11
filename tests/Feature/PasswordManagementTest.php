<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Password management for SiBUTA.
 *
 * Covers the mobile self-service change (`POST /api/profile/change-password`)
 * and the website admin reset (`POST /user/reset-password/{id}`).
 *
 * Every test runs inside a transaction that is rolled back, so seeded accounts
 * are never permanently modified.
 */
class PasswordManagementTest extends TestCase
{
    use DatabaseTransactions;

    private const ASN_NIP = '197006291998031004';
    private const ASN_PASSWORD = 'password';
    private const NEW_PASSWORD = 'RahasiaBaru2026';

    // ---------------------------------------------------------------
    // Mobile: self-service change password
    // ---------------------------------------------------------------

    public function test_mobile_change_password_succeeds_with_correct_current_password(): void
    {
        $token = $this->loginAsAsn();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/profile/change-password', [
                'current_password' => self::ASN_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password berhasil diperbarui.');

        $user = User::where('nip', self::ASN_NIP)->firstOrFail();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse(Hash::check(self::ASN_PASSWORD, $user->password));
    }

    public function test_new_password_works_for_the_next_login(): void
    {
        $token = $this->loginAsAsn();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/profile/change-password', [
                'current_password' => self::ASN_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertStatus(200);

        $this->refreshApplicationAuth();

        $this->postJson('/api/auth/login', [
            'nip' => self::ASN_NIP,
            'password' => self::NEW_PASSWORD,
        ])->assertStatus(200)->assertJsonPath('success', true);

        $this->refreshApplicationAuth();

        $this->postJson('/api/auth/login', [
            'nip' => self::ASN_NIP,
            'password' => self::ASN_PASSWORD,
        ])->assertStatus(401);
    }

    public function test_mobile_change_password_rejects_wrong_current_password(): void
    {
        $token = $this->loginAsAsn();
        $before = User::where('nip', self::ASN_NIP)->firstOrFail()->password;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/profile/change-password', [
                'current_password' => 'bukan-password-lama',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertSame($before, User::where('nip', self::ASN_NIP)->firstOrFail()->password);
    }

    public function test_mobile_change_password_rejects_mismatched_confirmation(): void
    {
        $token = $this->loginAsAsn();
        $before = User::where('nip', self::ASN_NIP)->firstOrFail()->password;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/profile/change-password', [
                'current_password' => self::ASN_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => 'BerbedaSekali2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertSame($before, User::where('nip', self::ASN_NIP)->firstOrFail()->password);
    }

    public function test_mobile_change_password_rejects_short_new_password(): void
    {
        $token = $this->loginAsAsn();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/profile/change-password', [
                'current_password' => self::ASN_PASSWORD,
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_mobile_change_password_requires_authentication(): void
    {
        $this->postJson('/api/profile/change-password', [
            'current_password' => self::ASN_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(401);
    }

    public function test_mobile_change_password_response_never_leaks_the_password(): void
    {
        $token = $this->loginAsAsn();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/profile/change-password', [
                'current_password' => self::ASN_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        $body = $response->getContent();

        $this->assertStringNotContainsString(self::NEW_PASSWORD, $body);
        $this->assertStringNotContainsString('password', $body);
        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_mobile_change_password_keeps_the_current_session_alive(): void
    {
        $token = $this->loginAsAsn();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/profile/change-password', [
                'current_password' => self::ASN_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])->assertStatus(200);

        $this->refreshApplicationAuth();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/profile')
            ->assertStatus(200);
    }

    // ---------------------------------------------------------------
    // Website: admin reset password
    // ---------------------------------------------------------------

    public function test_admin_can_reset_another_account_password(): void
    {
        $admin = $this->adminUser();
        $target = User::where('nip', self::ASN_NIP)->firstOrFail();
        $before = $target->password;

        $response = $this->actingAs($admin)
            ->post(route('user.reset-password', ['id' => $target->id]), [
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        $response->assertRedirect(route('user.list'));
        $response->assertSessionHas('success');

        $target->refresh();

        $this->assertNotSame($before, $target->password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $target->password));
        $this->assertNotSame(self::NEW_PASSWORD, $target->password, 'Password must never be stored in plaintext.');
    }

    public function test_admin_reset_password_is_stored_hashed(): void
    {
        $admin = $this->adminUser();
        $target = User::where('nip', self::ASN_NIP)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('user.reset-password', ['id' => $target->id]), [
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        $hash = $target->fresh()->password;

        $this->assertNotEquals($hash, self::NEW_PASSWORD);
        $this->assertSame('bcrypt', password_get_info($hash)['algoName']);
    }

    public function test_admin_reset_password_requires_matching_confirmation(): void
    {
        $admin = $this->adminUser();
        $target = User::where('nip', self::ASN_NIP)->firstOrFail();
        $before = $target->password;

        $this->actingAs($admin)
            ->post(route('user.reset-password', ['id' => $target->id]), [
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => 'TidakCocok2026',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame($before, $target->fresh()->password);
    }

    public function test_admin_reset_password_rejects_short_password(): void
    {
        $admin = $this->adminUser();
        $target = User::where('nip', self::ASN_NIP)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('user.reset-password', ['id' => $target->id]), [
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_non_admin_cannot_reset_password_through_admin_route(): void
    {
        $asn = User::where('nip', self::ASN_NIP)->firstOrFail();
        $target = $this->adminUser();
        $before = $target->password;

        $this->actingAs($asn)
            ->post(route('user.reset-password', ['id' => $target->id]), [
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertStatus(403);

        $this->assertSame($before, $target->fresh()->password);
    }

    public function test_non_admin_cannot_open_the_user_management_page(): void
    {
        $asn = User::where('nip', self::ASN_NIP)->firstOrFail();

        $this->actingAs($asn)->get(route('user.list'))->assertStatus(403);
        $this->actingAs($asn)->get(route('user.add'))->assertStatus(403);
    }

    public function test_guest_cannot_reset_password_through_admin_route(): void
    {
        $target = User::where('nip', self::ASN_NIP)->firstOrFail();
        $before = $target->password;

        // Guards only the invariant that matters: an unauthenticated caller can
        // never reach the reset action. The exact redirect target is handled by
        // the pre-existing Authenticate middleware, which is out of scope here.
        $this->post(route('user.reset-password', ['id' => $target->id]), [
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertGuest();
        $this->assertSame($before, $target->fresh()->password);
    }

    public function test_admin_reset_ignores_extra_fields_in_the_request(): void
    {
        $admin = $this->adminUser();
        $target = User::where('nip', self::ASN_NIP)->firstOrFail();
        $originalName = $target->name;
        $originalNip = $target->nip;
        $originalRoleId = $target->role_id;

        $this->actingAs($admin)
            ->post(route('user.reset-password', ['id' => $target->id]), [
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
                'name' => 'Hacked Name',
                'nip' => '000000000000000000',
                'role_id' => Role::where('code', Role::ADMIN)->value('id'),
                'username' => 'hacked',
            ]);

        $target->refresh();

        $this->assertSame($originalName, $target->name);
        $this->assertSame($originalNip, $target->nip);
        $this->assertSame($originalRoleId, $target->role_id);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $target->password));
    }

    public function test_admin_can_still_open_the_user_management_page(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get(route('user.list'))->assertStatus(200);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function adminUser(): User
    {
        $admin = User::where('username', 'admin')->first();

        if ($admin === null) {
            $this->markTestSkipped('Admin account not present in this database.');
        }

        return $admin;
    }

    private function loginAsAsn(): string
    {
        $response = $this->postJson('/api/auth/login', [
            'nip' => self::ASN_NIP,
            'password' => self::ASN_PASSWORD,
        ]);

        $response->assertStatus(200);

        return $response->json('data.token');
    }

    private function refreshApplicationAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('sanctum');
    }
}
