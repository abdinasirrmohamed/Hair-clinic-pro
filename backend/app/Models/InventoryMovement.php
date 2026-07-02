<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $table = 'inventory_movements';

    const UPDATED_AT = null;

    protected $fillable = [
        'transaction_number',
        'medicine_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'supplier_id',
        'department',
        'purpose',
        'reference_type',
        'reference_id',
        'invoice_path',
        'issued_by',
        'movement_date',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
