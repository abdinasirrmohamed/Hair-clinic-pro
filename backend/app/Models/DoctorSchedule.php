<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    use HasFactory;

    protected $table = 'doctor_schedules';

    protected $fillable = [
        'doctor_id',
        'day_of_week',
        'shift',
        'start_time',
        'end_time',
        'slot_minutes',
        'is_working',
    ];

    protected $casts = ['is_working' => 'boolean', 'slot_minutes' => 'integer'];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }
}
