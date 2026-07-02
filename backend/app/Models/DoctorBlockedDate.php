<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorBlockedDate extends Model
{
    use HasFactory;

    protected $table = 'doctor_blocked_dates';

    const UPDATED_AT = null;

    protected $fillable = [
        'doctor_id',
        'block_date',
        'block_type',
        'reason',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }
}
