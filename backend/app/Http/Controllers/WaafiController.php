<?php

namespace App\Http\Controllers;

use App\Services\WaafiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaafiController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'configured' => filled(config('services.waafi.merchant_uid'))
                && filled(config('services.waafi.api_user_id'))
                && filled(config('services.waafi.api_key')),
            'endpoint' => config('services.waafi.endpoint'),
            'merchant' => $this->mask((string) config('services.waafi.merchant_uid')),
        ]);
    }

    public function test(Request $request, WaafiPaymentService $waafi): JsonResponse
    {
        abort_unless($request->user()?->role === 'Administrator', 403, 'Only administrators can test WaafiPay.');

        $validated = $request->validate([
            'account_no' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1'],
        ]);

        $stamp = now()->format('YmdHis');
        $result = $waafi->charge(
            (float) $validated['amount'],
            $validated['account_no'],
            'TEST-'.$stamp,
            'TEST-INV-'.$stamp,
            'Hair Clinic Pro WaafiPay test'
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    private function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return str_repeat('*', max(strlen($value) - 4, 0)).substr($value, -4);
    }
}
