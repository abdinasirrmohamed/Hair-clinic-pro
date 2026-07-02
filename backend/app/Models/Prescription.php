<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;

    protected $table = 'prescriptions';

    const UPDATED_AT = null;

    protected $fillable = [
        'prescription_number',
        'patient_id',
        'doctor_id',
        'prescription_date',
        'status',
        'instructions',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function medicines()
    {
        return $this->hasMany(PrescriptionMedicine::class, 'prescription_id');
    }

    public function sales()
    {
        return $this->hasMany(PharmacySale::class, 'prescription_id');
    }
}
