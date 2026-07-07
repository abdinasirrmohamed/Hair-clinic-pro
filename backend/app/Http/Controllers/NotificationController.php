<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $items = [];

        $lowStock = Medicine::whereColumn('quantity', '<=', 'reorder_level')->limit(8)->get();
        foreach ($lowStock as $medicine) {
            $items[] = [
                'type' => 'Low Stock',
                'severity' => 'warning',
                'title' => $medicine->medicine_name,
                'message' => "{$medicine->quantity} left. Reorder level is {$medicine->reorder_level}.",
                'module' => 'Inventory',
            ];
        }

        $expired = Medicine::whereDate('expiry_date', '<=', today())->limit(8)->get();
        foreach ($expired as $medicine) {
            $items[] = [
                'type' => 'Expired Medicine',
                'severity' => 'danger',
                'title' => $medicine->medicine_name,
                'message' => "Expired on {$medicine->expiry_date}.",
                'module' => 'Pharmacy',
            ];
        }

        $appointments = Appointment::with(['patient', 'doctor'])
            ->whereDate('appointment_date', today())
            ->whereIn('status', ['Pending', 'Approved'])
            ->orderBy('appointment_time')
            ->limit(10)
            ->get();
        foreach ($appointments as $appointment) {
            $items[] = [
                'type' => 'Today Appointment',
                'severity' => 'info',
                'title' => $appointment->patient?->full_name,
                'message' => "{$appointment->appointment_time} with {$appointment->doctor?->full_name}.",
                'module' => 'Appointments',
            ];
        }

        $unpaid = Payment::with('patient')->whereIn('payment_status', ['Partial', 'Outstanding'])->limit(10)->get();
        foreach ($unpaid as $payment) {
            $items[] = [
                'type' => 'Unpaid Balance',
                'severity' => 'warning',
                'title' => $payment->patient?->full_name,
                'message' => "{$payment->payment_status}: {$payment->amount}.",
                'module' => 'Payments',
            ];
        }

        $pendingPrescriptions = Prescription::with('patient')->where('status', 'Pending')->limit(8)->get();
        foreach ($pendingPrescriptions as $prescription) {
            $items[] = [
                'type' => 'Pending Prescription',
                'severity' => 'info',
                'title' => $prescription->prescription_number,
                'message' => $prescription->patient?->full_name ?? 'Patient pending',
                'module' => 'Pharmacy',
            ];
        }

        return response()->json([
            'count' => count($items),
            'items' => $items,
        ]);
    }
}
