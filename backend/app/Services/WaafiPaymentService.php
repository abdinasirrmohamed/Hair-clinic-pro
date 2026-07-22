<?php

namespace App\Services;

use App\Models\PaymentGatewayLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WaafiPaymentService
{
    private string $merchantUid;
    private string $apiUserId;
    private string $apiKey;
    private string $endpoint;

    public function __construct()
    {
        $this->merchantUid = config('services.waafi.merchant_uid', env('WAAFI_MERCHANT_UID'));
        $this->apiUserId = config('services.waafi.api_user_id', env('WAAFI_API_USER_ID'));
        $this->apiKey = config('services.waafi.api_key', env('WAAFI_API_KEY'));
        $this->endpoint = config('services.waafi.endpoint', 'https://api.waafipay.net/asm');
    }

    public function charge(float $amount, string $accountNo, string $referenceId, string $invoiceId, string $description = 'Payment'): array
    {
        if (!is_finite($amount) || $amount <= 0 || $amount > 99999999.99) {
            return $this->result([
                'success' => false,
                'transaction_id' => '',
                'response_code' => 'INVALID_AMOUNT',
                'message' => 'Payment amount must be greater than zero and use no more than two decimal places.',
            ], $amount, $accountNo, $referenceId, $invoiceId);
        }

        if (!$this->merchantUid || !$this->apiUserId || !$this->apiKey) {
            return $this->result([
                'success' => false,
                'transaction_id' => '',
                'response_code' => 'NOT_CONFIGURED',
                'message' => 'WaafiPay is not configured. Add its credentials to the backend environment.',
            ], $amount, $accountNo, $referenceId, $invoiceId);
        }

        // Format phone number
        $accountNo = preg_replace('/\D/', '', $accountNo);
        if (strlen($accountNo) === 9) {
            $accountNo = '252' . $accountNo;
        } elseif (strlen($accountNo) === 10 && str_starts_with($accountNo, '0')) {
            $accountNo = '252' . substr($accountNo, 1);
        }
        if (!preg_match('/^252\d{9}$/', $accountNo)) {
            return $this->result([
                'success' => false,
                'transaction_id' => '',
                'response_code' => 'INVALID_ACCOUNT',
                'message' => 'Enter a valid Somali mobile account number.',
            ], $amount, $accountNo, $referenceId, $invoiceId);
        }

        $payload = [
            'schemaVersion' => '1.0',
            'requestId' => uniqid('REQ-'),
            'timestamp' => now()->toIso8601String(),
            'channelName' => 'WEB',
            'serviceName' => 'API_PURCHASE',
            'serviceParams' => [
                'merchantUid' => $this->merchantUid,
                'apiUserId' => $this->apiUserId,
                'apiKey' => $this->apiKey,
                'paymentMethod' => 'mwallet_account',
                'payerInfo' => [
                    'accountNo' => $accountNo,
                ],
                'transactionInfo' => [
                    'referenceId' => $referenceId,
                    'invoiceId' => $invoiceId,
                    'amount' => round((float) $amount, 2),
                    'currency' => 'USD',
                    'description' => $description,
                ],
            ],
        ];

        try {
            $response = Http::asJson()
                ->acceptJson()
                // A mobile prompt is interactive. Retrying a charge can create
                // duplicate prompts and previously pushed PHP past max_execution_time.
                ->connectTimeout(3)
                ->timeout(12)
                ->post($this->endpoint, $payload);
            $data = $response->json();

            if (!$response->successful()) {
                Log::warning('WaafiPay HTTP request failed', [
                    'status' => $response->status(),
                    'reference_id' => $referenceId,
                    'body' => $response->body(),
                ]);

                return $this->result([
                    'success' => false,
                    'transaction_id' => '',
                    'response_code' => 'HTTP_'.$response->status(),
                    'message' => 'WaafiPay is currently unavailable (HTTP '.$response->status().'). Please try again.',
                ], $amount, $accountNo, $referenceId, $invoiceId);
            }

            if (!is_array($data)) {
                return $this->result([
                    'success' => false,
                    'transaction_id' => '',
                    'response_code' => 'INVALID_RESPONSE',
                    'message' => 'WaafiPay returned an invalid response. Please try again.',
                ], $amount, $accountNo, $referenceId, $invoiceId);
            }

            $responseCode = (string) ($data['responseCode'] ?? '');
            $transactionId = (string) (
                $data['params']['transactionId']
                ?? $data['params']['transactionInfo']['transactionId']
                ?? $data['transactionId']
                ?? ''
            );

            if ($responseCode === '2001') {
                return $this->result([
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'response_code' => $responseCode,
                    'message' => 'Payment successful.',
                ], $amount, $accountNo, $referenceId, $invoiceId);
            }

            Log::warning('WaafiPay payment rejected', [
                'response_code' => $responseCode,
                'response_message' => $data['responseMsg'] ?? null,
                'reference_id' => $referenceId,
            ]);

            return $this->result([
                'success' => false,
                'transaction_id' => '',
                'response_code' => $responseCode ?: 'REJECTED',
                'message' => $this->friendlyMessage(
                    $responseCode,
                    $data['responseMsg'] ?? $data['error']['message'] ?? null
                ),
            ], $amount, $accountNo, $referenceId, $invoiceId);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return $this->result([
                'success' => false,
                'transaction_id' => '',
                'response_code' => 'TIMEOUT',
                'message' => 'Payment was cancelled or not approved in time. Nothing was recorded; please try again.',
            ], $amount, $accountNo, $referenceId, $invoiceId);
        } catch (\Exception $e) {
            return $this->result([
                'success' => false,
                'transaction_id' => '',
                'response_code' => 'GATEWAY_ERROR',
                'message' => 'Payment gateway error: ' . $e->getMessage(),
            ], $amount, $accountNo, $referenceId, $invoiceId);
        }
    }

    private function friendlyMessage(string $responseCode, ?string $gatewayMessage): string
    {
        $code = strtoupper(trim($gatewayMessage ?: $responseCode));

        if (str_contains($code, 'USER_REJECTED') || str_contains($code, 'DECLINED')) {
            return 'Payment was declined by the customer. Ask them to approve the request and try again.';
        }
        if (str_contains($code, 'INSUFFICIENT') || str_contains($code, 'BALANCE')) {
            return 'The mobile account has insufficient balance. Ask the customer to add funds or use another payment method.';
        }
        if (str_contains($code, 'USER_NOT_FOUND') || str_contains($code, 'ACCOUNT_NOT_FOUND')) {
            return 'The mobile account was not found. Check the phone number and try again.';
        }
        if (str_contains($code, 'TIMEOUT')) {
            return 'The payment request timed out. Please try again.';
        }

        return match ($code) {
            'RCS_USER_REJECTED' => 'Payment was declined by the customer. Ask them to approve the request and try again.',
            'RCS_USER_NOT_FOUND' => 'The mobile account was not found. Check the phone number and try again.',
            'RCS_INSUFFICIENT_BALANCE' => 'The mobile account has insufficient balance.',
            'RCS_TRANSACTION_TIMEOUT', 'RCS_REQUEST_TIMEOUT' => 'The payment request timed out. Please try again.',
            'RCS_DUPLICATE_TRANSACTION' => 'This payment request has already been processed.',
            default => $gatewayMessage
                ?: 'Payment failed. Verify the mobile number and ask the customer to approve the request.',
        };
    }

    private function result(array $result, float $amount, string $accountNo, string $referenceId, string $invoiceId): array
    {
        try {
            if (Schema::hasTable('payment_gateway_logs')) {
                $digits = preg_replace('/\D/', '', $accountNo);
                PaymentGatewayLog::create([
                    'gateway' => 'WaafiPay',
                    'reference_id' => $referenceId,
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'account_masked' => strlen($digits) > 4
                        ? str_repeat('*', strlen($digits) - 4).substr($digits, -4)
                        : $digits,
                    'status' => $result['success'] ? 'Successful' : 'Failed',
                    'response_code' => $result['response_code'] ?? null,
                    'transaction_id' => $result['transaction_id'] ?: null,
                    'message' => $result['message'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Unable to save payment gateway log', ['message' => $e->getMessage()]);
        }

        return $result;
    }
}
