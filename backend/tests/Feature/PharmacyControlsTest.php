<?php
namespace Tests\Feature;

use App\Models\{Medicine, InventoryMovement, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PharmacyControlsTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->actingAs(User::factory()->create(['role'=>'Administrator']),'sanctum'); }
    private function medicine(array $extra=[]): Medicine {
        return Medicine::create($extra+['medicine_name'=>'Counted medicine','category'=>'Tablet','supplier'=>'Supplier','batch_number'=>'B1',
            'quantity'=>10,'buying_price'=>2,'unit_price'=>5,'expiry_date'=>today()->addYear()->toDateString(),'reorder_level'=>2]);
    }
    private function sale(array $lines,array $extra=[]): array {
        return $this->postJson('/api/pharmacy/sales',$extra+['payment_method'=>'Cash','discount_type'=>'None','discount_value'=>0,'tax_percent'=>0,
            'medicines'=>$lines])->assertCreated()->json();
    }
    private function startCount(Medicine $m): array {
        return $this->postJson('/api/pharmacy/stocktakes',['medicine_ids'=>[$m->id]])->assertCreated()->json();
    }
    public function test_stocktake_requires_complete_reasoned_counts_and_admin_approval(): void {
        $m=$this->medicine(); $count=$this->startCount($m); $url='/api/pharmacy/stocktakes/'.$count['id'];
        $this->postJson($url.'/submit')->assertUnprocessable();
        $this->putJson($url,['items'=>[['id'=>$count['items'][0]['id'],'counted_quantity'=>7]]])->assertOk();
        $this->postJson($url.'/submit')->assertUnprocessable();
        $this->putJson($url,['items'=>[['id'=>$count['items'][0]['id'],'counted_quantity'=>7,'reason'=>'Damaged stock found during count']]])->assertOk();
        $this->assertEquals(10,$m->fresh()->quantity);
        $this->postJson($url.'/submit')->assertOk();
        $this->putJson($url,['items'=>[['id'=>$count['items'][0]['id'],'counted_quantity'=>8]]])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role'=>'Pharmacy User']),'sanctum');
        $this->postJson($url.'/approve')->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'Administrator']),'sanctum');
        $this->postJson($url.'/approve')->assertOk()->assertJsonPath('status','Approved');
        $this->assertEquals(7,$m->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements',['reference_type'=>'Stocktake','reference_id'=>$count['id'],'old_quantity'=>10,'new_quantity'=>7,'quantity'=>3]);
        $this->postJson($url.'/approve')->assertUnprocessable();
        $this->postJson($url.'/recount')->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements',1);
        $this->deleteJson('/api/medicines/'.$m->id)->assertUnprocessable();
    }
    public function test_stock_movement_after_count_requires_recount_even_if_quantity_is_unchanged(): void {
        $m=$this->medicine(); $count=$this->startCount($m); $url='/api/pharmacy/stocktakes/'.$count['id'];
        $this->putJson($url,['items'=>[['id'=>$count['items'][0]['id'],'counted_quantity'=>9,'reason'=>'Recount']]])->assertOk();
        $this->postJson($url.'/submit')->assertOk();
        InventoryMovement::create(['medicine_id'=>$m->id,'transaction_number'=>'TEST','movement_type'=>'Stock In','quantity'=>1]);
        $this->postJson($url.'/approve')->assertUnprocessable();
        $this->assertEquals(10,$m->fresh()->quantity);
        $this->postJson($url.'/recount')->assertOk()->assertJsonPath('status','Draft')->assertJsonPath('items.0.counted_quantity',null);
    }
    public function test_stocktake_approval_rolls_back_all_lines_on_conflict(): void {
        $a=$this->medicine(); $b=$this->medicine();
        $count=$this->postJson('/api/pharmacy/stocktakes',['medicine_ids'=>[$a->id,$b->id]])->assertCreated()->json();
        $url='/api/pharmacy/stocktakes/'.$count['id'];
        $this->putJson($url,['items'=>array_map(fn($i)=>['id'=>$i['id'],'counted_quantity'=>8,'reason'=>'Missing stock'],$count['items'])])->assertOk();
        $this->postJson($url.'/submit')->assertOk(); $b->update(['quantity'=>9]);
        $this->postJson($url.'/approve')->assertUnprocessable();
        $this->assertEquals(10,$a->fresh()->quantity); $this->assertDatabaseCount('inventory_movements',0);
        $this->assertDatabaseHas('pharmacy_stocktakes',['id'=>$count['id'],'status'=>'Submitted']);
    }
    public function test_count_lines_from_other_stocktakes_and_negative_counts_are_rejected(): void {
        $m=$this->medicine(); $a=$this->startCount($m); $b=$this->startCount($m);
        $this->putJson('/api/pharmacy/stocktakes/'.$a['id'],['items'=>[['id'=>$b['items'][0]['id'],'counted_quantity'=>1]]])->assertUnprocessable();
        $this->putJson('/api/pharmacy/stocktakes/'.$a['id'],['items'=>[['id'=>$a['items'][0]['id'],'counted_quantity'=>-1]]])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role'=>'Receptionist']),'sanctum');
        $this->getJson('/api/pharmacy/stocktakes')->assertForbidden();
        $this->getJson('/api/pharmacy/profit')->assertForbidden();
    }
    public function test_profit_uses_sale_cost_snapshot_and_excludes_tax_discounts_and_returns(): void {
        $m=$this->medicine();
        $sale=$this->sale([['medicine_id'=>$m->id,'quantity'=>2]],['discount_type'=>'Fixed','discount_value'=>2,'tax_percent'=>10]);
        $m->update(['buying_price'=>99]);
        $this->getJson('/api/pharmacy/profit')->assertOk()->assertJsonPath('net_revenue',8)->assertJsonPath('known_cost',4)->assertJsonPath('gross_profit',4)->assertJsonPath('margin_percent',50);
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/return',['return_reason'=>'Test'])->assertOk();
        $this->getJson('/api/pharmacy/profit')->assertOk()->assertJsonPath('net_revenue',0)->assertJsonPath('gross_profit',0)->assertJsonPath('returned_sales_excluded',1);
    }
    public function test_profit_handles_fefo_batch_costs_and_penny_discount_allocation(): void {
        $root=$this->medicine(['quantity'=>1,'unit_price'=>1,'buying_price'=>0.20]);
        $this->medicine(['batch_group_id'=>$root->id,'quantity'=>1,'unit_price'=>1,'buying_price'=>0.40,'expiry_date'=>today()->addMonth()->toDateString()]);
        $this->sale([['medicine_id'=>$root->id,'quantity'=>2]],['discount_type'=>'Fixed','discount_value'=>0.01]);
        $data=$this->getJson('/api/pharmacy/profit')->assertOk()->assertJsonPath('net_revenue',1.99)->assertJsonPath('known_cost',0.6)->assertJsonPath('gross_profit',1.39)->json();
        $this->assertEquals(1.99,array_sum(array_column($data['items'],'net_revenue')));
        $this->assertEquals(1.39,round(array_sum(array_column($data['items'],'gross_profit')),2));
    }
    public function test_historical_missing_cost_is_explicit_and_dates_are_respected(): void {
        $m=$this->medicine(); $sale=$this->sale([['medicine_id'=>$m->id,'quantity'=>1]]);
        DB::table('pharmacy_sale_medicines')->where('sale_id',$sale['id'])->update(['unit_cost'=>null]);
        $this->getJson('/api/pharmacy/profit')->assertOk()->assertJsonPath('gross_profit',null)->assertJsonPath('missing_cost_lines',1)->assertJsonPath('revenue_without_cost',5);
        $this->getJson('/api/pharmacy/profit?from=2020-01-01&to=2020-01-31')->assertOk()->assertJsonPath('sales',0);
        $this->getJson('/api/pharmacy/profit?from=2026-01-02&to=2026-01-01')->assertUnprocessable();
    }
}
