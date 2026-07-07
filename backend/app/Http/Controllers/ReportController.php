<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PharmacySale;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $filters = $this->filters($request);

        $paymentQuery = $this->paymentsQuery($from, $to, $filters);
        $saleQuery = $this->pharmacySalesQuery($from, $to, $filters);
        $expenseQuery = $this->expensesQuery($from, $to, $filters);
        $appointmentQuery = $this->appointmentsQuery($from, $to, $filters);
        $auditQuery = $this->auditLogsQuery($from, $to, $filters);

        $revenue = (clone $paymentQuery)->whereIn('payment_status', ['Paid', 'Partial'])->sum('amount');
        $pharmacyRevenue = (clone $saleQuery)->where('status', '!=', 'Returned')->sum('total_amount');
        $expenses = (clone $expenseQuery)->sum('amount');
        $appointments = (clone $appointmentQuery)->count();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filters' => $filters,
            'summary' => [
                'patients' => Patient::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'appointments' => $appointments,
                'treatments' => Treatment::whereBetween('treatment_date', [$from->toDateString(), $to->toDateString()])->count(),
                'clinic_revenue' => (float) $revenue,
                'pharmacy_revenue' => (float) $pharmacyRevenue,
                'expenses' => (float) $expenses,
                'net_profit' => (float) ($revenue + $pharmacyRevenue - $expenses),
                'low_stock' => Medicine::whereColumn('quantity', '<=', 'reorder_level')->count(),
            ],
            'appointments_by_status' => (clone $appointmentQuery)
                ->selectRaw('status, COUNT(*) total')->groupBy('status')->get(),
            'recent_movements' => InventoryMovement::with('medicine')->latest()->limit(10)->get(),
            'clinic_payments' => (clone $paymentQuery)->with(['patient', 'creator'])->latest()->limit(50)->get(),
            'pharmacy_sales' => (clone $saleQuery)->with(['patient', 'creator'])->latest()->limit(50)->get(),
            'expenses_by_user' => (clone $expenseQuery)->with('creator')->latest('expense_date')->limit(50)->get(),
            'appointments' => (clone $appointmentQuery)->with(['patient', 'doctor'])->latest('appointment_date')->limit(50)->get(),
            'user_activity' => (clone $auditQuery)->latest()->limit(80)->get(),
            'user_totals' => $this->userTotals($from, $to, $filters),
            'doctor_performance' => $this->doctorPerformance($from, $to, $filters),
            'inventory_audit' => InventoryMovement::with(['medicine', 'user'])
                ->whereBetween('movement_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->latest('movement_date')
                ->limit(80)
                ->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $filters = $this->filters($request);
        $reportType = $filters['report_type'];

        return response()->streamDownload(function () use ($from, $to, $filters, $reportType) {
            $out = fopen('php://output', 'w');

            if ($reportType === 'users') {
                fputcsv($out, ['User', 'Role', 'Clinic Revenue', 'Pharmacy Revenue', 'Expenses', 'Payments', 'Sales', 'Activities']);
                foreach ($this->userTotals($from, $to, $filters) as $row) {
                    fputcsv($out, [
                        $row['name'], $row['role'], $row['clinic_revenue'], $row['pharmacy_revenue'],
                        $row['expenses'], $row['payments_count'], $row['sales_count'], $row['activities'],
                    ]);
                }
            } elseif ($reportType === 'pharmacy') {
                fputcsv($out, ['Sale No', 'Customer', 'Total', 'Method', 'Payment Status', 'Status', 'Cashier', 'Date']);
                foreach ($this->pharmacySalesQuery($from, $to, $filters)->with(['patient', 'creator'])->latest()->get() as $row) {
                    fputcsv($out, [
                        $row->sale_number, $row->customer_name ?: $row->patient?->full_name, $row->total_amount,
                        $row->payment_method, $row->payment_status, $row->status, $row->creator?->full_name, $row->created_at,
                    ]);
                }
            } elseif ($reportType === 'appointments') {
                fputcsv($out, ['Patient', 'Doctor', 'Date', 'Time', 'Status', 'Reason']);
                foreach ($this->appointmentsQuery($from, $to, $filters)->with(['patient', 'doctor'])->latest('appointment_date')->get() as $row) {
                    fputcsv($out, [
                        $row->patient?->full_name, $row->doctor?->full_name, $row->appointment_date,
                        $row->appointment_time, $row->status, $row->reason,
                    ]);
                }
            } elseif ($reportType === 'activity') {
                fputcsv($out, ['User', 'Role', 'Module', 'Action', 'IP Address', 'Date']);
                foreach ($this->auditLogsQuery($from, $to, $filters)->latest()->get() as $row) {
                    fputcsv($out, [
                        $row->user_name, $row->user_role, $row->module_name, $row->action, $row->ip_address, $row->created_at,
                    ]);
                }
            } elseif ($reportType === 'doctor_performance') {
                fputcsv($out, ['Doctor', 'Specialization', 'Appointments', 'Completed', 'Revenue']);
                foreach ($this->doctorPerformance($from, $to, $filters) as $row) {
                    fputcsv($out, [$row['doctor'], $row['specialization'], $row['appointments'], $row['completed'], $row['revenue']]);
                }
            } elseif ($reportType === 'inventory') {
                fputcsv($out, ['Transaction', 'Medicine', 'Type', 'Quantity', 'Old Qty', 'New Qty', 'User', 'Date']);
                foreach (InventoryMovement::with(['medicine', 'user'])->whereBetween('movement_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->latest('movement_date')->get() as $row) {
                    fputcsv($out, [
                        $row->transaction_number, $row->medicine?->medicine_name, $row->movement_type, $row->quantity,
                        $row->old_quantity, $row->new_quantity, $row->user?->full_name, $row->movement_date,
                    ]);
                }
            } else {
                fputcsv($out, ['Reference', 'Patient', 'Amount', 'Method', 'Status', 'User', 'Role', 'Date']);
                foreach ($this->paymentsQuery($from, $to, $filters)->with(['patient', 'creator'])->latest()->get() as $row) {
                    fputcsv($out, [
                        $row->reference_number, $row->patient?->full_name, $row->amount,
                        $row->payment_method, $row->payment_status, $row->creator?->full_name,
                        $row->creator?->role, $row->created_at,
                    ]);
                }
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

    private function filters(Request $request): array
    {
        return [
            'report_type' => $request->input('report_type', 'overview'),
            'user_id' => $request->input('user_id'),
            'role' => $request->input('role'),
            'doctor_id' => $request->input('doctor_id'),
            'payment_method' => $request->input('payment_method'),
            'status' => $request->input('status'),
        ];
    }

    private function paymentsQuery(Carbon $from, Carbon $to, array $filters)
    {
        $query = Payment::query()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($filters['payment_method']) {
            $query->where('payment_method', $filters['payment_method']);
        }
        if ($filters['status']) {
            $query->where('payment_status', $filters['status']);
        }
        $this->applyCreatorFilters($query, $filters);

        return $query;
    }

    private function pharmacySalesQuery(Carbon $from, Carbon $to, array $filters)
    {
        $query = PharmacySale::query()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($filters['payment_method']) {
            $query->where('payment_method', $filters['payment_method']);
        }
        if ($filters['status']) {
            $query->where(function ($statusQuery) use ($filters) {
                $statusQuery->where('status', $filters['status'])
                    ->orWhere('payment_status', $filters['status']);
            });
        }
        $this->applyCreatorFilters($query, $filters);

        return $query;
    }

    private function expensesQuery(Carbon $from, Carbon $to, array $filters)
    {
        $query = Expense::query()->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);
        $this->applyCreatorFilters($query, $filters);

        return $query;
    }

    private function appointmentsQuery(Carbon $from, Carbon $to, array $filters)
    {
        $query = Appointment::query()->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()]);

        if ($filters['doctor_id']) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function auditLogsQuery(Carbon $from, Carbon $to, array $filters)
    {
        $query = AuditLog::query()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }
        if ($filters['role']) {
            $query->where('user_role', $filters['role']);
        }

        return $query;
    }

    private function applyCreatorFilters($query, array $filters): void
    {
        if ($filters['user_id']) {
            $query->where('created_by', $filters['user_id']);
        }

        if ($filters['role']) {
            $userIds = User::where('role', $filters['role'])->pluck('id');
            $query->whereIn('created_by', $userIds);
        }
    }

    private function userTotals(Carbon $from, Carbon $to, array $filters)
    {
        $users = User::when($filters['user_id'], fn ($query) => $query->where('id', $filters['user_id']))
            ->when($filters['role'], fn ($query) => $query->where('role', $filters['role']))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'role']);

        return $users->map(function (User $user) use ($from, $to) {
            $payments = Payment::where('created_by', $user->id)
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
            $sales = PharmacySale::where('created_by', $user->id)
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
            $expenses = Expense::where('created_by', $user->id)
                ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);

            return [
                'user_id' => $user->id,
                'name' => $user->full_name,
                'role' => $user->role,
                'clinic_revenue' => (float) (clone $payments)->whereIn('payment_status', ['Paid', 'Partial'])->sum('amount'),
                'pharmacy_revenue' => (float) (clone $sales)->where('status', '!=', 'Returned')->sum('total_amount'),
                'expenses' => (float) (clone $expenses)->sum('amount'),
                'payments_count' => (clone $payments)->count(),
                'sales_count' => (clone $sales)->count(),
                'activities' => AuditLog::where('user_id', $user->id)
                    ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->count(),
            ];
        });
    }

    private function doctorPerformance(Carbon $from, Carbon $to, array $filters)
    {
        return Doctor::when($filters['doctor_id'], fn ($query) => $query->where('id', $filters['doctor_id']))
            ->orderBy('full_name')
            ->get()
            ->map(function (Doctor $doctor) use ($from, $to) {
                $appointments = Appointment::where('doctor_id', $doctor->id)
                    ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()]);

                return [
                    'doctor' => $doctor->full_name,
                    'specialization' => $doctor->specialization,
                    'appointments' => (clone $appointments)->count(),
                    'completed' => (clone $appointments)->where('status', 'Completed')->count(),
                    'revenue' => (float) (clone $appointments)->sum('fee_at_booking'),
                ];
            });
    }
}
