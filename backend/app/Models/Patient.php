<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patients';

    const UPDATED_AT = null;

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'gender',
        'age',
        'date_of_birth',
        'address',
        'medical_notes',
        'assigned_doctor_id',
    ];

    protected $casts = [
        'age' => 'integer',
        'date_of_birth' => 'date:Y-m-d',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class, 'patient_id');
    }

    public function followups()
    {
        return $this->hasMany(Followup::class, 'patient_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'patient_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'patient_id');
    }

    public function assignedDoctor()
    {
        return $this->belongsTo(Doctor::class, 'assigned_doctor_id');
    }
}
