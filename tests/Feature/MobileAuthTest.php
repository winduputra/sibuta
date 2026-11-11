<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mobile (ASN) authentication.
 *
 * Runs against the local database inside a transaction that is rolled back,
 * so production-like seed data is never modified.
 */
class MobileAuthTest extends TestCase
{
    use DatabaseTransactions;

    private const ASN_NAME = 'DODI HENDRAWAN, ST., MEP';
    private const ASN_NIP = '197006291998031004';
    private const SHORT_NIP = '19700629199803100';
    private const LONG_NIP = '1970062919980310045';
    private const ALPHA_NIP = '19700629199803100A';

    public function test_asn_with_valid_nip_and_password_can_login(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'nip' => self::ASN_NIP,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nip', self::ASN_NIP)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'username', 'nip', 'token', 'division_id', 'division_name', 'image'],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_admin_cannot_login_to_mobile(): void
    {
        // The admin account is provisioned by UserSeeder with password 'password'.
        $admin = User::where('username', 'admin')->first();

        if ($admin === null) {
            $this->markTestSkipped('Admin account not present in this database.');
        }

        $response = $this->postJson('/api/auth/login', [
            'nip' => (string) $admin->nip,
            'password' => 'password',
        ]);

        // Admin has no NIP at all, so it must fail validation, not authenticate.
        $response->assertStatus(422);

        // Even if an admin row somehow carried a NIP, the role guard must reject it.
        $admin->forceFill(['nip' => '888888888888888888'])->save();

        $this->postJson('/api/auth/login', [
            'nip' => '888888888888888888',
            'password' => 'password',
        ])->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_unknown_nip_is_rejected_without_disclosing_existence(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'nip' => '999999999999999999',
            'password' => 'password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'NIP atau password salah');
    }

    public function test_wrong_password_is_rejected_with_same_response_as_unknown_nip(): void
    {
        $wrongPassword = $this->postJson('/api/auth/login', [
            'nip' => self::ASN_NIP,
            'password' => 'not-the-password',
        ]);

        $unknownNip = $this->postJson('/api/auth/login', [
            'nip' => '999999999999999999',
            'password' => 'password',
        ]);

        $wrongPassword->assertStatus(401)->assertJsonPath('message', 'NIP atau password salah');
        $this->assertSame($unknownNip->json(), $wrongPassword->json());
    }

    public function test_nip_with_wrong_length_fails_validation(): void
    {
        $this->postJson('/api/auth/login', [
            'nip' => self::SHORT_NIP,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('nip');

        $this->postJson('/api/auth/login', [
            'nip' => self::LONG_NIP,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('nip');
    }

    public function test_non_numeric_nip_fails_validation(): void
    {
        $this->postJson('/api/auth/login', [
            'nip' => self::ALPHA_NIP,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('nip');
    }

    public function test_prefix_of_a_real_nip_does_not_authenticate(): void
    {
        // Guards against the old LIKE/prefix matching behaviour.
        $this->postJson('/api/auth/login', [
            'nip' => substr(self::ASN_NIP, 0, 10),
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('nip');
    }

    public function test_issued_token_can_access_protected_endpoints(): void
    {
        $token = $this->loginAsAsn();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_logout_revokes_the_active_token(): void
    {
        $token = $this->loginAsAsn();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // The framework keeps the resolved guard between requests in one test,
        // so reset auth state to force a genuine re-authentication attempt.
        $this->refreshApplicationAuth();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/profile')
            ->assertStatus(401);
    }

    public function test_logout_only_revokes_the_current_token(): void
    {
        $firstToken = $this->loginAsAsn();
        $secondToken = $this->loginAsAsn();

        $this->assertNotSame($firstToken, $secondToken);

        $this->withHeader('Authorization', 'Bearer ' . $firstToken)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // The second device session must survive.
        $this->refreshApplicationAuth();

        $this->withHeader('Authorization', 'Bearer ' . $secondToken)
            ->getJson('/api/profile')
            ->assertStatus(200);
    }

    public function test_logout_removes_only_its_own_device_token(): void
    {
        $token = $this->loginAsAsn(['device_token' => 'fcm-token-under-test']);
        $user = User::where('nip', self::ASN_NIP)->firstOrFail();

        $otherDevice = Device::create([
            'device_token' => 'other-device-token',
            'device_name' => 'Other Device',
            'user_id' => $user->id,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout', ['device_token' => 'fcm-token-under-test'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('devices', ['device_token' => 'fcm-token-under-test']);
        $this->assertDatabaseHas('devices', ['id' => $otherDevice->id]);
    }

    public function test_login_registers_device_token_without_duplicates(): void
    {
        $this->loginAsAsn(['device_token' => 'duplicate-check-token']);
        $this->loginAsAsn(['device_token' => 'duplicate-check-token']);

        $this->assertSame(1, Device::where('device_token', 'duplicate-check-token')->count());
    }

    public function test_website_login_still_uses_username_and_password(): void
    {
        $response = $this->post('/auth/login', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        // Success redirects away from the login form; a failure would bounce back.
        $response->assertRedirect(route('user.list'));
        $this->assertAuthenticated();
    }

    /**
     * Log in as the seeded ASN account and return the plaintext token.
     */
    private function loginAsAsn(array $extra = []): string
    {
        $response = $this->postJson('/api/auth/login', array_merge([
            'nip' => self::ASN_NIP,
            'password' => 'password',
        ], $extra));

        $response->assertStatus(200);

        return $response->json('data.token');
    }

    /**
     * Forget the user resolved by the Sanctum guard so the next request must
     * authenticate from the Bearer token again.
     */
    private function refreshApplicationAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('sanctum');
    }
}
