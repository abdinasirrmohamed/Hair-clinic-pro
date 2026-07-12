<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\PharmacySale;
use App\Models\Treatment;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    private array $categories = [
        'Staff Salaries',
        'Medical Supplies',
        'Medicine Purchases',
        'Rent',
        'Electricity',
        'Water',
        'Internet',
        'Equipment Maintenance',
        'Administrative Expenses',
        'Miscellaneous Expenses',
    ];

    public function index(Request $request): JsonResponse
    {
        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('to', Carbon::now()->endOfMonth()->toDateString());

        $query = Expense::with('creator')->whereBetween('expense_date', [$from, $to]);

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $expenses = $query->paginate(15);
        $totalExpenses = Expense::whereBetween('expense_date', [$from, $to])->sum('amount');
        
        $totalRevenue = Payment::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                        ->whereIn('payment_status', ['Paid', 'Partial'])->sum('amount')
                      + PharmacySale::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                        ->where('status', '!=', 'Returned')->sum('total_amount');

        return response()->json([
            'expenses' => $expenses,
            'summary' => [
                'total_expenses' => $totalExpenses,
                'total_revenue' => $totalRevenue,
                'net_profit' => $totalRevenue - $totalExpenses,
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in($this->categories)],
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'vendor' => 'nullable|string',
            'description' => 'nullable|string',
            'receipt' => 'nullable|image|max:3072'
        ]);

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store('expense_receipts', 'public');
        }

        $validated['created_by'] = auth()->id();
        $expense = Expense::create($validated);

        AuditLogService::log('Recorded expense', 'Finance', $expense->id);

        return response()->json($expense, 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json($expense);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'category' => [Rule::in($this->categories)],
            'expense_date' => 'date',
            'amount' => 'numeric',
            'vendor' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store('expense_receipts', 'public');
        }

        $expense->update($validated);
        AuditLogService::log('Updated expense', 'Finance', $expense->id);

        return response()->json($expense);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();
        AuditLogService::log('Deleted expense', 'Finance', $expense->id);
        return response()->json(null, 204);
    }
}
