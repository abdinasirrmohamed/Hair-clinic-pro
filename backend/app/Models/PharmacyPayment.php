<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacyPayment extends Model
{
    use HasFactory;

    protected $table = 'pharmacy_payments';

    const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_method',
        'reference_number',
    ];

    public function invoice()
    {
        return $this->belongsTo(PharmacyInvoice::class, 'invoice_id');
    }
}
