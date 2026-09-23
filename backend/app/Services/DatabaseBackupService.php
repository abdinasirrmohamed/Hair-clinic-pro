<?php

namespace App\Services;

use Illuminate\Support\Facades\{Cache, Crypt, DB, File, Schema};
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseBackupService
{
    public function path(string $name): string
    {
        if (!preg_match('/^backup-\d{8}-\d{6}-[a-f0-9-]{36}\.hcp$/D',$name)) throw new RuntimeException('Invalid backup filename.');
        return config('backups.directory').DIRECTORY_SEPARATOR.$name;
    }
    public function listing(): array
    {
        File::ensureDirectoryExists(config('backups.directory'));
        $rows=[];
        foreach (File::files(config('backups.directory')) as $file) {
            if (!preg_match('/^backup-\d{8}-\d{6}-[a-f0-9-]{36}\.hcp$/D',$file->getFilename())) continue;
            $rows[]=['name'=>$file->getFilename(),'bytes'=>$file->getSize(),'created_at'=>date(DATE_ATOM,$file->getMTime())];
        }
        usort($rows,fn($a,$b)=>strcmp($b['name'],$a['name']));
        return $rows;
    }
    private function schema(): array
    {
        $schema=[];
        foreach (Schema::getTables(DB::getDriverName()==='mysql' ? DB::getDatabaseName() : null) as $table) {
            $name=$table['name'];
            if (str_starts_with($name,'sqlite_')) continue;
            $columns=Schema::getColumns($name);
            $schema[$name]=array_map(fn($c)=>['name'=>$c['name'],'type'=>$c['type'],'nullable'=>$c['nullable']],$columns);
        }
        ksort($schema); return $schema;
    }
    public function create(): array
    {
        return Cache::lock('clinic-database-backup',3600)->block(1,fn()=>$this->snapshot());
    }
    public function createIfDue(): ?array
    {
        return Cache::lock('clinic-database-backup',3600)->block(1,function () {
            $now=now(config('backups.timezone')); $cutoff=$now->copy()->startOfDay()->addHours(2);
            if ($now->lessThan($cutoff)) $cutoff->subDay();
            $latest=$this->listing()[0]??null;
            if ($latest && \Illuminate\Support\Carbon::parse($latest['created_at'])->greaterThanOrEqualTo($cutoff)) return null;
            return $this->snapshot();
        });
    }
    private function snapshot(): array
    {
        File::ensureDirectoryExists(config('backups.directory'));
        $schema=$this->schema();
        if (DB::getDriverName()==='mysql') {
            foreach (DB::select('SHOW TABLE STATUS') as $table) {
                if (isset($schema[$table->Name]) && strcasecmp($table->Engine??'','InnoDB')!==0) throw new RuntimeException('Backups require transactional InnoDB tables.');
            }
        }
        $tables=DB::transaction(function () use ($schema) {
            $tables=[]; $bytes=0;
            foreach ($schema as $name=>$columns) {
                $tables[$name]=[];
                foreach (DB::table($name)->cursor() as $row) {
                    $record=(array)$row; $bytes+=strlen(json_encode($record,JSON_THROW_ON_ERROR));
                    if ($bytes>config('backups.max_bytes')) throw new RuntimeException('Database exceeds the 128 MB snapshot limit. Use a database-native backup.');
                    $tables[$name][]=$record;
                }
            }
            return $tables;
        });
        $payload=['version'=>1,'driver'=>DB::getDriverName(),'created_at'=>now()->toIso8601String(),'schema'=>$schema,'tables'=>$tables];
        $json=json_encode($payload,JSON_THROW_ON_ERROR);
        if (strlen($json)>config('backups.max_bytes')) throw new RuntimeException('Snapshot exceeds the backup size limit.');
        $encrypted=Crypt::encryptString(base64_encode(gzencode($json,6)));
        $name='backup-'.now()->format('Ymd-His').'-'.Str::uuid().'.hcp'; $path=$this->path($name);
        try {
            File::put($path.'.tmp',$encrypted,true);
            if (!rename($path.'.tmp',$path)) throw new RuntimeException('Could not finalize backup.');
        } finally { if (File::exists($path.'.tmp')) File::delete($path.'.tmp'); }
        return ['name'=>$name,'bytes'=>strlen($encrypted),'created_at'=>$payload['created_at'],'tables'=>count($tables),'rows'=>array_sum(array_map('count',$tables))];
    }
    private function read(string $name): array
    {
        $path=$this->path($name);
        if (!File::isFile($path)) throw new RuntimeException('Backup not found.');
        if (File::size($path)>config('backups.max_bytes')*2) throw new RuntimeException('Backup exceeds the size limit.');
        try {
            $compressed=base64_decode(Crypt::decryptString(File::get($path)),true);
            $json=$compressed===false?false:@gzdecode($compressed,config('backups.max_bytes'));
            if ($json===false) throw new RuntimeException('Invalid compressed data.');
            $data=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) { throw new RuntimeException('Backup is damaged or was encrypted with a different APP_KEY.',0,$e); }
        if (($data['version']??null)!==1 || ($data['driver']??null)!==DB::getDriverName() || ($data['schema']??null)!==$this->schema()) {
            throw new RuntimeException('Backup schema or database driver does not match this application. Restore using the matching application version.');
        }
        if (array_keys($data['tables']??[])!==array_keys($data['schema'])) throw new RuntimeException('Backup table manifest is incomplete.');
        foreach ($data['tables'] as $table=>$rows) {
            $columns=array_column($data['schema'][$table],'name'); sort($columns);
            if (!is_array($rows)) throw new RuntimeException('Invalid backup rows.');
            foreach ($rows as $row) {
                if (!is_array($row)) throw new RuntimeException('Invalid backup row.');
                $keys=array_keys($row); sort($keys);
                if ($keys!==$columns) throw new RuntimeException('Backup row columns do not match the schema.');
            }
        }
        return $data;
    }
    public function inspect(string $name): array
    {
        $data=$this->read($name);
        return ['name'=>$name,'valid'=>true,'created_at'=>$data['created_at'],'tables'=>count($data['tables']),
            'rows'=>array_sum(array_map('count',$data['tables'])),'scope'=>'Database records only; uploaded files, application code and .env are not included.'];
    }
    // Called only by the maintenance-mode CLI command. No restore endpoint is exposed to web requests.
    public function restore(string $name): array
    {
        return Cache::lock('clinic-database-backup',3600)->block(1,function () use ($name) {
            $data=$this->read($name);
            $safety=$this->snapshot();
            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($data) {
                    foreach (array_reverse(array_keys($data['tables'])) as $table) DB::table($table)->delete();
                    foreach ($data['tables'] as $table=>$rows) {
                        // Single-row inserts support SQLite parameter limits and retain exact IDs.
                        foreach ($rows as $row) DB::table($table)->insert($row);
                    }
                    if (DB::getDriverName()==='sqlite' && DB::select('PRAGMA foreign_key_check')) throw new RuntimeException('Restored data failed foreign-key validation.');
                });
            } finally { Schema::enableForeignKeyConstraints(); }
            return ['restored'=>$name,'safety_backup'=>$safety['name']];
        });
    }
}
