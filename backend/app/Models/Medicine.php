<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    protected $table = 'medicines';

    const UPDATED_AT = null;

    protected $fillable = [
        'medicine_name',
        'generic_name',
        'category',
        'batch_number',
        'barcode',
        'quantity',
        'unit_price',
        'supplier_id',
        'expiry_date',
        'manufacturing_date',
        'reorder_level',
        'supplier',
        'description',
    ];

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'medicine_id');
    }

    public function prescriptionMedicines()
    {
        return $this->hasMany(PrescriptionMedicine::class, 'medicine_id');
    }

    public function pharmacySaleMedicines()
    {
        return $this->hasMany(PharmacySaleMedicine::class, 'medicine_id');
    }

    public function pharmacyInvoiceItems()
    {
        return $this->hasMany(PharmacyInvoiceItem::class, 'medicine_id');
    }
}
