<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionMedicine extends Model
{
    use HasFactory;

    protected $table = 'prescription_medicines';

    public $timestamps = false;

    protected $fillable = [
        'prescription_id',
        'medicine_id',
        'quantity',
        'frequency',
        'dispensed_quantity',
        'instructions',
    ];

    protected $casts = ['quantity' => 'integer', 'dispensed_quantity' => 'integer'];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class, 'prescription_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }
}
