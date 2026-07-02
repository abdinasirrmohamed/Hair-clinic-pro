<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';

    const UPDATED_AT = null;

    protected $fillable = [
        'company_name',
        'contact_person',
        'phone',
        'email',
        'address',
    ];

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'supplier_id');
    }
}
