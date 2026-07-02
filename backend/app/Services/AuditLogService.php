<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function log(string $action, string $module, $recordId = null): void
    {
        $user = Auth::user();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->full_name ?? 'System',
            'user_role' => $user?->role ?? 'System',
            'action' => $action,
            'module_name' => $module,
            'record_id' => $recordId ? (string) $recordId : null,
            'ip_address' => Request::ip(),
        ]);
    }
}
