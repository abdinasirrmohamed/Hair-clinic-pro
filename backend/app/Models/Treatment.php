<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    use HasFactory;

    protected $table = 'treatments';

    const UPDATED_AT = null;

    protected $fillable = [
        'patient_id',
        'treatment_name',
        'treatment_date',
        'treatment_stage',
        'progress',
        'cost',
        'grafts_planned',
        'grafts_extracted',
        'grafts_implanted',
        'donor_area_status',
        'recipient_area_status',
        'pre_op_photo',
        'post_op_photo',
        'notes',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function followups()
    {
        return $this->hasMany(Followup::class, 'treatment_id');
    }
}
