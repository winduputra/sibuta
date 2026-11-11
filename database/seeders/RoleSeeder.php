<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Idempotent: safe to re-run. Fixed IDs keep the legacy role_id mapping
     * stable once it is decided (see audit: mapping still unconfirmed).
     */
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'code' => Role::ADMIN, 'name' => 'Admin Website'],
            ['id' => 2, 'code' => Role::ASN, 'name' => 'ASN'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['id' => $role['id']], [
                'code' => $role['code'],
                'name' => $role['name'],
            ]);
        }
    }
}
