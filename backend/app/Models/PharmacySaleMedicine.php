<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacySaleMedicine extends Model
{
    use HasFactory;

    protected $table = 'pharmacy_sale_medicines';

    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'medicine_id',
        'prescription_medicine_id',
        'quantity',
        'frequency',
        'instructions',
        'unit_price',
        'subtotal',
    ];

    public function sale()
    {
        return $this->belongsTo(PharmacySale::class, 'sale_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }

    public function prescriptionMedicine()
    {
        return $this->belongsTo(PrescriptionMedicine::class, 'prescription_medicine_id');
    }
}
