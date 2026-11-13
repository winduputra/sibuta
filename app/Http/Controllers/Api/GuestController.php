<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GuestAccept;
use App\Mail\GuestReject;
use App\Mail\GuestReschedule;
use App\Models\Guest;
use App\Support\GuestNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class GuestController extends Controller
{
    /**
     * Guest status values, mirroring the comment on the guests.status column:
     * 0 pending, 1 approved, -1 rejected, 2 reschedule, 3 completed.
     */
    private const STATUS_PENDING = 0;

    private const STATUS_APPROVED = 1;

    private const STATUS_RESCHEDULED = 2;

    private const STATUS_REJECTED = -1;

    private const STATUS_COMPLETED = 3;

    /**
     * Statuses that still expect an accept/reject decision from the ASN.
     */
    private const UNDECIDED_STATUSES = [self::STATUS_PENDING, self::STATUS_RESCHEDULED];


    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Visits assigned to the signed-in ASN, filtered by status.
     *
     * Defaults to the requests still awaiting a decision (status 0 and 2, see
     * awaitingDecision()), status=answered for the same set explicitly,
     * status=1 for accepted visits the ASN can now close, status=3 for the
     * completed ones, and status=all for every visit of this ASN.
     * ticket_type is kept for backwards compatibility with the old web
     * dashboard; -1 means "every ticket type".
     */
    public function list()
    {
        $auth = Auth::user();

        $ticketType = $this->request->ticket_type;
        $search = $this->request->search;
        $status = $this->request->status;

        $query = Guest::where('user_id', '=', $auth->id)
            // Newest request first: an ASN works through the visits that just
            // arrived, not the ones from weeks ago. created_at also carries the
            // real submission moment while schedule only holds a date.
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if ($status === 'all') {
            // No status filter: the history tab wants every visit.
        } elseif ($status === 'answered') {
            // Every visit the ASN has not decided yet, reschedules included.
            $query = $this->awaitingDecision($query);
        } elseif ($status !== null && $status !== '') {
            $query = $query->where('status', '=', (int) $status);
        } else {
            $query = $this->awaitingDecision($query);
        }

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

        return $this->respondWithGuests($query->get());
    }

    public function today()
    {
        $auth = Auth::user();

        $ticketType = $this->request->ticket_type;

        if (!$ticketType) {
            return response()->json(['success' => false, 'message' => 'Please Provide Ticket Type'], 400);
        }

        // schedule is a date column, so "today" has to be the WIB date rather
        // than whatever date the server clock happens to be on.
        $today = now()->timezone('Asia/Jakarta')->format('Y-m-d');

        return Guest::where('user_id', '=', $auth->id)
            ->where('ticket_type', '=', $ticketType)
            ->where('status', '=', self::STATUS_APPROVED)
            ->where('schedule', '=', $today)
            ->paginate(1000);
    }

    public function accept($id)
    {
        $guest = $this->findOwnedUndecidedGuest($id);

        if ($guest instanceof \Illuminate\Http\JsonResponse) {
            return $guest;
        }

        // Rescheduling only records a new date; the visit still needs an
        // answer, so accepting it must not be refused as "already answered".
        // Clearing reschedule_reason keeps the accepted visit free of a stale
        // explanation, while the reschedule column stays as the meeting date.
        $guest->update([
            'status' => self::STATUS_APPROVED,
            'reschedule_reason' => null,
        ]);

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

        $guest = $this->findOwnedUndecidedGuest($id);

        if ($guest instanceof \Illuminate\Http\JsonResponse) {
            return $guest;
        }

        $payloads = $this->request->only(['rejection_reason']);

        $guest->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $payloads['rejection_reason'],
        ]);

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

        $guest = $this->findOwnedUndecidedGuest($id);

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
     * Closes an accepted visit and records what happened in the meeting.
     *
     * Only an accepted visit (status 1) can be completed, so the ASN cannot
     * skip the accept step, and a second completion attempt is rejected with
     * 409 instead of overwriting the original result and timestamp.
     */
    public function complete($id)
    {
        $this->request->validate([
            'meeting_result' => ['required', 'string', 'max:2000'],
        ], [
            'meeting_result.required' => 'Hasil pertemuan wajib diisi.',
            'meeting_result.max' => 'Hasil pertemuan maksimal 2000 karakter.',
        ]);

        $auth = Auth::user();

        $guest = Guest::where('id', '=', $id)->where('user_id', '=', $auth->id)->first();

        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Data Tidak Ditemukan'], 404);
        }

        if ((int) $guest->status !== self::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya kunjungan yang sudah diterima dapat diselesaikan',
            ], 409);
        }

        $guest->update([
            'status' => self::STATUS_COMPLETED,
            'meeting_result' => trim((string) $this->request->meeting_result),
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $guest->refresh()]);
    }

    /**
     * Streams the visitor photo to the ASN who owns the visit.
     *
     * The files sit in storage/app/image, outside the web root, so they are
     * only reachable through this route. Scoping the lookup by user_id means a
     * photo id belonging to another ASN returns 404 rather than leaking the
     * image, and no public directory ever exposes every guest photo at once.
     */
    public function photo($id)
    {
        $auth = Auth::user();

        $guest = Guest::where('id', '=', $id)->where('user_id', '=', $auth->id)->first();

        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Data Tidak Ditemukan'], 404);
        }

        $path = $guest->resolvePhotoPath();

        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'Foto Tidak Tersedia'], 404);
        }

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Turns the photo_url accessor into an absolute URL on the host the app
     * actually called, so the app never receives a localhost address even when
     * APP_URL on the server is unset or stale.
     */
    private function respondWithGuests($guests)
    {
        $base = $this->request->getSchemeAndHttpHost();

        return $guests->map(function (Guest $guest) use ($base) {
            $payload = $guest->toArray();
            $payload['photo_url'] = $guest->photo_url === null
                ? null
                : $base . $guest->photo_url;

            return $payload;
        });
    }

    /**
     * Resolves a guest that belongs to the caller and still needs a decision.
     *
     * Scoping by user_id is what prevents one ASN from acting on another
     * ASN's visitor: an id belonging to a colleague resolves to 404, never to
     * a successful action. A request already answered returns 409 so the app
     * can distinguish "not yours" from "already decided".
     *
     * A rescheduled visit keeps status 2 indefinitely and is still undecided,
     * so it has to be answered as well as status 0. Accepting the visitor first
     * returns 409 here only when the latest server state is approved, rejected
     * or completed - which is the only case the ASN should ever see.
     */
    private function findOwnedUndecidedGuest($id)
    {
        $auth = Auth::user();

        $guest = Guest::where('id', '=', $id)->where('user_id', '=', $auth->id)->first();

        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Data Tidak Ditemukan'], 404);
        }

        if (!in_array((int) $guest->status, self::UNDECIDED_STATUSES, true)) {
            return response()->json(['success' => false, 'message' => 'Data Sudah Diberi Aksi'], 409);
        }

        return $guest;
    }

    /**
     * Restricts a query to the visits still awaiting an ASN decision.
     */
    private function awaitingDecision(Builder $query): Builder
    {
        return $query->whereIn('status', self::UNDECIDED_STATUSES);
    }

    /**
     * Current status of a guest as the server sees it right now.
     *
     * Used when the app answers with a status it had cached locally: the edit
     * has to be conditional on the live row, not on the copy the phone held.
     */
    public function status($id)
    {
        $guest = $this->findOwnedGuest($id);

        if ($guest instanceof \Illuminate\Http\JsonResponse) {
            return $guest;
        }

        return response()->json(['success' => true, 'data' => $guest]);
    }
    /**
     * Completion statistics behind the Riwayat summary card.
     *
     * The window is applied on completed_at, the moment the visit was closed,
     * never on schedule or on the submission date. Query parameters:
     * period=month|week|custom, start_date, end_date as `Y-m-d`.
     */
    public function historyStats()
    {
        $auth = Auth::user();

        $period = $this->request->period ?: 'month';

        [$start, $end] = $this->resolvePeriod($period);

        if ($start === null || $end === null) {
            return response()->json([
                'success' => false,
                'message' => 'Periode laporan tidak valid.',
            ], 422);
        }

        $base = Guest::where('user_id', '=', $auth->id)
            ->where('status', '=', self::STATUS_COMPLETED)
            ->whereNotNull('completed_at');

        $total = (clone $base)
            ->where('completed_at', '>=', $start->copy()->startOfDay())
            ->where('completed_at', '<=', $end->copy()->endOfDay())
            ->count();

        $today = now()->timezone('Asia/Jakarta')->startOfDay();

        $completedToday = (clone $base)
            ->where('completed_at', '>=', $today)
            ->where('completed_at', '<=', $today->copy()->endOfDay())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'total' => $total,
                'completed_today' => $completedToday,
            ],
        ]);
    }

    /**
     * Completed visits the ASN closed today, in WIB, newest decision first.
     */
    public function historyToday()
    {
        $auth = Auth::user();

        $today = now()->timezone('Asia/Jakarta')->startOfDay();

        $guests = Guest::where('user_id', '=', $auth->id)
            ->where('status', '=', self::STATUS_COMPLETED)
            ->where('completed_at', '>=', $today)
            ->where('completed_at', '<=', $today->copy()->endOfDay())
            ->orderBy('completed_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $this->respondWithGuests($guests);
    }

    /**
     * Resolves the day window of a stats period in WIB.
     *
     * Returns [null, null] for an unknown or incomplete period so the caller
     * answers 422 instead of silently counting every completion.
     */
    private function resolvePeriod(string $period)
    {
        $now = now()->timezone('Asia/Jakarta');

        switch ($period) {
            case 'month':
                return [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfMonth()->startOfDay()];
            case 'week':
                // Carbon starts the week on Monday, matching the app's filter.
                return [$now->copy()->startOfWeek()->startOfDay(), $now->copy()->endOfWeek()->startOfDay()];
            case 'custom':
                $start = $this->parseDate($this->request->start_date);
                $end = $this->parseDate($this->request->end_date);

                if ($start === null || $end === null) {
                    return [null, null];
                }

                // A reversed range is a typo, not an empty report.
                return $end->lt($start) ? [$end, $start] : [$start, $end];
            default:
                return [null, null];
        }
    }

    private function parseDate($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d', trim($value), 'Asia/Jakarta')->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resolves a guest owned by the caller, whatever its status.
     */
    private function findOwnedGuest($id)
    {
        $auth = Auth::user();

        $guest = Guest::where('id', '=', $id)->where('user_id', '=', $auth->id)->first();

        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Data Tidak Ditemukan'], 404);
        }

        return $guest;
    }
}