<?php

namespace Tests\Feature;

use App\Models\{Medicine, PharmacyCustomer, Prescription, Supplier, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PharmacyOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'Pharmacy User']), 'sanctum');
    }

    private function medicine(array $attributes = []): Medicine
    {
        return Medicine::create($attributes + ['medicine_name' => 'Test medicine', 'category' => 'Tablet',
            'supplier' => 'Test supplier', 'quantity' => 10, 'buying_price' => 2, 'unit_price' => 5,
            'batch_number' => 'B1', 'expiry_date' => today()->addYear()->toDateString(), 'reorder_level' => 3]);
    }

    private function sale(Medicine $medicine, array $attributes = []): array
    {
        return $attributes + ['payment_method' => 'Cash', 'discount_type' => 'None', 'discount_value' => 0,
            'tax_percent' => 0, 'medicines' => [['medicine_id' => $medicine->id, 'quantity' => 2]]];
    }

    public function test_receiving_preserves_selling_price_and_duplicate_invoices_do_not_add_stock(): void
    {
        $medicine = $this->medicine();
        $supplier = Supplier::create(['company_name' => 'Wholesaler', 'phone' => '123']);
        $data = ['supplier_id' => $supplier->id, 'invoice_number' => 'INV-1', 'received_date' => today()->toDateString(),
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 5, 'unit_cost' => 3, 'batch_number' => 'B1', 'expiry_date' => $medicine->expiry_date]]];
        $this->postJson('/api/pharmacy/purchases', $data)->assertCreated();
        $this->assertEquals(15, $medicine->fresh()->quantity);
        $this->assertEquals(5, $medicine->fresh()->unit_price);
        $this->assertEquals(3, $medicine->fresh()->buying_price);
        $this->assertDatabaseHas('inventory_movements', ['old_quantity' => 10, 'new_quantity' => 15, 'supplier_id' => $supplier->id]);
        $this->postJson('/api/pharmacy/purchases', $data)->assertUnprocessable();
        $this->assertEquals(15, $medicine->fresh()->quantity);
        $this->getJson('/api/pharmacy/purchases')->assertOk()->assertJsonPath('data.0.items.0.quantity', 5);
    }

    public function test_mixed_batches_roll_back_the_entire_purchase(): void
    {
        $medicine = $this->medicine();
        $supplier = Supplier::create(['company_name' => 'Wholesaler', 'phone' => '123']);
        $this->postJson('/api/pharmacy/purchases', ['supplier_id' => $supplier->id, 'invoice_number' => 'INV-2',
            'received_date' => today()->toDateString(), 'items' => [['medicine_id' => $medicine->id,
                'quantity' => 5, 'unit_cost' => 3, 'batch_number' => 'B2', 'expiry_date' => $medicine->expiry_date]],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('pharmacy_purchases', 0);
        $this->assertEquals(10, $medicine->fresh()->quantity);
    }

    public function test_customer_sale_balance_and_payment_ledger(): void
    {
        $customer = $this->postJson('/api/pharmacy/customers', ['full_name' => 'Customer One', 'phone' => '123'])->assertCreated()->json();
        $sale = $this->postJson('/api/pharmacy/sales', $this->sale($this->medicine(), ['customer_id' => $customer['id'], 'amount_paid' => 4]))
            ->assertCreated()->assertJsonPath('remaining_balance', '6.00')->json();
        $this->getJson('/api/pharmacy/customers/'.$customer['id'])->assertOk()->assertJsonCount(1, 'sales');
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/payments', ['amount' => 7, 'payment_method' => 'Cash'])->assertUnprocessable();
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/payments', ['amount' => 6, 'payment_method' => 'Cash'])
            ->assertOk()->assertJsonPath('remaining_balance', '0.00')->assertJsonPath('payment_status', 'Full Paid');
        $this->assertDatabaseCount('pharmacy_sale_payments', 2);
        $this->deleteJson('/api/pharmacy/customers/'.$customer['id'])->assertUnprocessable();
    }

    public function test_expired_stock_cannot_be_sold_and_stock_is_unchanged(): void
    {
        $medicine = $this->medicine(['expiry_date' => today()->subDay()->toDateString()]);
        $this->postJson('/api/pharmacy/sales', $this->sale($medicine))->assertUnprocessable();
        $this->assertEquals(10, $medicine->fresh()->quantity);
        $this->assertDatabaseCount('pharmacy_sales', 0);
    }

    public function test_external_prescription_partial_dispensing_and_return(): void
    {
        $customer = PharmacyCustomer::create(['full_name' => 'Prescription Customer']);
        $other = PharmacyCustomer::create(['full_name' => 'Other Customer']);
        $medicine = $this->medicine();
        $rx = $this->postJson('/api/pharmacy/prescriptions', [
            'customer_id' => $customer->id, 'prescriber_name' => 'External Doctor', 'prescription_date' => today()->toDateString(),
            'medicines' => [['medicine_id' => $medicine->id, 'quantity' => 4, 'frequency' => 'As prescribed', 'instructions' => 'Test instructions']],
        ])->assertCreated()->json();
        $data = $this->sale($medicine, ['customer_id' => $other->id, 'prescription_id' => $rx['id'],
            'medicines' => [['medicine_id' => $medicine->id, 'quantity' => 2, 'prescription_medicine_id' => $rx['medicines'][0]['id']]]]);
        $this->postJson('/api/pharmacy/sales', $data)->assertUnprocessable();
        $data['customer_id'] = $customer->id;
        $sale = $this->postJson('/api/pharmacy/sales', $data)->assertCreated()->json();
        $this->assertEquals('Partially Dispensed', Prescription::find($rx['id'])->status);
        $this->putJson('/api/prescriptions/'.$rx['id'], [])->assertUnprocessable();
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/return', ['return_reason' => 'Test return'])->assertOk();
        $this->assertEquals(10, $medicine->fresh()->quantity);
        $this->assertDatabaseHas('prescription_medicines', ['id' => $rx['medicines'][0]['id'], 'dispensed_quantity' => 0]);
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/return', ['return_reason' => 'Repeat'])->assertStatus(400);
        $this->assertEquals(10, $medicine->fresh()->quantity);
    }

    public function test_reports_exclude_returns_and_enforce_pharmacy_permissions(): void
    {
        $medicine = $this->medicine();
        $sale = $this->postJson('/api/pharmacy/sales', $this->sale($medicine))->assertCreated()->json();
        $this->getJson('/api/pharmacy/reports')->assertOk()->assertJsonPath('sales', 1)->assertJsonPath('revenue', 10);
        $this->postJson('/api/pharmacy/sales/'.$sale['id'].'/return', ['return_reason' => 'Test'])->assertOk();
        $this->getJson('/api/pharmacy/reports')->assertOk()->assertJsonPath('sales', 0)->assertJsonPath('revenue', 0)->assertJsonPath('returns', 1);
        $this->actingAs(User::factory()->create(['role' => 'Receptionist']), 'sanctum');
        $this->getJson('/api/pharmacy/reports')->assertForbidden();
        $this->postJson('/api/pharmacy/purchases', [])->assertForbidden();
    }

    public function test_reports_include_sales_beyond_the_first_page(): void
    {
        $sale = $this->postJson('/api/pharmacy/sales', $this->sale($this->medicine()))->assertCreated()->json();
        $record = (array) DB::table('pharmacy_sales')->find($sale['id']);
        unset($record['id']);
        for ($i = 0; $i < 101; $i++) {
            DB::table('pharmacy_sales')->insert(array_replace($record, ['sale_number' => 'REPORT-'.$i]));
        }
        $this->getJson('/api/pharmacy/reports')->assertOk()->assertJsonPath('sales', 102)->assertJsonPath('revenue', 1020);
    }

    public function test_stock_removal_does_not_allow_negative_inventory(): void
    {
        $medicine = $this->medicine();
        $this->postJson('/api/inventory/stock-out', ['medicine_id' => $medicine->id, 'quantity' => 11])->assertUnprocessable();
        $this->assertEquals(10, $medicine->fresh()->quantity);
        $this->postJson('/api/inventory/stock-out', ['medicine_id' => $medicine->id, 'quantity' => 3, 'purpose' => 'Damaged stock'])->assertCreated();
        $this->assertEquals(7, $medicine->fresh()->quantity);
    }
}
