<?php

namespace App\Http\Controllers;

use App\Models\{Medicine, InventoryMovement};
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PharmacyStocktakeController extends Controller
{
    public function index()
    {
        return DB::table('pharmacy_stocktakes as s')->leftJoin('users as u','u.id','=','s.created_by')
            ->select('s.*','u.full_name as creator_name')->orderByDesc('s.id')->paginate(25);
    }
    public function show(int $stocktake)
    {
        $row = DB::table('pharmacy_stocktakes')->find($stocktake); abort_unless($row,404);
        $row->items = DB::table('pharmacy_stocktake_items')->where('stocktake_id',$stocktake)->orderBy('medicine_name')->get();
        return $row;
    }
    public function store(Request $request)
    {
        $data=$request->validate(['medicine_ids'=>'required|array|min:1|max:1000','medicine_ids.*'=>'required|distinct|exists:medicines,id','notes'=>'nullable|string|max:5000']);
        $id=DB::transaction(function () use ($data) {
            $id=DB::table('pharmacy_stocktakes')->insertGetId(['number'=>'COUNT-'.Str::upper(Str::random(10)),
                'created_by'=>auth()->id(),'notes'=>$data['notes']??null,'created_at'=>now(),'updated_at'=>now()]);
            foreach (Medicine::whereIn('id',$data['medicine_ids'])->orderBy('id')->lockForUpdate()->get() as $m) {
                DB::table('pharmacy_stocktake_items')->insert(['stocktake_id'=>$id,'medicine_id'=>$m->id,'medicine_name'=>$m->medicine_name,
                    'batch_number'=>$m->batch_number,'expected_quantity'=>$m->quantity,
                    'movement_id'=>InventoryMovement::where('medicine_id',$m->id)->max('id')??0]);
            }
            return $id;
        });
        AuditLogService::log('Started stocktake','Inventory',$id);
        return response()->json($this->show($id),201);
    }
    public function update(Request $request,int $stocktake)
    {
        $data=$request->validate(['items'=>'required|array|min:1|max:1000','items.*.id'=>'required|integer|distinct',
            'items.*.counted_quantity'=>'required|integer|min:0|max:1000000','items.*.reason'=>'nullable|string|max:255']);
        DB::transaction(function () use ($stocktake,$data) {
            $s=DB::table('pharmacy_stocktakes')->where('id',$stocktake)->lockForUpdate()->first(); abort_unless($s,404);
            abort_unless($s->status==='Draft',422,'Only draft counts can be edited.');
            foreach ($data['items'] as $item) {
                $row=DB::table('pharmacy_stocktake_items')->where('stocktake_id',$stocktake)->where('id',$item['id'])->first();
                abort_unless($row,422,'Count line does not belong to this stocktake.');
                DB::table('pharmacy_stocktake_items')->where('id',$row->id)->update(['counted_quantity'=>$item['counted_quantity'],'reason'=>$item['reason']??null]);
            }
            DB::table('pharmacy_stocktakes')->where('id',$stocktake)->update(['updated_at'=>now()]);
        });
        return $this->show($stocktake);
    }
    public function submit(int $stocktake)
    {
        DB::transaction(function () use ($stocktake) {
            $s=DB::table('pharmacy_stocktakes')->where('id',$stocktake)->lockForUpdate()->first(); abort_unless($s,404);
            abort_unless($s->status==='Draft',422,'Only draft counts can be submitted.');
            foreach (DB::table('pharmacy_stocktake_items')->where('stocktake_id',$stocktake)->get() as $i) {
                abort_if($i->counted_quantity===null,422,'Count every selected batch before submitting.');
                abort_if($i->counted_quantity!=$i->expected_quantity && !trim($i->reason??''),422,'Every stock difference needs a reason.');
            }
            DB::table('pharmacy_stocktakes')->where('id',$stocktake)->update(['status'=>'Submitted','updated_at'=>now()]);
        });
        AuditLogService::log('Submitted stocktake','Inventory',$stocktake);
        return $this->show($stocktake);
    }
    public function recount(int $stocktake)
    {
        DB::transaction(function () use ($stocktake) {
            $s=DB::table('pharmacy_stocktakes')->where('id',$stocktake)->lockForUpdate()->first(); abort_unless($s,404);
            abort_if($s->status==='Approved',422,'Approved stocktakes cannot be reopened.');
            foreach (DB::table('pharmacy_stocktake_items')->where('stocktake_id',$stocktake)->orderBy('medicine_id')->get() as $i) {
                $m=Medicine::lockForUpdate()->findOrFail($i->medicine_id);
                DB::table('pharmacy_stocktake_items')->where('id',$i->id)->update(['expected_quantity'=>$m->quantity,
                    'counted_quantity'=>null,'reason'=>null,'movement_id'=>InventoryMovement::where('medicine_id',$m->id)->max('id')??0]);
            }
            DB::table('pharmacy_stocktakes')->where('id',$stocktake)->update(['status'=>'Draft','updated_at'=>now()]);
        });
        AuditLogService::log('Reset stocktake for recount','Inventory',$stocktake);
        return $this->show($stocktake);
    }
    public function approve(int $stocktake)
    {
        DB::transaction(function () use ($stocktake) {
            $s=DB::table('pharmacy_stocktakes')->where('id',$stocktake)->lockForUpdate()->first(); abort_unless($s,404);
            abort_unless($s->status==='Submitted',422,'Only submitted stocktakes can be approved.');
            foreach (DB::table('pharmacy_stocktake_items')->where('stocktake_id',$stocktake)->orderBy('medicine_id')->get() as $i) {
                $m=Medicine::lockForUpdate()->findOrFail($i->medicine_id);
                $movement=InventoryMovement::where('medicine_id',$m->id)->max('id')??0;
                abort_if($m->quantity!=$i->expected_quantity || $movement!=$i->movement_id,422,"Stock changed for {$i->medicine_name}. Recount before approval.");
                $delta=$i->counted_quantity-$m->quantity;
                if ($delta) {
                    $m->update(['quantity'=>$i->counted_quantity]);
                    InventoryMovement::create(['transaction_number'=>'COUNT-'.Str::upper(Str::random(16)), 'medicine_id'=>$m->id,
                        'movement_type'=>$delta>0?'Stock In':'Stock Out','quantity'=>abs($delta),'old_quantity'=>$i->expected_quantity,
                        'new_quantity'=>$i->counted_quantity,'unit_cost'=>$m->buying_price??0,'total_cost'=>abs($delta)*($m->buying_price??0),
                        'purpose'=>$i->reason,'reference_type'=>'Stocktake','reference_id'=>$stocktake,'issued_by'=>auth()->id()]);
                }
            }
            DB::table('pharmacy_stocktakes')->where('id',$stocktake)->update(['status'=>'Approved','approved_by'=>auth()->id(),'approved_at'=>now(),'updated_at'=>now()]);
        });
        AuditLogService::log('Approved stocktake adjustments','Inventory',$stocktake);
        return $this->show($stocktake);
    }
}
