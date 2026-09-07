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
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
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
