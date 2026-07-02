<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacySale extends Model
{
    use HasFactory;

    protected $table = 'pharmacy_sales';

    const UPDATED_AT = null;

    protected $fillable = [
        'sale_number',
        'customer_name',
        'patient_id',
        'prescription_id',
        'medicine_count',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_percent',
        'tax_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'status',
        'returned_at',
        'return_reason',
        'notes',
        'created_by',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class, 'prescription_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function medicines()
    {
        return $this->hasMany(PharmacySaleMedicine::class, 'sale_id');
    }
}
