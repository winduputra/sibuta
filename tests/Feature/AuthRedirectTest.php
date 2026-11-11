<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthRedirectTest extends TestCase
{
    use DatabaseTransactions;

    private const ASN_NIP = '197006291998031004';

    private const SEEDED_PASSWORD = 'password';

    /**
     * Guest must still be able to open the login page (no redirect loop).
     */
    public function test_guest_can_open_login_page(): void
    {
        $response = $this->get(route('auth.login'));

        $response->assertOk();
        $response->assertViewIs('auth.login');
        $this->assertGuest();
    }

    /**
     * An authenticated admin visiting the login page is sent to the admin page.
     */
    public function test_admin_visiting_login_page_is_redirected_to_user_list(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('auth.login'));

        $response->assertRedirect(route('user.list'));
        $response->assertStatus(302);
    }

    /**
     * An authenticated ASN visiting the login page is sent to the report page,
     * never to an admin-only route.
     */
    public function test_asn_visiting_login_page_is_redirected_to_report_form(): void
    {
        $asn = $this->asnUser();

        $response = $this->actingAs($asn)->get(route('auth.login'));

        $response->assertRedirect(route('report.form'));
        $this->assertStringNotContainsString('user/list', $response->headers->get('Location'));
    }

    /**
     * The redirect target must resolve to a real route (the dead /report/home
     * route caused a 404 before this fix).
     */
    public function test_redirect_target_is_not_a_dead_route(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('report.home'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('user.list'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('report.form'));
    }

    /**
     * Admin can access the admin dashboard page.
     */
    public function test_admin_can_access_user_list(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get(route('user.list'))->assertOk();
    }

    /**
     * ASN can access their dashboard (report form).
     */
    public function test_asn_can_access_report_form(): void
    {
        $asn = $this->asnUser();

        $this->actingAs($asn)->get(route('report.form'))->assertOk();
    }

    /**
     * ASN is denied the admin-only page (403, not a redirect loop).
     */
    public function test_asn_is_forbidden_from_user_list(): void
    {
        $asn = $this->asnUser();

        $this->actingAs($asn)->get(route('user.list'))->assertForbidden();
    }

    /**
     * Website login sends admin to the admin page.
     */
    public function test_website_login_as_admin_redirects_to_user_list(): void
    {
        $admin = $this->adminUser();

        $response = $this->post(route('auth.login'), [
            'username' => $admin->username,
            'password' => self::SEEDED_PASSWORD,
        ]);

        $response->assertRedirect(route('user.list'));
        $this->assertAuthenticatedAs($admin);
    }

    /**
     * Website login sends ASN to the report page, not to the admin-only page.
     */
    public function test_website_login_as_asn_redirects_to_report_form(): void
    {
        $asn = $this->asnUser();

        $response = $this->post(route('auth.login'), [
            'username' => $asn->username,
            'password' => self::SEEDED_PASSWORD,
        ]);

        $response->assertRedirect(route('report.form'));
        $this->assertAuthenticatedAs($asn);
    }

    /**
     * The landing page for an ASN must be openable right after login,
     * otherwise the redirect produces a 403 page.
     */
    public function test_asn_landing_page_is_openable_after_login(): void
    {
        $asn = $this->asnUser();

        $login = $this->post(route('auth.login'), [
            'username' => $asn->username,
            'password' => self::SEEDED_PASSWORD,
        ]);

        $target = $login->headers->get('Location');
        $this->assertNotNull($target);

        $this->actingAs($asn)->get($target)->assertOk();
    }

    /**
     * Login with invalid credentials must not authenticate and must flag an error.
     */
    public function test_login_with_wrong_password_redirects_back_with_error(): void
    {
        $admin = $this->adminUser();

        $response = $this->post(route('auth.login'), [
            'username' => $admin->username,
            'password' => 'definitely-not-the-password',
        ]);

        $response->assertRedirect(route('auth.login'));
        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    private function adminUser(): User
    {
        $adminRoleId = Role::where('code', Role::ADMIN)->value('id');
        $admin = User::where('role_id', $adminRoleId)->first();

        if ($admin === null) {
            $this->markTestSkipped('No admin account present in the database.');
        }

        return $admin;
    }

    private function asnUser(): User
    {
        $asn = User::where('nip', self::ASN_NIP)->first();

        if ($asn === null) {
            $this->markTestSkipped('ASN test account not present in the database.');
        }

        return $asn;
    }
}
