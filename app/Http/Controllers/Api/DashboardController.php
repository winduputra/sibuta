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

        $todayGuest = Guest::where('user_id', '=', $auth->id)->where('schedule', '=', date('Y-m-d'))->count();
        $thisMonth = Guest::where('user_id', '=', $auth->id)->where('schedule', '>=', date('Y-m-') . '01 00:00:00')->where('schedule', '<=', date('Y-m-t') . ' 23:59:59')->count();
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
