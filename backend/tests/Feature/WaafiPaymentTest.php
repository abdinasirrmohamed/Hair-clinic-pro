<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayLog;
use App\Models\User;
use App\Services\WaafiPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WaafiPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.waafi.endpoint' => 'https://waafi.test/asm',
            'services.waafi.merchant_uid' => 'merchant-test',
            'services.waafi.api_user_id' => 'api-user-test',
            'services.waafi.api_key' => 'secret-api-key',
        ]);
    }

    public function test_successful_charge_is_logged_with_a_masked_account(): void
    {
        $user = User::factory()->create(['role' => 'Administrator']);
        $this->actingAs($user);

        Http::fake([
            'https://waafi.test/asm' => Http::response([
                'responseCode' => '2001',
                'responseMsg' => 'Approved',
                'params' => ['transactionId' => 'TX-1001'],
            ]),
        ]);

        $result = app(WaafiPaymentService::class)->charge(
            0.01,
            '0612345678',
            'REF-TEST-1',
            'INV-TEST-1'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('TX-1001', $result['transaction_id']);

        $log = PaymentGatewayLog::firstOrFail();
        $this->assertSame('Successful', $log->status);
        $this->assertSame('2001', $log->response_code);
        $this->assertStringEndsWith('5678', $log->account_masked);
        $this->assertStringNotContainsString('0612345678', $log->account_masked);
        $this->assertSame($user->id, $log->created_by);
    }

    public function test_rejected_charge_records_the_gateway_reason(): void
    {
        Http::fake([
            'https://waafi.test/asm' => Http::response([
                'responseCode' => '5310',
                'responseMsg' => 'Insufficient balance',
            ]),
        ]);

        $result = app(WaafiPaymentService::class)->charge(
            0.01,
            '252612345678',
            'REF-TEST-2',
            'INV-TEST-2'
        );

        $this->assertFalse($result['success']);
        $this->assertSame('5310', $result['response_code']);
        $this->assertDatabaseHas('payment_gateway_logs', [
            'reference_id' => 'REF-TEST-2',
            'status' => 'Failed',
            'response_code' => '5310',
            'message' => 'The mobile account has insufficient balance. Ask the customer to add funds or use another payment method.',
        ]);
    }

    public function test_gateway_history_requires_payment_permission(): void
    {
        $receptionist = User::factory()->create([
            'role' => 'Receptionist',
            'module_permissions' => [],
        ]);

        $this->actingAs($receptionist, 'sanctum')
            ->getJson('/api/payments/gateway-logs')
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/payments/gateway-logs')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_only_an_administrator_can_send_a_test_charge(): void
    {
        $receptionist = User::factory()->create([
            'role' => 'Receptionist',
            'module_permissions' => ['settings'],
        ]);

        $this->actingAs($receptionist, 'sanctum')
            ->postJson('/api/settings/waafi/test', [
                'account_no' => '0612345678',
                'amount' => 0.01,
            ])
            ->assertForbidden();
    }
}
