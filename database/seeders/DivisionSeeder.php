<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Kepala Biro',
            'Bagian Pengelolaan Pengadaan',
            'Bagian LPSE',
            'Sub Bagian Umum',
            'Bagian Pembinaan dan Advokasi'
        ];
        

        foreach ($data as $key => $name) {
            $division = Division::firstOrCreate(['name' => $name]);
        }
    }
}
