<?php

namespace App\Http\Controllers;

use App\Models\PharmacyCustomer;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class PharmacyCustomerController extends Controller
{
    public function index(Request $request)
    {
        return PharmacyCustomer::withCount('sales')
            ->withSum(['sales as outstanding_balance' => fn ($q) => $q->where('status', '!=', 'Returned')], 'remaining_balance')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('full_name', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%')))
            ->orderBy('full_name')->paginate(min(100, max(1, $request->integer('per_page', 25))));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'full_name' => 'required|string|max:150', 'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);
    }

    public function store(Request $request)
    {
        $customer = PharmacyCustomer::create($this->validated($request));
        AuditLogService::log('Created customer', 'Pharmacy', $customer->id);
        return response()->json($customer, 201);
    }

    public function show(PharmacyCustomer $customer)
    {
        return $customer->load(['sales' => fn ($q) => $q->latest()->with('medicines.medicine')]);
    }

    public function update(Request $request, PharmacyCustomer $customer)
    {
        $customer->update($this->validated($request));
        AuditLogService::log('Updated customer', 'Pharmacy', $customer->id);
        return $customer;
    }

    public function destroy(PharmacyCustomer $customer)
    {
        abort_if($customer->sales()->exists() || \App\Models\Prescription::where('customer_id', $customer->id)->exists(), 422, 'Customers with sales or prescriptions must be retained.');
        $customer->delete();
        return response()->noContent();
    }
}
