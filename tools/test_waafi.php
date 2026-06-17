<?php
$endpoints = [
    'https://api.waafipay.net/asm',
    'https://waafipay.net/asm',
    'https://waafi.net/asm',
    'https://pay.hormuud.com/api/Purchase',
];

$payload = json_encode([
    'schemaVersion' => '1.0',
    'requestId'     => '10111331033',
    'timestamp'     => date('Y-m-d\TH:i:s'),
    'channelName'   => 'WEB',
    'serviceName'   => 'API_PURCHASE',
    'serviceParams' => [
        'merchantUid'     => 'M0910291',
        'apiUserId'       => '1000416',
        'apiKey'          => 'API-675418888AHX',
        'paymentMethod'   => 'mwallet_account',
        'payerInfo'       => ['accountNo' => '252618827482'],
        'transactionInfo' => [
            'referenceId' => '12334',
            'invoiceId'   => '7896504',
            'amount'      => 1.00,
            'currency'    => 'USD',
            'description' => 'Test USD',
        ],
    ],
]);

foreach ($endpoints as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "URL   : $url\n";
    echo "HTTP  : $http | Error: " . ($err ?: 'none') . "\n";
    echo "Body  : " . substr($raw ?: '', 0, 150) . "\n\n";
}
