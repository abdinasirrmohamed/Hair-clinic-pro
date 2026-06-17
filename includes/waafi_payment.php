<?php

class WaafiPayment
{
    private string $endpoint    = 'https://api.waafipay.net/asm';
    private string $merchantUid = 'M0910291';
    private string $apiUserId   = '1000416';
    private string $apiKey      = 'API-675418888AHX';

    public function charge(float $amount, string $accountNo, string $referenceId, string $invoiceId, string $description = 'Hair Clinic Payment'): array
    {
        $accountNo = preg_replace('/\D/', '', $accountNo);
        if (!str_starts_with($accountNo, '252')) {
            $accountNo = '252' . ltrim($accountNo, '0');
        }

        return $this->callApi($amount, $accountNo, $referenceId, $invoiceId, $description);
    }

    private function callApi(float $amount, string $accountNo, string $referenceId, string $invoiceId, string $description): array
    {
        $payload = [
            'schemaVersion' => '1.0',
            'requestId'     => (string) time() . rand(100, 999),
            'timestamp'     => date('Y-m-d\TH:i:s'),
            'channelName'   => 'WEB',
            'serviceName'   => 'API_PURCHASE',
            'serviceParams' => [
                'merchantUid'     => $this->merchantUid,
                'apiUserId'       => $this->apiUserId,
                'apiKey'          => $this->apiKey,
                'paymentMethod'   => 'mwallet_account',
                'payerInfo'       => ['accountNo' => $accountNo],
                'transactionInfo' => [
                    'referenceId' => $referenceId,
                    'invoiceId'   => $invoiceId,
                    'amount'      => round($amount, 2),
                    'currency'    => 'USD',
                    'description' => $description,
                ],
            ],
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            return ['success' => false, 'message' => "Cannot reach WaafiPay gateway: $curlErr"];
        }

        $response = json_decode($raw, true);
        if (!is_array($response)) {
            return ['success' => false, 'message' => 'Invalid response from WaafiPay gateway.'];
        }

        $responseCode = $response['params']['responseCode'] ?? $response['responseCode'] ?? null;
        $state        = $response['params']['state']        ?? $response['state']        ?? null;
        $txId         = $response['params']['transactionId'] ?? $response['transactionId'] ?? null;
        $responseMsg  = $response['params']['responseMsg']  ?? $response['responseMsg']  ?? 'Payment declined.';

        if ($responseCode === '2001' || $state === 'APPROVED') {
            return [
                'success'        => true,
                'transaction_id' => (string) ($txId ?? ''),
                'message'        => 'Payment approved.',
                'raw'            => $response,
            ];
        }

        return [
            'success' => false,
            'message' => "$responseMsg (code: $responseCode)",
            'raw'     => $response,
        ];
    }
}
