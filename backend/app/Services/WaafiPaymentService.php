<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                    'amount' => round((float) $amount, 2),
                    'currency' => 'USD',
                    'description' => $description,
                ],
            ],
        ];

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(45)
                ->retry(2, 500)
                ->post($this->endpoint, $payload);
            $data = $response->json();

            if (!$response->successful()) {
                Log::warning('WaafiPay HTTP request failed', [
                    'status' => $response->status(),
                    'reference_id' => $referenceId,
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'transaction_id' => '',
                    'message' => 'WaafiPay is currently unavailable (HTTP '.$response->status().'). Please try again.',
                ];
            }

            if (!is_array($data)) {
                return [
                    'success' => false,
                    'transaction_id' => '',
                    'message' => 'WaafiPay returned an invalid response. Please try again.',
                ];
            }

            $responseCode = (string) ($data['responseCode'] ?? '');
            $transactionId = (string) (
                $data['params']['transactionId']
                ?? $data['params']['transactionInfo']['transactionId']
                ?? $data['transactionId']
                ?? ''
            );

            if ($responseCode === '2001') {
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'message' => 'Payment successful.',
                ];
            }

            Log::warning('WaafiPay payment rejected', [
                'response_code' => $responseCode,
                'response_message' => $data['responseMsg'] ?? null,
                'reference_id' => $referenceId,
            ]);

            return [
                'success' => false,
                'transaction_id' => '',
                'message' => $data['responseMsg']
                    ?? $data['error']['message']
                    ?? 'Payment failed. No approval prompt was received; verify the mobile number and WaafiPay credentials.',
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
