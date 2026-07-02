<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacyInvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'pharmacy_invoice_items';

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'medicine_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    public function invoice()
    {
        return $this->belongsTo(PharmacyInvoice::class, 'invoice_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }
}
