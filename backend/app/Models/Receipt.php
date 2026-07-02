<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $table = 'receipts';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'receipt_number',
        'clinic_name',
        'clinic_phone',
        'clinic_address',
        'issued_at',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
