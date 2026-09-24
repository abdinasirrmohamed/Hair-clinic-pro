<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\InventoryMovement;
use App\Models\Medicine;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TreatmentController extends Controller
{
    private function authorizePatient(int $patientId): void
    {
        if (auth()->user()->role === 'Doctor') {
            abort_unless(\App\Models\Patient::whereKey($patientId)
                ->where('assigned_doctor_id', auth()->user()->doctor?->id ?? 0)->exists(), 403, 'This patient is not assigned to you.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = Treatment::with('patient');
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($q) => $q->where('treatment_name', 'like', "%{$search}%")
                ->orWhereHas('patient', fn ($p) => $p->where('full_name', 'like', "%{$search}%")));
        }

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
            'patient_id' => 'integer|required|exists:patients,id',
            'treatment_name' => 'required|string|max:150',
            'treatment_date' => 'required|date',
            'treatment_stage' => ['required', Rule::in(['Pre-Treatment Evaluation', 'Surgery', 'Post-Treatment Review'])],
            'progress' => ['required', Rule::in(['Started', 'In Progress', 'Completed'])],
            'cost' => 'required|numeric|min:0|max:99999999.99',
            'grafts_planned' => 'nullable|integer|min:0|max:2147483647',
            'grafts_extracted' => 'nullable|integer|min:0|max:2147483647',
            'grafts_implanted' => 'nullable|integer|min:0|max:2147483647',
            'donor_area_status' => 'nullable|string|max:255',
            'recipient_area_status' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'usage_medicine_id' => 'integer|nullable|exists:medicines,id',
            'usage_quantity' => 'nullable|integer|min:1',
            'pre_op_photo' => 'nullable|image|max:3072',
            'post_op_photo' => 'nullable|image|max:3072',
        ]);

        $this->authorizePatient($validated['patient_id']);

        if ($request->hasFile('pre_op_photo')) {
            $validated['pre_op_photo'] = $request->file('pre_op_photo')->store('treatments', 'public');
        }
        if ($request->hasFile('post_op_photo')) {
            $validated['post_op_photo'] = $request->file('post_op_photo')->store('treatments', 'public');
        }

        DB::beginTransaction();
        try {
            $treatment = Treatment::create($validated);

            if ($request->usage_medicine_id && $request->usage_quantity) {
                $medicine = Medicine::findOrFail($request->usage_medicine_id);
                if ($medicine->quantity < $request->usage_quantity) {
                    throw new \Exception('Insufficient medicine stock.');
                }

                $medicine->decrement('quantity', $request->usage_quantity);

                InventoryMovement::create([
                    'transaction_number' => 'TRT-' . date('Ymd') . '-' . rand(1000, 9999),
                    'medicine_id' => $medicine->id,
                    'movement_type' => 'Treatment Consumption',
                    'quantity' => $request->usage_quantity,
                    'unit_cost' => $medicine->unit_price,
                    'total_cost' => $medicine->unit_price * $request->usage_quantity,
                    'reference_type' => 'Treatment',
                    'reference_id' => $treatment->id,
                    'issued_by' => auth()->id(),
                ]);
            }

            DB::commit();
            AuditLogService::log('Created treatment', 'Treatments', $treatment->id);
            return response()->json($treatment, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
        }
    }

    public function show(Treatment $treatment): JsonResponse
    {
        $this->authorizePatient($treatment->patient_id);
        $treatment->load(['patient', 'followups']);
        return response()->json($treatment);
    }

    public function update(Request $request, Treatment $treatment): JsonResponse
    {
        $this->authorizePatient($treatment->patient_id);
        $validated = $request->validate([
            'patient_id' => 'sometimes|required|integer|exists:patients,id',
            'treatment_name' => 'sometimes|required|string|max:150',
            'treatment_date' => 'sometimes|required|date',
            'treatment_stage' => [Rule::in(['Pre-Treatment Evaluation', 'Surgery', 'Post-Treatment Review'])],
            'progress' => [Rule::in(['Started', 'In Progress', 'Completed'])],
            'cost' => 'numeric|min:0|max:99999999.99',
            'grafts_planned' => 'nullable|integer|min:0|max:2147483647',
            'grafts_extracted' => 'nullable|integer|min:0|max:2147483647',
            'grafts_implanted' => 'nullable|integer|min:0|max:2147483647',
            'donor_area_status' => 'nullable|string|max:255',
            'recipient_area_status' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $this->authorizePatient($validated['patient_id'] ?? $treatment->patient_id);
        $treatment->update($validated);
        AuditLogService::log('Updated treatment', 'Treatments', $treatment->id);

        return response()->json($treatment);
    }

    public function destroy(Treatment $treatment): JsonResponse
    {
        $this->authorizePatient($treatment->patient_id);
        $treatment->delete();
        AuditLogService::log('Deleted treatment', 'Treatments', $treatment->id);
        return response()->json(null, 204);
    }
}
