<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            'Apakah anda puas dengan metode penyampaian permasalahan?',
            'Seberapa besar tingkat kepuasan anda terhadap kecepatan penanganan permasalahan dari kami?',
            'Seberapa puaskah anda tentang kualitas cara penyelesaian permasalahan dari kami?',
            'Seberapa besar kepuasan anda kepada kami terhadap kesesuaian penyelesaian permasalahan yang disampaikan?',
            'Apakah anda puas dengan keramahan Petugas Helpdesk kami pada saat memberikan layanan?',
            'Seberapa puaskah anda terhadap informasi yang diberikan oleh petugas kami?',
            'Apakah anda puas dengan ketepatan waktu pelayanan kami?',
            'Apakah anda merasa puas dengan kualitas pelayanan kami?',
            'Seberapa besar kepuasan anda akan fasilitas yang kami sediakan?',
            'Seberapa besar tingkat kepuasan anda terhadap kesesuaian layanan kami dengan permintaan anda?',
            'Seberapa cepat LPSE Provinsi Lampng bisa di Akses?',
        ];

        foreach ($questions as $question) {
            Question::firstOrCreate(['question' => $question]);
        }
    }
}
