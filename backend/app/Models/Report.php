<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $table = 'reports';

    const UPDATED_AT = null;

    protected $fillable = [
        'report_type',
        'period_type',
        'title',
        'generated_by',
        'date_from',
        'date_to',
        'summary',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
