<?php
namespace App\Http\Controllers;

use App\Services\{DatabaseBackupService, AuditLogService};
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index(DatabaseBackupService $service)
    {
        return ['backups'=>$service->listing(),'schedule'=>'Daily at 02:00 Africa/Mogadishu; run the Laravel scheduler.',
            'scheduler_last_seen'=>\Illuminate\Support\Facades\Cache::store('file')->get('backup-scheduler-last-seen'),
            'scope'=>'Encrypted database records only. Keep uploaded files and APP_KEY separately.'];
    }
    public function store(DatabaseBackupService $service)
    {
        try { $backup=$service->create(); }
        catch (\Throwable $e) { return response()->json(['message'=>$e->getMessage()],422); }
        AuditLogService::log('Created encrypted database backup','Settings',null);
        return response()->json($backup,201);
    }
    public function verify(Request $request,DatabaseBackupService $service)
    {
        $v=$request->validate(['name'=>'required|string|max:100']);
        try { return $service->inspect($v['name']); }
        catch (\Throwable $e) { return response()->json(['message'=>$e->getMessage()],422); }
    }
    public function download(Request $request,DatabaseBackupService $service)
    {
        $v=$request->validate(['name'=>'required|string|max:100']);
        try { $path=$service->path($v['name']); }
        catch (\Throwable $e) { abort(422,'Invalid backup filename.'); }
        abort_unless(is_file($path),404);
        return response()->download($path,basename($path),['Cache-Control'=>'no-store']);
    }
}
