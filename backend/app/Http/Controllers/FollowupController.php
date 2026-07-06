<?php

namespace App\Http\Controllers;

use App\Models\Followup;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FollowupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Followup::with(['patient', 'treatment']);

        if (auth()->user()->role === 'Doctor') {
            $query->whereHas('patient', function ($q) {
                $q->where('assigned_doctor_id', auth()->user()->doctor?->id ?? 0);
            });
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'treatment_id' => 'nullable|exists:treatments,id',
            'followup_date' => 'required|date',
            'status' => ['required', Rule::in(['Scheduled', 'Done', 'Missed'])],
            'result' => 'nullable|string',
        ]);

        $followup = Followup::create($validated);
        AuditLogService::log('Created followup', 'Followups', $followup->id);

        return response()->json($followup, 201);
    }

    public function show(Followup $followup): JsonResponse
    {
        $followup->load(['patient', 'treatment']);
        return response()->json($followup);
    }

    public function update(Request $request, Followup $followup): JsonResponse
    {
        $validated = $request->validate([
            'followup_date' => 'date',
            'status' => [Rule::in(['Scheduled', 'Done', 'Missed'])],
            'result' => 'nullable|string',
        ]);

        $followup->update($validated);
        AuditLogService::log('Updated followup', 'Followups', $followup->id);

        return response()->json($followup);
    }

    public function destroy(Followup $followup): JsonResponse
    {
        $followup->delete();
        AuditLogService::log('Deleted followup', 'Followups', $followup->id);
        return response()->json(null, 204);
    }
}
