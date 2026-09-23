<?php

namespace App\Http\Controllers;

use App\Models\PharmacySale;
use Illuminate\Http\Request;

class PharmacyProfitController extends Controller
{
    public function index(Request $request)
    {
        $v=$request->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        $from=$v['from']??today()->startOfMonth()->toDateString(); $to=$v['to']??today()->toDateString();
        $rows=[]; $net=0; $cost=0; $unknownRevenue=0; $unknownLines=0; $sales=0; $returned=0;
        foreach (PharmacySale::with(['medicines'=>fn($q)=>$q->orderBy('id'), 'medicines.medicine'])->whereDate('created_at','>=',$from)->whereDate('created_at','<=',$to)->orderBy('id')->lazyById(200) as $sale) {
            if ($sale->status==='Returned') { $returned++; continue; }
            $sales++; $discountLeft=(int)round($sale->discount_amount*100);
            foreach ($sale->medicines as $index=>$line) {
                $gross=(int)round($line->subtotal*100);
                $discount=$index===$sale->medicines->count()-1 ? $discountLeft : min($discountLeft,(int)round($sale->subtotal>0?$sale->discount_amount*100*$line->subtotal/$sale->subtotal:0));
                $discountLeft-=$discount; $revenue=$gross-$discount; $net+=$revenue;
                $lineCost=$line->unit_cost===null ? null : (int)round($line->unit_cost*$line->quantity*100);
                if ($lineCost===null) { $unknownRevenue+=$revenue; $unknownLines++; } else { $cost+=$lineCost; }
                $key=$line->medicine_id;
                $rows[$key]??=['medicine_id'=>$key,'medicine_name'=>$line->medicine?->medicine_name??'Deleted medicine','batch_number'=>$line->medicine?->batch_number,
                    'quantity'=>0,'net_revenue'=>0,'cost'=>0,'missing_cost_lines'=>0];
                $rows[$key]['quantity']+=$line->quantity; $rows[$key]['net_revenue']+=$revenue;
                $rows[$key]['cost']+=$lineCost??0; $rows[$key]['missing_cost_lines']+=($lineCost===null?1:0);
            }
        }
        foreach ($rows as &$row) {
            $row['gross_profit']=$row['missing_cost_lines']?null:round(($row['net_revenue']-$row['cost'])/100,2);
            $row['net_revenue']/=100; $row['cost']/=100;
        } unset($row);
        return ['from'=>$from,'to'=>$to,'sales'=>$sales,'returned_sales_excluded'=>$returned,'net_revenue'=>$net/100,
            'known_cost'=>$cost/100,'missing_cost_lines'=>$unknownLines,'revenue_without_cost'=>$unknownRevenue/100,
            'known_cost_profit'=>($net-$unknownRevenue-$cost)/100,'gross_profit'=>$unknownLines?null:($net-$cost)/100,
            'margin_percent'=>!$unknownLines&&$net>0?round(($net-$cost)/$net*100,2):null,'items'=>array_values($rows)];
    }
}
