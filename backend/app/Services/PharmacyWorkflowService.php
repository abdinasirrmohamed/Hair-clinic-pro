<?php

namespace App\Services;

use App\Models\{Medicine, User};
use Illuminate\Support\Facades\DB;

class PharmacyWorkflowService
{
    // The user row serializes opening, closing, and posting to the same cashier's drawer.
    public static function registerId(): ?int
    {
        User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
        return DB::table('pharmacy_registers')->where('user_id', auth()->id())->whereNull('closed_at')->value('id');
    }

    public static function allocate(array $lines): array
    {
        $allocated = []; $reserved = [];
        foreach (collect($lines)->sortBy('medicine_id') as $line) {
            $selected = Medicine::findOrFail($line['medicine_id']);
            $group = $selected->batch_group_id ?: $selected->id;
            $batches = Medicine::where(fn ($q) => $q->where('id', $group)->orWhere('batch_group_id', $group))
                ->where('quantity', '>', 0)->whereDate('expiry_date', '>=', today())
                ->orderBy('expiry_date')->orderBy('id')->lockForUpdate()->get();
            $remaining = (int) $line['quantity'];
            foreach ($batches as $batch) {
                $qty = min($remaining, max(0, $batch->quantity - ($reserved[$batch->id] ?? 0)));
                if (!$qty) continue;
                $reserved[$batch->id] = ($reserved[$batch->id] ?? 0) + $qty;
                $allocated[] = array_replace($line, ['medicine_id' => $batch->id, 'quantity' => $qty]);
                $remaining -= $qty;
                if (!$remaining) break;
            }
            abort_if($remaining > 0, 422, "Insufficient unexpired stock for {$selected->medicine_name}.");
        }
        return $allocated;
    }

    public static function registerSummary(int $id): array
    {
        $payments = DB::table('pharmacy_sale_payments')->where('register_id', $id)
            ->selectRaw('payment_method, SUM(amount) as amount')->groupBy('payment_method')->pluck('amount','payment_method');
        $refunds = DB::table('pharmacy_refunds')->where('register_id', $id)
            ->selectRaw('payment_method, SUM(amount) as amount')->groupBy('payment_method')->pluck('amount','payment_method');
        return ['collections' => $payments, 'refunds' => $refunds, 'net_cash' => round(($payments['Cash'] ?? 0) - ($refunds['Cash'] ?? 0), 2)];
    }
}
