<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $table = 'doctors';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'full_name',
        'specialization',
        'qualification',
        'phone',
        'consultation_fee',
        'email',
        'license_number',
        'photo',
        'experience_years',
        'availability_schedule',
        'bio',
        'status',
    ];

    protected $casts = [
        'experience_years' => 'integer',
        'consultation_fee' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function schedules()
    {
        return $this->hasMany(DoctorSchedule::class, 'doctor_id');
    }

    public function blockedDates()
    {
        return $this->hasMany(DoctorBlockedDate::class, 'doctor_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }
}
