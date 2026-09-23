<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Services\{AuditLogService, PharmacyWorkflowService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PharmacyWorkflowController extends Controller
{
    public function alerts(Request $request)
    {
        $data = $request->validate(['days' => ['nullable', Rule::in([30,60,90])]]);
        return [
            'low_stock' => Medicine::whereColumn('quantity','<=','reorder_level')->orderBy('quantity')->get(),
            'expired' => Medicine::where('quantity','>',0)->whereDate('expiry_date','<',today())->orderBy('expiry_date')->get(),
            'expiring' => Medicine::where('quantity','>',0)->whereBetween('expiry_date',[today()->toDateString(),today()->addDays((int) ($data['days'] ?? 30))->toDateString()])->orderBy('expiry_date')->get(),
        ];
    }

    public function batch(Request $request, Medicine $medicine)
    {
        $data = $request->validate(['batch_number'=>'required|string|max:100', 'expiry_date'=>'required|date|after_or_equal:today',
            'buying_price'=>'required|numeric|decimal:0,2|min:0|max:1000000', 'barcode'=>'nullable|string|max:100|unique:medicines,barcode']);
        $batch = DB::transaction(function () use ($medicine,$data) {
            $root = Medicine::lockForUpdate()->findOrFail($medicine->batch_group_id ?: $medicine->id);
            abort_if(Medicine::where(fn ($q) => $q->whereKey($root->id)->orWhere('batch_group_id',$root->id))
                ->where('batch_number',$data['batch_number'])->exists(),422,'This batch number already exists for the product.');
            $batch = $root->replicate();
            $batch->fill($data + ['quantity'=>0,'batch_group_id'=>$root->id]);
            $batch->barcode = $data['barcode'] ?? null;
            $batch->save(); return $batch;
        });
        AuditLogService::log('Created medicine batch','Inventory',$batch->id);
        return response()->json($batch,201);
    }

    public function orders()
    {
        $rows = DB::table('pharmacy_orders as o')->join('suppliers as s','s.id','=','o.supplier_id')
            ->select('o.*','s.company_name')->orderByDesc('o.id')->paginate(25);
        foreach ($rows as $row) $row->items = DB::table('pharmacy_order_items as i')->join('medicines as m','m.id','=','i.medicine_id')
            ->where('order_id',$row->id)->select('i.*','m.medicine_name')->get();
        return $rows;
    }

    public function order(Request $request)
    {
        $data = $request->validate(['supplier_id' => 'integer|required|exists:suppliers,id','expected_date'=>'nullable|date|after_or_equal:today',
            'notes'=>'nullable|string|max:5000','items'=>'required|array|min:1','items.*.medicine_id' => 'integer|required|distinct|exists:medicines,id',
            'items.*.quantity'=>'required|integer|min:1|max:1000000','items.*.unit_cost'=>'required|numeric|decimal:0,2|min:0|max:1000000']);
        $id = DB::transaction(function () use ($data) {
            $id = DB::table('pharmacy_orders')->insertGetId(collect($data)->except('items')->all()+[
                'order_number'=>'PO-'.Str::upper(Str::random(12)), 'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            foreach ($data['items'] as $item) DB::table('pharmacy_order_items')->insert($item+['order_id'=>$id]);
            return $id;
        });
        AuditLogService::log('Created purchase order','Pharmacy',$id);
        return response()->json(DB::table('pharmacy_orders')->find($id),201);
    }

    public function cancelOrder(int $order)
    {
        DB::transaction(function () use ($order) {
            $row = DB::table('pharmacy_orders')->where('id',$order)->lockForUpdate()->first(); abort_unless($row,404);
            abort_unless(in_array($row->status,['Ordered','Partially Received']),422,'This order cannot be cancelled.');
            DB::table('pharmacy_orders')->where('id',$order)->update(['status'=>'Cancelled','updated_at'=>now()]);
        });
        AuditLogService::log('Cancelled outstanding purchase order quantities','Pharmacy',$order);
        return ['message'=>'Outstanding order quantities cancelled.'];
    }

    public function payables()
    {
        $rows = DB::table('pharmacy_purchases as p')->join('suppliers as s','s.id','=','p.supplier_id')
            ->select('p.*','s.company_name')->orderByDesc('p.id')->paginate(25);
        foreach ($rows as $row) {
            $row->balance = $row->amount_paid === null ? null : round($row->total_amount-$row->amount_paid,2);
            $row->payments = DB::table('pharmacy_supplier_payments')->where('purchase_id',$row->id)->orderBy('id')->get();
        }
        return $rows;
    }

    public function reconcile(Request $request, int $purchase)
    {
        $data = $request->validate(['amount_paid'=>'required|numeric|decimal:0,2|min:0','due_date'=>'nullable|date']);
        DB::transaction(function () use ($data,$purchase) {
            $p = DB::table('pharmacy_purchases')->where('id',$purchase)->lockForUpdate()->first(); abort_unless($p,404);
            abort_unless($p->amount_paid === null,422,'Payment history has already been confirmed.');
            abort_if($data['amount_paid'] > $p->total_amount,422,'Paid amount exceeds invoice total.');
            DB::table('pharmacy_purchases')->where('id',$purchase)->update($data+['updated_at'=>now()]);
            if ($data['amount_paid'] > 0) DB::table('pharmacy_supplier_payments')->insert([
                'purchase_id'=>$purchase,'amount'=>$data['amount_paid'],'payment_method'=>'Opening balance',
                'reference'=>'Confirmed historical payment','created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        });
        AuditLogService::log('Confirmed historical supplier invoice balance','Pharmacy',$purchase);
        return ['message'=>'Historical balance confirmed.'];
    }

    public function supplierPayment(Request $request, int $purchase)
    {
        $data = $request->validate(['amount'=>'required|numeric|decimal:0,2|min:0.01',
            'payment_method'=>['required',Rule::in(['Cash','Card','Bank Transfer','EVC Plus','Zaad','Sahal'])],'reference'=>'nullable|string|max:255']);
        DB::transaction(function () use ($data,$purchase) {
            $p = DB::table('pharmacy_purchases')->where('id',$purchase)->lockForUpdate()->first(); abort_unless($p,404);
            abort_if($p->amount_paid === null,422,'Confirm the historical balance first.');
            abort_if(round($data['amount'],2) > round($p->total_amount-$p->amount_paid,2),422,'Payment exceeds supplier balance.');
            DB::table('pharmacy_supplier_payments')->insert($data+['purchase_id'=>$purchase,'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            DB::table('pharmacy_purchases')->where('id',$purchase)->update(['amount_paid'=>round($p->amount_paid+$data['amount'],2),'updated_at'=>now()]);
        });
        AuditLogService::log('Recorded supplier payment','Pharmacy',$purchase);
        return ['message'=>'Supplier payment recorded.'];
    }

    public function registers()
    {
        $rows = DB::table('pharmacy_registers')->where('user_id',auth()->id())->orderByDesc('id')->paginate(25);
        foreach ($rows as $row) {
            $row->summary = PharmacyWorkflowService::registerSummary($row->id);
            $row->expected_cash = $row->closed_at ? $row->expected_cash : round($row->opening_cash+$row->summary['net_cash'],2);
        }
        return $rows;
    }

    public function openRegister(Request $request)
    {
        $data = $request->validate(['opening_cash'=>'required|numeric|decimal:0,2|min:0|max:10000000']);
        $id = DB::transaction(function () use ($data) {
            abort_if(PharmacyWorkflowService::registerId(),422,'Close your current register first.');
            return DB::table('pharmacy_registers')->insertGetId($data+['user_id'=>auth()->id(),'opened_at'=>now()]);
        });
        AuditLogService::log('Opened pharmacy register','Pharmacy',$id);
        return response()->json(DB::table('pharmacy_registers')->find($id),201);
    }

    public function closeRegister(Request $request, int $register)
    {
        $data = $request->validate(['counted_cash'=>'required|numeric|decimal:0,2|min:0|max:10000000','notes'=>'nullable|string|max:5000']);
        DB::transaction(function () use ($data,$register) {
            $active = PharmacyWorkflowService::registerId();
            $row = DB::table('pharmacy_registers')->where('id',$register)->where('user_id',auth()->id())->lockForUpdate()->first(); abort_unless($row,404);
            abort_unless($active === $register,422,'This register is already closed.');
            $summary = PharmacyWorkflowService::registerSummary($register);
            $expected = round($row->opening_cash+$summary['net_cash'],2);
            $difference = round($data['counted_cash']-$expected,2);
            abort_if($difference != 0 && empty(trim($data['notes'] ?? '')),422,'Explain the cash difference in notes.');
            DB::table('pharmacy_registers')->where('id',$register)->update($data+['expected_cash'=>$expected,'difference'=>$difference,'closed_at'=>now()]);
        });
        AuditLogService::log('Closed pharmacy register','Pharmacy',$register);
        return DB::table('pharmacy_registers')->find($register);
    }
}
