<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $permissions = config('roles.module_permissions')[$user->role] ?? [];

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role,
                'status' => $user->status,
            ],
            'permissions' => $permissions,
            'lookups' => [
                'patients' => Patient::orderBy('full_name')->get(['id', 'full_name', 'phone']),
                'doctors' => Doctor::where('status', 'Active')->orderBy('full_name')->get(['id', 'full_name']),
                'medicines' => Medicine::orderBy('medicine_name')->get([
                    'id', 'medicine_name', 'quantity', 'unit_price', 'reorder_level',
                ]),
                'treatments' => Treatment::latest('treatment_date')->get(['id', 'patient_id', 'treatment_name']),
                'appointments' => Appointment::latest('appointment_date')->get(['id', 'patient_id', 'appointment_date']),
                'doctor_users' => User::where('role', 'Doctor')->where('status', 'Active')
                    ->orderBy('full_name')->get(['id', 'full_name']),
            ],
        ]);
    }
}
