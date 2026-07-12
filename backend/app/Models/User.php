<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    const UPDATED_AT = null;

    protected $fillable = [
        'username',
        'password',
        'full_name',
        'role',
        'status',
        'profile_photo_path',
        'module_permissions',
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected $casts = [
        'module_permissions' => 'array',
    ];

    public function effectiveModulePermissions(): array
    {
        return is_array($this->module_permissions)
            ? $this->module_permissions
            : (config('roles.module_permissions')[$this->role] ?? []);
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'generated_by');
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'issued_by');
    }

    public function pharmacyInvoices()
    {
        return $this->hasMany(PharmacyInvoice::class, 'created_by');
    }

    public function pharmacySales()
    {
        return $this->hasMany(PharmacySale::class, 'created_by');
    }
}
