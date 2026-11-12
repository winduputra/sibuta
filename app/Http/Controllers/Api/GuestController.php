<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GuestAccept;
use App\Mail\GuestReject;
use App\Mail\GuestReschedule;
use App\Models\Guest;
use App\Support\GuestNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class GuestController extends Controller
{
    /**
     * Guest status values, mirroring the comment on the guests.status column:
     * 0 pending, 1 approved, -1 rejected, 2 reschedule.
     */
    private const STATUS_PENDING = 0;

    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Pending requests assigned to the signed-in ASN.
     *
     * ticket_type is kept for backwards compatibility with the old web
     * dashboard; -1 means "every ticket type".
     */
    public function list()
    {
        $auth = Auth::user();

        $ticketType = $this->request->ticket_type;
        $search = $this->request->search;

        $query = Guest::where('user_id', '=', $auth->id)
            ->where('status', '=', self::STATUS_PENDING)
            ->orderBy('schedule', 'asc')
            ->orderBy('id', 'asc');

        if ($ticketType !== null && $ticketType !== '' && (int) $ticketType !== -1) {
            $query = $query->where('ticket_type', '=', $ticketType);
        }

        if ($search) {
            $search = '%' . $search . '%';
            $query = $query->where(function ($q) use ($search) {
                return $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('schedule', 'like', $search);
            });
        }

        return $query->get();
    }

    public function today()
    {
        $auth = Auth::user();

        $ticketType = $this->request->ticket_type;

        if (!$ticketType) {
            return response()->json(['success' => false, 'message' => 'Please Provide Ticket Type'], 400);
        }

        return Guest::where('user_id', '=', $auth->id)->where('ticket_type', '=', $ticketType)->where('status', '=', 1)->paginate(1000);
    }

    public function accept($id)
    {
        $guest = $this->findOwnedPendingGuest($id);

        if ($guest instanceof \Illuminate\Http\JsonResponse) {
            return $guest;
        }

        $guest->update(['status' => 1]);

        $guest = $guest->refresh();

        try {
            Mail::to($guest->email)->send(new GuestAccept($guest));
        } catch (\Exception $e) {
            // Email is best effort: a dead SMTP server must not undo the decision.
        }

        return response()->json(['success' => true, 'data' => $guest]);
    }

    public function reject($id)
    {
        $this->request->validate([
            'rejection_reason' => ['required'],
        ]);

        $guest = $this->findOwnedPendingGuest($id);

        if ($guest instanceof \Illuminate\Http\JsonResponse) {
            return $guest;
        }

        $payloads = $this->request->only(['rejection_reason']);

        $guest->update(['status' => -1, 'rejection_reason' => $payloads['rejection_reason']]);

        $guest = $guest->refresh();

        try {
            Mail::to($guest->email)->send(new GuestReject($guest));
        } catch (\Exception $e) {
            // Email is best effort.
        }

        return response()->json(['success' => true, 'data' => $guest]);
    }

    public function reschedule($id)
    {
        $this->request->validate([
            'reschedule_reason' => ['required'],
            'reschedule' => ['required'],
        ]);

        $guest = $this->findOwnedPendingGuest($id);

        if ($guest instanceof \Illuminate\Http\JsonResponse) {
            return $guest;
        }

        $payloads = $this->request->only(['reschedule_reason', 'reschedule']);

        $guest->update([
            'status' => 2,
            'reschedule_reason' => $payloads['reschedule_reason'],
            'reschedule' => $payloads['reschedule'],
        ]);

        $guest = $guest->refresh();

        try {
            Mail::to($guest->email)->send(new GuestReschedule($guest));
        } catch (\Exception $e) {
            // Email is best effort.
        }

        return response()->json(['success' => true, 'data' => $guest]);
    }

    /**
     * Resolves a guest that belongs to the caller and is still pending.
     *
     * Scoping by user_id is what prevents one ASN from acting on another
     * ASN's visitor: an id belonging to a colleague resolves to 404, never to
     * a successful action. A request already answered returns 409 so the app
     * can distinguish "not yours" from "already decided".
     */
    private function findOwnedPendingGuest($id)
    {
        $auth = Auth::user();

        $guest = Guest::where('id', '=', $id)->where('user_id', '=', $auth->id)->first();

        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Data Tidak Ditemukan'], 404);
        }

        if ((int) $guest->status !== self::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Data Sudah Diberi Aksi'], 409);
        }

        return $guest;
    }
}
