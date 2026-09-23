<?php

namespace App\Http\Controllers;

use App\Models\{Medicine, Supplier, InventoryMovement, Prescription, PharmacySale};
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PharmacyOperationsController extends Controller
{
    public function purchases(Request $request)
    {
        $rows = DB::table('pharmacy_purchases as p')->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->select('p.*', 's.company_name')->orderByDesc('p.id')->paginate(25);
        foreach ($rows as $row) {
            $row->items = DB::table('pharmacy_purchase_items as i')->leftJoin('medicines as m', 'm.id', '=', 'i.medicine_id')
                ->where('purchase_id', $row->id)->select('i.*', 'm.medicine_name')->get();
        }
        return $rows;
    }

    public function receive(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'integer|nullable|exists:pharmacy_orders,id', 'due_date' => 'nullable|date|after_or_equal:received_date',
            'amount_paid' => 'nullable|numeric|decimal:0,2|min:0',
            'payment_method' => ['nullable', Rule::in(['Cash','Card','Bank Transfer','EVC Plus','Zaad','Sahal'])],
            'supplier_id' => 'integer|required|exists:suppliers,id',
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('pharmacy_purchases')->where('supplier_id', $request->supplier_id)],
            'received_date' => 'required|date|before_or_equal:today', 'notes' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1', 'items.*.medicine_id' => 'integer|required|distinct|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000000', 'items.*.unit_cost' => 'required|numeric|decimal:0,2|min:0|max:1000000',
            'items.*.batch_number' => 'nullable|string|max:100', 'items.*.expiry_date' => 'required|date|after_or_equal:today',
        ]);
        $id = DB::transaction(function () use ($data) {
            $order = null;
            if (!empty($data['order_id'])) {
                $order = DB::table('pharmacy_orders')->where('id',$data['order_id'])->lockForUpdate()->first();
                abort_unless($order && (int)$order->supplier_id === (int)$data['supplier_id'] && in_array($order->status,['Ordered','Partially Received']),422,'Select an open order from this supplier.');
            }
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $total = collect($data['items'])->sum(fn ($i) => round($i['unit_cost'] * $i['quantity'], 2));
            abort_if($total > 99999999.99, 422, 'Purchase total exceeds the supported maximum.');
            $paid = round($data['amount_paid'] ?? 0,2);
            abort_if($paid > $total,422,'Paid amount exceeds the invoice total.');
            $id = DB::table('pharmacy_purchases')->insertGetId([
                'purchase_number' => 'PUR-'.Str::upper(Str::random(12)), 'supplier_id' => $supplier->id,
                'order_id' => $data['order_id'] ?? null, 'due_date' => $data['due_date'] ?? null, 'amount_paid' => $paid,
                'invoice_number' => $data['invoice_number'], 'received_date' => $data['received_date'],
                'notes' => $data['notes'] ?? null, 'total_amount' => $total, 'created_by' => auth()->id(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (collect($data['items'])->sortBy('medicine_id') as $item) {
                $medicine = Medicine::lockForUpdate()->findOrFail($item['medicine_id']);
                // A medicine row represents one batch: do not overwrite the expiry of remaining stock.
                abort_if($medicine->quantity > 0 && (
                    (string) $medicine->batch_number !== (string) ($item['batch_number'] ?? '') ||
                    substr((string) $medicine->expiry_date, 0, 10) !== $item['expiry_date']
                ), 422, 'Use a separate medicine record for a different batch or expiry date.');
                if ($order) {
                    $line = DB::table('pharmacy_order_items')->where('order_id',$order->id)->where('medicine_id',$medicine->id)->first();
                    abort_unless($line && $item['quantity'] <= $line->quantity-$line->received_quantity,422,'Receiving exceeds the outstanding quantity or product is not on this order.');
                    DB::table('pharmacy_order_items')->where('id',$line->id)->increment('received_quantity',$item['quantity']);
                }
                $old = $medicine->quantity;
                abort_if($old + $item['quantity'] > 2147483647, 422, 'Stock quantity exceeds the supported maximum.');
                $medicine->update([
                    'quantity' => $old + $item['quantity'], 'buying_price' => $item['unit_cost'],
                    'batch_number' => $item['batch_number'] ?? null, 'expiry_date' => $item['expiry_date'],
                    'supplier_id' => $supplier->id, 'supplier' => $supplier->company_name,
                ]);
                DB::table('pharmacy_purchase_items')->insert([
                    'purchase_id' => $id, 'medicine_id' => $medicine->id, 'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'], 'total_cost' => round($item['unit_cost'] * $item['quantity'], 2),
                    'batch_number' => $item['batch_number'] ?? null, 'expiry_date' => $item['expiry_date'],
                ]);
                InventoryMovement::create([
                    'transaction_number' => 'IN-'.Str::upper(Str::random(12)), 'medicine_id' => $medicine->id,
                    'supplier_id' => $supplier->id, 'movement_type' => 'Stock In', 'quantity' => $item['quantity'],
                    'old_quantity' => $old, 'new_quantity' => $medicine->quantity, 'unit_cost' => $item['unit_cost'],
                    'total_cost' => round($item['unit_cost'] * $item['quantity'], 2),
                    'reference_type' => 'Pharmacy Purchase', 'reference_id' => $id, 'issued_by' => auth()->id(),
                ]);
            }
            if ($paid > 0) DB::table('pharmacy_supplier_payments')->insert(['purchase_id'=>$id,'amount'=>$paid,
                'payment_method'=>$data['payment_method'] ?? 'Cash','created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            if ($order) {
                $pending = DB::table('pharmacy_order_items')->where('order_id',$order->id)->whereColumn('received_quantity','<','quantity')->exists();
                DB::table('pharmacy_orders')->where('id',$order->id)->update(['status'=>$pending ? 'Partially Received' : 'Received','updated_at'=>now()]);
            }
            return $id;
        });
        AuditLogService::log('Received pharmacy purchase', 'Pharmacy', $id);
        return response()->json(DB::table('pharmacy_purchases')->find($id), 201);
    }

    public function prescription(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'integer|required|exists:pharmacy_customers,id', 'prescriber_name' => 'required|string|max:150',
            'prescription_date' => 'required|date|before_or_equal:today', 'instructions' => 'nullable|string|max:5000',
            'medicines' => 'required|array|min:1', 'medicines.*.medicine_id' => 'integer|required|distinct|exists:medicines,id',
            'medicines.*.quantity' => 'required|integer|min:1|max:2147483647', 'medicines.*.frequency' => 'required|string|max:100',
            'medicines.*.instructions' => 'required|string|max:255',
        ]);
        $rx = DB::transaction(function () use ($data) {
            $rx = Prescription::create(collect($data)->except('medicines')->all() + [
                'prescription_number' => 'RX-'.Str::upper(Str::random(12)), 'status' => 'Pending',
            ]);
            $rx->medicines()->createMany($data['medicines']);
            return $rx;
        });
        AuditLogService::log('Recorded external prescription', 'Pharmacy', $rx->id);
        return response()->json($rx->load(['customer', 'medicines.medicine']), 201);
    }

    public function collectPayment(Request $request, PharmacySale $sale)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|decimal:0,2|min:0.01',
            'payment_method' => ['required', Rule::in(['Cash', 'Card', 'Bank Transfer'])],
            'reference' => 'nullable|string|max:255',
        ]);
        DB::transaction(function () use ($sale, $data) {
            $registerId = \App\Services\PharmacyWorkflowService::registerId();
            $sale = PharmacySale::lockForUpdate()->findOrFail($sale->id);
            abort_if($sale->status === 'Returned', 422, 'Cannot collect payment on a returned sale.');
            abort_if($data['amount'] > $sale->remaining_balance, 422, 'Payment exceeds the outstanding balance.');
            $remaining = round($sale->remaining_balance - $data['amount'], 2);
            DB::table('pharmacy_sale_payments')->insert($data + [
                'sale_id' => $sale->id, 'register_id' => $registerId, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $sale->update(['amount_paid' => round($sale->amount_paid + $data['amount'], 2),
                'remaining_balance' => $remaining, 'status' => $remaining > 0 ? 'Partial Paid' : 'Paid',
                'payment_status' => $remaining > 0 ? 'Partial Paid' : 'Full Paid']);
        });
        AuditLogService::log('Collected pharmacy balance', 'Pharmacy', $sale->id);
        return $sale->fresh();
    }

    public function reports(Request $request)
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $sales = PharmacySale::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
        $active = (clone $sales)->where('status', '!=', 'Returned');
        $daily = (clone $active)->selectRaw('DATE(created_at) as day, COUNT(*) as sales, SUM(total_amount) as revenue')->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $top = DB::table('pharmacy_sale_medicines as i')->join('pharmacy_sales as s', 's.id', '=', 'i.sale_id')
            ->join('medicines as m', 'm.id', '=', 'i.medicine_id')->where('s.status', '!=', 'Returned')
            ->whereDate('s.created_at', '>=', $from)->whereDate('s.created_at', '<=', $to)
            ->selectRaw('m.medicine_name, SUM(i.quantity) as quantity, SUM(i.subtotal) as gross_sales')
            ->groupBy('m.id', 'm.medicine_name')->orderByDesc('quantity')->limit(10)->get();
        return [
            'from' => $from, 'to' => $to, 'sales' => (clone $active)->count(),
            'revenue' => (float) (clone $active)->sum('total_amount'),
            'collected' => (float) (clone $active)->sum('amount_paid'),
            'outstanding' => (float) PharmacySale::where('status', '!=', 'Returned')->sum('remaining_balance'),
            'returns' => (clone $sales)->where('status', 'Returned')->count(),
            'purchases' => (float) DB::table('pharmacy_purchases')->whereBetween('received_date', [$from, $to])->sum('total_amount'),
            'stock_value' => (float) Medicine::selectRaw('SUM(quantity * buying_price) as value')->value('value'),
            'low_stock' => Medicine::whereColumn('quantity', '<=', 'reorder_level')->orderBy('quantity')->get(),
            'expiring' => Medicine::where('quantity', '>', 0)->whereBetween('expiry_date', [today()->toDateString(), today()->addDays(90)->toDateString()])->orderBy('expiry_date')->get(),
            'expired' => Medicine::where('quantity', '>', 0)->whereDate('expiry_date', '<', today())->get(),
            'daily' => $daily, 'top_medicines' => $top,
        ];
    }
}
