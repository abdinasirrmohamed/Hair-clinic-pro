<?php

namespace Tests\Feature;

use App\Models\{Medicine, Supplier, User, PharmacyCustomer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PharmacyWorkflowTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->actingAs(User::factory()->create(['role'=>'Pharmacy User']), 'sanctum'); }
    private function medicine(array $extra=[]): Medicine {
        return Medicine::create($extra+['medicine_name'=>'Product 10mg','category'=>'Tablet','supplier'=>'Supplier',
            'quantity'=>10,'unit_price'=>5,'buying_price'=>2,'batch_number'=>'B1','expiry_date'=>today()->addYear()->toDateString(),'reorder_level'=>3]);
    }
    private function sale(Medicine $m, int $qty=2, array $extra=[]): array {
        return $extra+['payment_method'=>'Cash','discount_type'=>'None','discount_value'=>0,'tax_percent'=>0,'medicines'=>[['medicine_id'=>$m->id,'quantity'=>$qty]]];
    }
    private function purchase(Medicine $m, Supplier $s, array $extra=[]): array {
        return $extra+['supplier_id'=>$s->id,'invoice_number'=>'INV-1','received_date'=>today()->toDateString(),
            'items'=>[['medicine_id'=>$m->id,'quantity'=>4,'unit_cost'=>2,'batch_number'=>$m->batch_number,'expiry_date'=>$m->expiry_date]]];
    }
    public function test_alert_windows_and_expired_stock_are_separate(): void {
        $this->medicine(['expiry_date'=>today()->addDays(20)->toDateString(),'quantity'=>2]);
        $this->medicine(['expiry_date'=>today()->addDays(50)->toDateString()]);
        $this->medicine(['expiry_date'=>today()->subDay()->toDateString()]);
        $this->medicine(['expiry_date'=>today()->addDays(5)->toDateString(),'quantity'=>0]);
        $this->getJson('/api/pharmacy/alerts?days=30')->assertOk()->assertJsonCount(1,'expiring')->assertJsonCount(1,'expired')->assertJsonCount(2,'low_stock');
        $this->getJson('/api/pharmacy/alerts?days=60')->assertOk()->assertJsonCount(2,'expiring');
        $this->getJson('/api/pharmacy/alerts?days=7')->assertUnprocessable();
    }
    public function test_fefo_splits_sale_and_return_restores_original_batches(): void {
        $root=$this->medicine(['quantity'=>5]);
        $batch=$this->postJson('/api/pharmacy/medicines/'.$root->id.'/batches',['batch_number'=>'B2','expiry_date'=>today()->addMonth()->toDateString(),'buying_price'=>3])->assertCreated()->assertJsonPath('quantity',0)->json();
        Medicine::find($batch['id'])->update(['quantity'=>2]);
        $this->postJson('/api/pharmacy/medicines/'.$root->id.'/batches',['batch_number'=>'B2','expiry_date'=>today()->addMonth()->toDateString(),'buying_price'=>3])->assertUnprocessable();
        $sale=$this->postJson('/api/pharmacy/sales',$this->sale($root,4))->assertCreated()->assertJsonCount(2,'medicines')->json();
        $this->assertEquals(0,Medicine::find($batch['id'])->quantity); $this->assertEquals(3,$root->fresh()->quantity);
        $this->assertDatabaseHas('pharmacy_sale_medicines',['sale_id'=>$sale['id'],'medicine_id'=>$batch['id'],'quantity'=>2]);
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/return',['return_reason'=>'Test'])->assertOk();
        $this->assertEquals(2,Medicine::find($batch['id'])->quantity); $this->assertEquals(5,$root->fresh()->quantity);
    }
    public function test_fefo_never_uses_expired_or_unrelated_stock_and_rolls_back_oversell(): void {
        $root=$this->medicine(['quantity'=>1]);
        $this->medicine(['batch_group_id'=>$root->id,'quantity'=>10,'expiry_date'=>today()->subDay()->toDateString()]);
        $this->medicine(['quantity'=>100]);
        $this->postJson('/api/pharmacy/sales',$this->sale($root,2))->assertUnprocessable();
        $this->assertEquals(1,$root->fresh()->quantity); $this->assertDatabaseCount('pharmacy_sales',0);
    }
    public function test_prescription_uses_linked_batches_and_returns_prescribed_quantity(): void {
        $root=$this->medicine(['quantity'=>1]);
        $early=$this->medicine(['batch_group_id'=>$root->id,'batch_number'=>'EARLY','quantity'=>2,'expiry_date'=>today()->addMonth()->toDateString()]);
        $customer=PharmacyCustomer::create(['full_name'=>'RX customer']);
        $rx=$this->postJson('/api/pharmacy/prescriptions',['customer_id'=>$customer->id,'prescriber_name'=>'Doctor','prescription_date'=>today()->toDateString(),
            'medicines'=>[['medicine_id'=>$root->id,'quantity'=>3,'frequency'=>'As prescribed','instructions'=>'Original instructions']]])->assertCreated()->json();
        $sale=$this->postJson('/api/pharmacy/sales',$this->sale($root,3,['customer_id'=>$customer->id,'prescription_id'=>$rx['id'],
            'medicines'=>[['medicine_id'=>$root->id,'quantity'=>3,'prescription_medicine_id'=>$rx['medicines'][0]['id']]]]))->assertCreated()->assertJsonCount(2,'medicines')->json();
        $this->assertEquals(0,$early->fresh()->quantity);
        $this->assertDatabaseHas('prescription_medicines',['id'=>$rx['medicines'][0]['id'],'dispensed_quantity'=>3]);
        $this->assertDatabaseHas('pharmacy_sale_medicines',['sale_id'=>$sale['id'],'medicine_id'=>$early->id,'instructions'=>'Original instructions']);
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/return',['return_reason'=>'Returned'])->assertOk();
        $this->assertDatabaseHas('prescription_medicines',['id'=>$rx['medicines'][0]['id'],'dispensed_quantity'=>0]);
        $this->postJson('/api/pharmacy/dispense',['prescription_id'=>$rx['id']])->assertOk();
        $this->assertEquals(0,$early->fresh()->quantity); $this->assertEquals(0,$root->fresh()->quantity);
    }
    public function test_linked_batches_share_retail_price_and_do_not_double_allocate(): void {
        $root=$this->medicine(['quantity'=>2]);
        $early=$this->medicine(['batch_group_id'=>$root->id,'quantity'=>2,'expiry_date'=>today()->addMonth()->toDateString()]);
        $this->putJson('/api/medicines/'.$early->id,['unit_price'=>6])->assertOk();
        $this->assertEquals(6,$root->fresh()->unit_price);
        $payload=$this->sale($root,2,['medicines'=>[['medicine_id'=>$root->id,'quantity'=>3],['medicine_id'=>$early->id,'quantity'=>2]]]);
        $this->postJson('/api/pharmacy/sales',$payload)->assertUnprocessable();
        $this->assertEquals(2,$early->fresh()->quantity); $this->assertEquals(2,$root->fresh()->quantity);
        $payload['medicines'][0]['quantity']=2;
        $this->postJson('/api/pharmacy/sales',$payload)->assertCreated();
        $this->assertEquals(0,$early->fresh()->quantity); $this->assertEquals(0,$root->fresh()->quantity);
    }
    public function test_purchase_order_partial_receive_overreceive_and_cancel(): void {
        $m=$this->medicine(); $s=Supplier::create(['company_name'=>'Supplier','phone'=>'1']);
        $order=$this->postJson('/api/pharmacy/orders',['supplier_id'=>$s->id,'items'=>[['medicine_id'=>$m->id,'quantity'=>6,'unit_cost'=>2]]])->assertCreated()->json();
        $this->assertEquals(10,$m->fresh()->quantity);
        $payload=$this->purchase($m,$s,['order_id'=>$order['id']]);
        $this->postJson('/api/pharmacy/purchases',$payload)->assertCreated();
        $this->assertDatabaseHas('pharmacy_orders',['id'=>$order['id'],'status'=>'Partially Received']);
        $this->postJson('/api/pharmacy/purchases',array_replace($payload,['invoice_number'=>'INV-2']))->assertUnprocessable();
        $this->assertDatabaseCount('pharmacy_purchases',1); $this->assertEquals(14,$m->fresh()->quantity);
        $payload['items'][0]['quantity']=2; $payload['invoice_number']='INV-3';
        $this->postJson('/api/pharmacy/purchases',$payload)->assertCreated();
        $this->assertDatabaseHas('pharmacy_orders',['id'=>$order['id'],'status'=>'Received']);
        $this->postJson('/api/pharmacy/orders/'.$order['id'].'/cancel')->assertUnprocessable();
        $this->deleteJson('/api/suppliers/'.$s->id)->assertUnprocessable();
        $this->getJson('/api/pharmacy/orders')->assertOk()->assertJsonPath('data.0.items.0.received_quantity',6);
    }
    public function test_cancelled_order_cannot_receive_and_wrong_supplier_rolls_back(): void {
        $m=$this->medicine(); $s=Supplier::create(['company_name'=>'Supplier','phone'=>'1']);
        $other=Supplier::create(['company_name'=>'Other','phone'=>'2']);
        $order=$this->postJson('/api/pharmacy/orders',['supplier_id'=>$s->id,'items'=>[['medicine_id'=>$m->id,'quantity'=>6,'unit_cost'=>2]]])->assertCreated()->json();
        $this->postJson('/api/pharmacy/purchases',$this->purchase($m,$other,['order_id'=>$order['id']]))->assertUnprocessable();
        $this->postJson('/api/pharmacy/orders/'.$order['id'].'/cancel')->assertOk();
        $this->postJson('/api/pharmacy/purchases',$this->purchase($m,$s,['order_id'=>$order['id']]))->assertUnprocessable();
        $this->assertEquals(10,$m->fresh()->quantity);
    }
    public function test_supplier_balances_partial_payments_and_historical_confirmation(): void {
        $m=$this->medicine(); $s=Supplier::create(['company_name'=>'Supplier','phone'=>'1']);
        $p=$this->postJson('/api/pharmacy/purchases',$this->purchase($m,$s,['amount_paid'=>3]))->assertCreated()->json();
        $this->getJson('/api/pharmacy/payables')->assertOk()->assertJsonPath('data.0.balance',5);
        $this->postJson('/api/pharmacy/payables/'.$p['id'].'/payments',['amount'=>6,'payment_method'=>'Cash'])->assertUnprocessable();
        $this->postJson('/api/pharmacy/payables/'.$p['id'].'/payments',['amount'=>5,'payment_method'=>'Bank Transfer'])->assertOk();
        $this->assertDatabaseCount('pharmacy_supplier_payments',2);
        $this->getJson('/api/pharmacy/payables')->assertOk()->assertJsonPath('data.0.balance',0);
        $this->postJson('/api/pharmacy/payables/'.$p['id'].'/reconcile',['amount_paid'=>0])->assertUnprocessable();
        DB::table('pharmacy_purchases')->where('id',$p['id'])->update(['amount_paid'=>null]);
        DB::table('pharmacy_supplier_payments')->where('purchase_id',$p['id'])->delete();
        $this->postJson('/api/pharmacy/payables/'.$p['id'].'/payments',['amount'=>1,'payment_method'=>'Cash'])->assertUnprocessable();
        $this->postJson('/api/pharmacy/payables/'.$p['id'].'/reconcile',['amount_paid'=>2])->assertOk();
        $this->getJson('/api/pharmacy/payables')->assertOk()->assertJsonPath('data.0.balance',6);
    }
    public function test_cash_register_tracks_collections_and_cross_session_refunds(): void {
        $m=$this->medicine(['quantity'=>30]);
        $r=$this->postJson('/api/pharmacy/registers',['opening_cash'=>20])->assertCreated()->json();
        $this->postJson('/api/pharmacy/registers',['opening_cash'=>0])->assertUnprocessable();
        $sale=$this->postJson('/api/pharmacy/sales',$this->sale($m))->assertCreated()->json();
        $this->postJson('/api/pharmacy/sales',$this->sale($m,2,['payment_method'=>'Card']))->assertCreated();
        $this->getJson('/api/pharmacy/registers')->assertOk()->assertJsonPath('data.0.expected_cash',30);
        $this->postJson('/api/pharmacy/registers/'.$r['id'].'/close',['counted_cash'=>29])->assertUnprocessable();
        $this->postJson('/api/pharmacy/registers/'.$r['id'].'/close',['counted_cash'=>30])->assertOk();
        $this->postJson('/api/pharmacy/registers/'.$r['id'].'/close',['counted_cash'=>30])->assertUnprocessable();
        $r2=$this->postJson('/api/pharmacy/registers',['opening_cash'=>20])->assertCreated()->json();
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/return',['return_reason'=>'Test'])->assertOk();
        $this->getJson('/api/pharmacy/registers')->assertOk()->assertJsonPath('data.0.expected_cash',10);
        $this->postJson('/api/pharmacy/registers/'.$r2['id'].'/close',['counted_cash'=>9,'notes'=>'One dollar shortage'])->assertOk();
        $this->assertDatabaseHas('pharmacy_registers',['id'=>$r['id'],'expected_cash'=>30]);
        $this->assertDatabaseHas('pharmacy_registers',['id'=>$r2['id'],'difference'=>-1]);
    }
    public function test_balance_collection_uses_current_cashier_register_and_access_is_scoped(): void {
        $m=$this->medicine(); $customer=PharmacyCustomer::create(['full_name'=>'Customer']);
        $sale=$this->postJson('/api/pharmacy/sales',$this->sale($m,2,['customer_id'=>$customer->id,'amount_paid'=>4]))->assertCreated()->json();
        $r=$this->postJson('/api/pharmacy/registers',['opening_cash'=>0])->assertCreated()->json();
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/payments',['amount'=>6,'payment_method'=>'Cash'])->assertOk();
        $this->getJson('/api/pharmacy/registers')->assertOk()->assertJsonPath('data.0.expected_cash',6);
        $this->actingAs(User::factory()->create(['role'=>'Pharmacy User']),'sanctum');
        $this->postJson('/api/pharmacy/registers/'.$r['id'].'/close',['counted_cash'=>6])->assertNotFound();
        $this->actingAs(User::factory()->create(['role'=>'Receptionist']),'sanctum');
        foreach (['alerts','orders','payables','registers'] as $path) $this->getJson('/api/pharmacy/'.$path)->assertForbidden();
    }
}
