<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PharmacySale;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $revenue = Payment::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereIn('payment_status', ['Paid', 'Partial'])->sum('amount');
        $pharmacyRevenue = PharmacySale::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', '!=', 'Returned')->sum('total_amount');
        $expenses = Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->sum('amount');

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'patients' => Patient::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'appointments' => Appointment::whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])->count(),
                'treatments' => Treatment::whereBetween('treatment_date', [$from->toDateString(), $to->toDateString()])->count(),
                'clinic_revenue' => (float) $revenue,
                'pharmacy_revenue' => (float) $pharmacyRevenue,
                'expenses' => (float) $expenses,
                'net_profit' => (float) ($revenue + $pharmacyRevenue - $expenses),
                'low_stock' => Medicine::whereColumn('quantity', '<=', 'reorder_level')->count(),
            ],
            'appointments_by_status' => Appointment::whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('status, COUNT(*) total')->groupBy('status')->get(),
            'recent_movements' => InventoryMovement::with('medicine')->latest()->limit(10)->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = Payment::with('patient')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Patient', 'Amount', 'Method', 'Status', 'Date']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->reference_number, $row->patient?->full_name, $row->amount,
                    $row->payment_method, $row->payment_status, $row->created_at,
                ]);
            }
            fclose($out);
        }, 'hair-clinic-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function finance(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $payments = Payment::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereIn('payment_status', ['Paid', 'Partial'])
            ->selectRaw('DATE(created_at) day, SUM(amount) total')->groupBy('day')->pluck('total', 'day');
        $sales = PharmacySale::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', '!=', 'Returned')
            ->selectRaw('DATE(created_at) day, SUM(total_amount) total')->groupBy('day')->pluck('total', 'day');
        $expenses = Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('expense_date day, SUM(amount) total')->groupBy('day')->pluck('total', 'day');

        $series = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $series[] = [
                'date' => $key,
                'revenue' => (float) (($payments[$key] ?? 0) + ($sales[$key] ?? 0)),
                'expenses' => (float) ($expenses[$key] ?? 0),
            ];
        }
        return response()->json($series);
    }

    private function range(Request $request): array
    {
        $period = $request->input('period', 'monthly');
        $to = Carbon::parse($request->input('to', now()->toDateString()));
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))
            : match ($period) {
                'daily' => $to->copy(),
                'weekly' => $to->copy()->subDays(6),
                default => $to->copy()->startOfMonth(),
            };
        return [$from, $to];
    }
}
