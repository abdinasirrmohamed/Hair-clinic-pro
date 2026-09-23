<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PharmacyCustomer extends Model
{
    protected $fillable = ['full_name', 'phone', 'email', 'address', 'notes'];

    public function sales()
    {
        return $this->hasMany(PharmacySale::class, 'customer_id');
    }
}
