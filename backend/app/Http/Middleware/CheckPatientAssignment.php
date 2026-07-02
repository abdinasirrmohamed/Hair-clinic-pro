<?php

namespace App\Http\Middleware;

use App\Models\Patient;
use Closure;
use Illuminate\Http\Request;

class CheckPatientAssignment
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user->role !== 'Doctor') {
            return $next($request);
        }

        $patientId = $request->route('patient') ?? $request->input('patient_id');

        if ($patientId) {
            $patient = Patient::find($patientId);
            if ($patient && $patient->assigned_doctor_id !== $user->id) {
                return response()->json([
                    'message' => 'Access denied. This patient is not assigned to you.',
                ], 403);
            }
        }

        return $next($request);
    }
}
