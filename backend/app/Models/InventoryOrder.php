<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryOrder extends Model
{
    use HasFactory;

    protected $table = 'inventory_orders';

    const UPDATED_AT = null;

    protected $fillable = [
        'item_id',
        'order_code',
        'quantity',
        'priority',
        'shipping_cost',
        'total_estimate',
        'order_status',
        'note',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
