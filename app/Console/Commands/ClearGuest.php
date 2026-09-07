<?php

namespace App\Console\Commands;

use App\Models\Guest;
use Illuminate\Console\Command;

class ClearGuest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'guest:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to clear the guest';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $guests = Guest::where('schedule', '<=', date('Y-m-d') . ' 00:00:00')->where('status', '=', 0)->get();
        $this->info('Jumlah Tamu belum ditangani ' . count($guests));

        Guest::where('schedule', '<=', date('Y-m-d') . ' 00:00:00')->where('status', '=', 0)->update(['status' => 1]);
    }
}
