<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayLog extends Model
{
    protected $fillable = [
        'gateway',
        'reference_id',
        'invoice_id',
        'amount',
        'account_masked',
        'status',
        'response_code',
        'transaction_id',
        'message',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
