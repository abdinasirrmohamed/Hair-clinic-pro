<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_items';

    const UPDATED_AT = null;

    protected $fillable = [
        'item_name',
        'category',
        'stock_level',
        'unit_price',
        'vendor',
        'status',
    ];

    public function orders()
    {
        return $this->hasMany(InventoryOrder::class, 'item_id');
    }
}
