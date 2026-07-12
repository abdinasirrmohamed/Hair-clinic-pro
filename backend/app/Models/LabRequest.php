<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'patient_id',
        'appointment_id',
        'doctor_id',
        'lab_test_id',
        'request_date',
        'status',
        'result',
        'notes',
        'created_by',
        'completed_at',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function test()
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
