<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacyInvoice extends Model
{
    use HasFactory;

    protected $table = 'pharmacy_invoices';

    const UPDATED_AT = null;

    protected $fillable = [
        'invoice_number',
        'patient_id',
        'total_amount',
        'payment_method',
        'payment_status',
        'notes',
        'created_by',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PharmacyInvoiceItem::class, 'invoice_id');
    }

    public function payments()
    {
        return $this->hasMany(PharmacyPayment::class, 'invoice_id');
    }
}
