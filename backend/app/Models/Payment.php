<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    const UPDATED_AT = null;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'amount',
        'payment_method',
        'payment_status',
        'reference_number',
        'notes',
        'created_by',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class, 'payment_id');
    }
}
