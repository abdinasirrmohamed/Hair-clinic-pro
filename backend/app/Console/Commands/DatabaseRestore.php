<?php
namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class DatabaseRestore extends Command
{
    protected $signature='clinic:restore {name} {--verify : Validate without changing data} {--confirm= : Must equal RESTORE to replace database records}';
    protected $description='Validate or restore a compatible encrypted database backup in maintenance mode';
    public function handle(DatabaseBackupService $service): int
    {
        try {
            if ($this->option('verify')) { $this->info(json_encode($service->inspect($this->argument('name')),JSON_PRETTY_PRINT)); return self::SUCCESS; }
            if ($this->option('confirm')!=='RESTORE' || !app()->isDownForMaintenance()) {
                $this->error('First stop background workers, run artisan down, and pass --confirm=RESTORE. Use --verify for a read-only validation.'); return self::FAILURE;
            }
            $result=$service->restore($this->argument('name'));
            $this->info('Restored. Pre-restore safety backup: '.$result['safety_backup']);
            $this->info('Verify the application, then run artisan up.'); return self::SUCCESS;
        } catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
