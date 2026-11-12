<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'guest_type',
        'division_id',
        'user_id',
        'ticket_type',
        'selfie',
        'status',
        'description',
        'schedule',
        'rejection_reason',
        'reschedule',
        'reschedule_reason',
        'employee_name',
        'meeting_result',
        'completed_at',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mobile-facing photo URL, or null when the visitor has no photo.
     *
     * The selfie column stores whatever the form produced, which on older
     * records is an absolute URL baked from APP_URL. Returning that raw value
     * would hand the app a localhost address, and the files themselves live in
     * storage/app/image which the web server cannot serve. The app therefore
     * always receives the authenticated API photo route, which streams the file
     * only to the ASN who owns the visit.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $selfie = trim((string) $this->selfie);

        if ($selfie === '') {
            return null;
        }

        return url('/api/guest/' . $this->id . '/photo');
    }

    /**
     * Absolute path of the stored photo file, or null when there is nothing to
     * stream. Only the basename is honoured so a crafted selfie value cannot
     * walk out of the image directory.
     */
    public function resolvePhotoPath(): ?string
    {
        $selfie = trim((string) $this->selfie);

        if ($selfie === '') {
            return null;
        }

        $name = basename(parse_url($selfie, PHP_URL_PATH) ?: $selfie);

        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }

        $base = storage_path('app/image');
        $path = $base . DIRECTORY_SEPARATOR . $name;

        return is_file($path) ? $path : null;
    }

    public function getTicketTypeStringAttribute()
    {
        $result = '';
        switch ($this->guest_type) {
            case 1:
                $result = 'Permintaan Layanan';
                break;
            case 2:
                $result = 'Memenuhi Undangan';
                break;
            case 3:
                $result = 'Kepentingan Lainnya';
                break;
            default:
                # code...
                break;
        }

        return $result;
    }

    public function getGuestTypeStringAttribute()
    {
        $result = '';
        switch ($this->guest_type) {
            case 1:
                $result = 'Penyedia';
                break;
            case 2:
                $result = 'Non Penyedia';
                break;
            case 3:
                $result = 'Lainnya';
                break;
            default:
                # code...
                break;
        }

        return $result;
    }
}
