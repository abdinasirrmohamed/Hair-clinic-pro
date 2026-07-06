<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
        if (!$this->merchantUid || !$this->apiUserId || !$this->apiKey) {
            return [
                'success' => false,
                'transaction_id' => '',
                'message' => 'WaafiPay is not configured. Add its credentials to the backend environment.',
            ];
        }

        // Format phone number
        $accountNo = preg_replace('/\D/', '', $accountNo);
        if (strlen($accountNo) === 9) {
            $accountNo = '252' . $accountNo;
        } elseif (strlen($accountNo) === 10 && str_starts_with($accountNo, '0')) {
            $accountNo = '252' . substr($accountNo, 1);
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
                    'amount' => $amount,
                    'currency' => 'USD',
                    'description' => $description,
                ],
            ],
        ];

        try {
            $response = Http::timeout(30)->post($this->endpoint, $payload);
            $data = $response->json();

            if (isset($data['responseCode']) && $data['responseCode'] === '2001') {
                return [
                    'success' => true,
                    'transaction_id' => $data['params']['transactionId'] ?? '',
                    'message' => 'Payment successful.',
                ];
            }

            return [
                'success' => false,
                'transaction_id' => '',
                'message' => $data['responseMsg'] ?? 'Payment failed.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'transaction_id' => '',
                'message' => 'Payment gateway error: ' . $e->getMessage(),
            ];
        }
    }
}
