<?php
namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class DatabaseBackup extends Command
{
    protected $signature='clinic:backup {--if-due : Only create a snapshot if the latest daily backup is due}';
    protected $description='Create an encrypted database snapshot';
    public function handle(DatabaseBackupService $service): int
    {
        try { $result=$this->option('if-due') ? $service->createIfDue() : $service->create(); $this->info($result['name'] ?? 'Daily backup already exists.'); return self::SUCCESS; }
        catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
