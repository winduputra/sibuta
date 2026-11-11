<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Idempotent: firstOrCreate keyed on `username`, so re-running never
     * duplicates accounts and never overwrites an existing admin password.
     * Only the initial admin account is provisioned here.
     * ASN accounts are created by StaffSeeder.
     */
    public function run(): void
    {
        $adminRoleId = Role::where('code', Role::ADMIN)->value('id');

        if ($adminRoleId === null) {
            throw new RuntimeException(
                'Role "' . Role::ADMIN . '" not found. Run RoleSeeder before UserSeeder.'
            );
        }

        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'nip' => null,
                'division_id' => null,
                'role_id' => $adminRoleId,
                'password' => Hash::make($this->adminPassword()),
            ]
        );
    }

    /**
     * Password is never hardcoded and never logged. It must be supplied through
     * the environment so no default credential can ship to production.
     *
     * The exception message intentionally omits the value.
     */
    private function adminPassword(): string
    {
        $password = env('SIBUTA_ADMIN_PASSWORD');

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException(
                'SIBUTA_ADMIN_PASSWORD is not set. Define it in the environment before running UserSeeder.'
            );
        }

        return $password;
    }
}
