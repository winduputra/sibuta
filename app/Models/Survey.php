<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'organization',
        'position',
        'address',
        'phone',
        'guest_type',
        'survey_questions',
        'survey_answers',
        'review',
    ];

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
