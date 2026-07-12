<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_name',
        'category',
        'price',
        'sample_type',
        'status',
        'description',
    ];

    public function requests()
    {
        return $this->hasMany(LabRequest::class, 'lab_test_id');
    }
}
