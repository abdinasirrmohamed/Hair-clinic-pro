<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\Treatment;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\PharmacySale;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;
        $data = [];

        if ($role === 'Administrator') {
            $data['total_users'] = User::count();
            $data['total_doctors'] = Doctor::count();
            $data['total_patients'] = Patient::count();
            $data['total_appointments'] = Appointment::count();
            $data['total_treatments'] = Treatment::count();
            $data['total_inventory_items'] = InventoryItem::count();
            
            $data['revenue_today'] = Payment::whereIn('payment_status', ['Paid', 'Partial'])->whereDate('created_at', today())->sum('amount') +
                                     PharmacySale::where('status', '!=', 'Returned')->whereDate('created_at', today())->sum('total_amount');
            
            $data['expenses_month'] = Expense::whereMonth('expense_date', now()->month)->sum('amount');
            $data['outstanding_balances'] = Payment::whereIn('payment_status', ['Partial', 'Outstanding'])->sum('amount');
            $data['low_stock_items'] = Medicine::whereColumn('quantity', '<=', 'reorder_level')->count();
            $data['expired_medicines'] = Medicine::whereDate('expiry_date', '<=', today())->count();
        } elseif ($role === 'Receptionist') {
            $data['total_patients'] = Patient::count();
            $data['today_appointments'] = Appointment::whereDate('appointment_date', today())->count();
            $data['upcoming_appointments'] = Appointment::whereDate('appointment_date', '>=', today())->count();
            $data['payments_collected'] = Payment::whereIn('payment_status', ['Paid', 'Partial'])->sum('amount');
            $data['unpaid_appointments'] = Payment::whereIn('payment_status', ['Partial', 'Outstanding'])->count();
        } elseif ($role === 'Doctor') {
            $doctor = Doctor::where('user_id', $user->id)->first();
            if ($doctor) {
                $data['assigned_patients'] = Patient::where('assigned_doctor_id', $doctor->id)->count();
                $data['today_consultations'] = Appointment::where('doctor_id', $doctor->id)->whereDate('appointment_date', today())->count();
                $data['pending_prescriptions'] = Prescription::where('doctor_id', $doctor->id)->where('status', 'Pending')->count();
                $data['upcoming_appointments'] = Appointment::where('doctor_id', $doctor->id)->whereDate('appointment_date', '>=', today())->count();
            }
        } elseif ($role === 'Inventory Officer') {
            $data['total_inventory_items'] = InventoryItem::count();
            $data['total_medicines'] = Medicine::count();
            $data['low_stock_items'] = Medicine::whereColumn('quantity', '<=', 'reorder_level')->count();
            $data['expired_medicines'] = Medicine::whereDate('expiry_date', '<=', today())->count();
            $data['stock_value'] = Medicine::selectRaw('SUM(quantity * buying_price) total')->value('total') ?? 0;
        } elseif ($role === 'Pharmacy User') {
            $data['total_medicines'] = Medicine::count();
            $data['pharmacy_sales_today'] = PharmacySale::whereDate('created_at', today())->count();
            $data['pending_prescriptions'] = Prescription::where('status', 'Pending')->count();
            $data['low_stock_items'] = Medicine::whereColumn('quantity', '<=', 'reorder_level')->count();
            $data['expired_medicines'] = Medicine::whereDate('expiry_date', '<=', today())->count();
            $data['pharmacy_revenue_today'] = PharmacySale::where('status', '!=', 'Returned')->whereDate('created_at', today())->sum('total_amount');
        }

        return response()->json($data);
    }

    public function metrics(Request $request): JsonResponse
    {
        return $this->index($request);
    }
}
