<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GuestAccept;
use App\Mail\GuestReject;
use App\Mail\GuestReschedule;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class GuestController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function list()
    {
        $auth = Auth::user();

        $ticketType = $this->request->ticket_type;
        $search = $this->request->search;
        
        if( !$ticketType ) {
            return response()->json(['success' => false, 'message' => 'Please Provide Ticket Type'], 400);
        }

        $query = Guest::where('user_id', '=', $auth->id)->where('ticket_type', '=', $ticketType);

        if( $ticketType == -1 ) {
            $query = Guest::where('user_id', '=', $auth->id);
        }

        if( $search ) {
            $search = '%'.$search.'%';
            $query = $query->where(function($q) use($search) {
                return $q->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search)->orWhere('description', 'like', $search)->orWhere('schedule', 'like', $search);
            });
        }

        return $query->where('status', '=', 0)->paginate(1000);
    }

    public function today()
    {
        $auth = Auth::user();

        $ticketType = $this->request->ticket_type;
        
        if( !$ticketType ) {
            return response()->json(['success' => false, 'message' => 'Please Provide Ticket Type'], 400);
        }

        return Guest::where('user_id', '=', $auth->id)->where('ticket_type', '=', $ticketType)->where('status', '=', 1)->paginate(1000);
    }

    public function accept($id)
    {
        $auth = Auth::user();

        $guest = Guest::where('id', '=', $id)->where('user_id', '=', $auth->id)->first();
        if( !$guest ) {
            return response()->json(['success' => false, 'message' => 'Data Tidak Ditemukan'], 404);
        }

        if( $guest->status != 0 ) {
            return response()->json(['success' => false, 'message' => 'Data Sudah Diberi Aksi'], 404);
        }

        $guest->update(['status' => 1]);

        $guest = $guest->refresh();

        try {
            Mail::to($guest->email)->send(new GuestAccept($guest));
        } catch (\Exception $e) {
            //throw $th;
        }

        return response()->json(['success' => true, 'data' => $guest]);
    }

    public function reject($id)
    {
        $auth = Auth::user();

        $this->request->validate([
            'rejection_reason' => ['required']
        ]);

        $guest = Guest::where('id', '=', $id)->where('user_id', '=', $auth->id)->first();
        
        if( !$guest ) {
            return response()->json(['success' => false, 'message' => 'Data Tidak Ditemukan'], 404);
        }

        if( $guest->status != 0 ) {
            return response()->json(['success' => false, 'message' => 'Data Sudah Diberi Aksi'], 404);
        }

        $payloads = $this->request->only(['rejection_reason']);

        $guest->update(['status' => -1, 'rejection_reason' => $payloads['rejection_reason']]);

        $guest = $guest->refresh();

        try {
            Mail::to($guest->email)->send(new GuestReject($guest));
        } catch (\Exception $e) {
            //throw $th;
        }

        return response()->json(['success' => true, 'data' => $guest]);
    }

    public function reschedule($id)
    {
        $auth = Auth::user();

        $this->request->validate([
            'reschedule_reason' => ['required'],
            'reschedule' => ['required'],
        ]);

        $guest = Guest::where('id', '=', $id)->where('user_id', '=', $auth->id)->first();
        if( !$guest ) {
            return response()->json(['success' => false, 'message' => 'Data Tidak Ditemukan'], 404);
        }

        if( $guest->status != 0 ) {
            return response()->json(['success' => false, 'message' => 'Data Sudah Diberi Aksi'], 404);
        }

        $payloads = $this->request->only(['reschedule_reason', 'reschedule']);

        $guest->update(['status' => -1, 'reschedule_reason' => $payloads['reschedule_reason'], 'reschedule' => $payloads['reschedule']]);

        $guest = $guest->refresh();

        try {
            Mail::to($guest->email)->send(new GuestReschedule($guest));
        } catch (\Exception $e) {
            //throw $th;
        }

        return response()->json(['success' => true, 'data' => $guest]);
    }
}
