<?php
namespace Tests\Feature;

use App\Models\{User, Medicine};
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Crypt, DB, File};
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use DatabaseMigrations;
    private string $directory;
    protected function setUp(): void {
        parent::setUp(); $this->directory=storage_path('framework/testing/backups-'.Str::uuid());
        config(['backups.directory'=>$this->directory]);
        $this->actingAs(User::factory()->create(['role'=>'Administrator']),'sanctum');
    }
    protected function tearDown(): void { File::deleteDirectory($this->directory); parent::tearDown(); }
    private function medicine(): Medicine {
        return Medicine::create(['medicine_name'=>'Backup test medicine','category'=>'Tablet','supplier'=>'Test','quantity'=>5,'expiry_date'=>today()->addYear()->toDateString(),'unit_price'=>5]);
    }
    public function test_encrypted_snapshot_round_trip_preserves_records_and_creates_safety_backup(): void {
        $m=$this->medicine(); $service=app(DatabaseBackupService::class); $backup=$service->create();
        $this->assertStringNotContainsString('Backup test medicine',File::get($service->path($backup['name'])));
        $this->assertTrue($service->inspect($backup['name'])['valid']);
        $m->update(['quantity'=>1]); $extra=$this->medicine();
        $result=$service->restore($backup['name']);
        $this->assertEquals(5,Medicine::find($m->id)->quantity); $this->assertNull(Medicine::find($extra->id));
        $this->assertCount(2,$service->listing()); $this->assertNotEquals($backup['name'],$result['safety_backup']);
        $this->assertTrue($service->inspect($result['safety_backup'])['valid']);
    }
    public function test_failed_restore_rolls_back_instead_of_leaving_partial_database(): void {
        $m=$this->medicine(); $service=app(DatabaseBackupService::class); $backup=$service->create(); $path=$service->path($backup['name']);
        $data=json_decode(gzdecode(base64_decode(Crypt::decryptString(File::get($path)))),true);
        $data['tables']['medicines'][]=$data['tables']['medicines'][0];
        File::put($path,Crypt::encryptString(base64_encode(gzencode(json_encode($data)))));
        $m->update(['quantity'=>3]);
        try { $service->restore($backup['name']); $this->fail('Duplicate IDs must fail restoration.'); } catch (\Illuminate\Database\QueryException $e) { }
        $this->assertEquals(3,Medicine::find($m->id)->quantity);
        $this->assertEquals(1,DB::select('PRAGMA foreign_keys')[0]->foreign_keys);
    }
    public function test_corruption_and_schema_mismatch_are_rejected_before_restore(): void {
        $service=app(DatabaseBackupService::class); $backup=$service->create(); $path=$service->path($backup['name']);
        $original=File::get($path); File::put($path,'corrupt');
        $this->postJson('/api/backups/verify',['name'=>$backup['name']])->assertUnprocessable();
        $data=json_decode(gzdecode(base64_decode(Crypt::decryptString($original))),true); unset($data['schema']['users']);
        File::put($path,Crypt::encryptString(base64_encode(gzencode(json_encode($data)))));
        $this->postJson('/api/backups/verify',['name'=>$backup['name']])->assertUnprocessable();
        $this->assertDatabaseCount('users',1);
    }
    public function test_backup_api_is_admin_only_and_paths_cannot_escape_directory(): void {
        $backup=$this->postJson('/api/backups')->assertCreated()->json();
        $this->getJson('/api/backups')->assertOk()->assertJsonCount(1,'backups');
        $this->get('/api/backups/download?name='.$backup['name'])->assertOk();
        $this->getJson('/api/backups/download?name=../../.env')->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role'=>'Pharmacy User','module_permissions'=>['settings']]),'sanctum');
        $this->getJson('/api/backups')->assertForbidden(); $this->postJson('/api/backups')->assertForbidden();
    }
    public function test_cli_verify_does_not_change_records_and_restore_requires_maintenance_confirmation(): void {
        $m=$this->medicine(); $backup=app(DatabaseBackupService::class)->create(); $m->update(['quantity'=>2]);
        $this->artisan('clinic:restore',['name'=>$backup['name'],'--verify'=>true])->assertSuccessful();
        $this->assertEquals(2,$m->fresh()->quantity);
        $this->artisan('clinic:restore',['name'=>$backup['name']])->assertFailed();
        $this->artisan('clinic:restore',['name'=>$backup['name'],'--confirm'=>'RESTORE'])->assertFailed();
        $this->assertEquals(2,$m->fresh()->quantity);
    }
    public function test_daily_backup_catches_up_once_and_does_not_duplicate_current_snapshot(): void {
        $service=app(DatabaseBackupService::class);
        $first=$service->createIfDue(); $this->assertNotNull($first);
        $this->assertNull($service->createIfDue());
        touch($service->path($first['name']),time()-172800); clearstatcache();
        $next=$service->createIfDue(); $this->assertNotNull($next);
        $this->assertCount(2,$service->listing());
    }
}
