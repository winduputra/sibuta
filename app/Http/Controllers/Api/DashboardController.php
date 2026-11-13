<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function list()
    {
        $auth = Auth::user();

        // schedule stores a plain date, so today's boundary must be the WIB
        // date. Using date() here followed the server clock instead.
        $today = now()->timezone('Asia/Jakarta');

        $todayGuest = Guest::where('user_id', '=', $auth->id)->where('schedule', '=', $today->format('Y-m-d'))->count();
        $thisMonth = Guest::where('user_id', '=', $auth->id)->where('schedule', '>=', $today->format('Y-m-') . '01')->where('schedule', '<=', $today->format('Y-m-t'))->count();
        $rating = 0;

        return response()->json([
            'success' => true,
            'data' => [
                'today_guest' => $todayGuest,
                'this_week_guest' => $thisMonth,
                'rating' => $rating,
                'link_chart' => route('chart', ['id' => $auth->id]),
                'link_download' => route('download', ['id' => $auth->id]),
            ]
        ]);
    }
}
