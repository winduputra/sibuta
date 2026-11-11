<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Every seeder listed here MUST be idempotent (updateOrCreate /
     * firstOrCreate) so that re-running `db:seed` never duplicates rows.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DivisionSeeder::class,
            QuestionSeeder::class,
            UserSeeder::class,
            StaffSeeder::class,
        ]);
    }
}
