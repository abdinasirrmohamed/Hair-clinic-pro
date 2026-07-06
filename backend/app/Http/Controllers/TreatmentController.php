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
    public function index(Request $request): JsonResponse
    {
        $query = Treatment::with('patient');

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
            'treatment_name' => 'required|string',
            'treatment_date' => 'required|date',
            'treatment_stage' => ['required', Rule::in(['Pre-Treatment Evaluation', 'Surgery', 'Post-Treatment Review'])],
            'progress' => ['required', Rule::in(['Started', 'In Progress', 'Completed'])],
            'cost' => 'required|numeric',
            'grafts_planned' => 'nullable|integer',
            'grafts_extracted' => 'nullable|integer',
            'grafts_implanted' => 'nullable|integer',
            'donor_area_status' => 'nullable|string',
            'recipient_area_status' => 'nullable|string',
            'notes' => 'nullable|string',
            'usage_medicine_id' => 'nullable|exists:medicines,id',
            'usage_quantity' => 'nullable|integer|min:1',
            'pre_op_photo' => 'nullable|image|max:3072',
            'post_op_photo' => 'nullable|image|max:3072',
        ]);

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
        $treatment->load(['patient', 'followups']);
        return response()->json($treatment);
    }

    public function update(Request $request, Treatment $treatment): JsonResponse
    {
        $validated = $request->validate([
            'treatment_name' => 'string',
            'treatment_stage' => [Rule::in(['Pre-Treatment Evaluation', 'Surgery', 'Post-Treatment Review'])],
            'progress' => [Rule::in(['Started', 'In Progress', 'Completed'])],
            'cost' => 'numeric',
            'notes' => 'nullable|string',
        ]);

        $treatment->update($validated);
        AuditLogService::log('Updated treatment', 'Treatments', $treatment->id);

        return response()->json($treatment);
    }

    public function destroy(Treatment $treatment): JsonResponse
    {
        $treatment->delete();
        AuditLogService::log('Deleted treatment', 'Treatments', $treatment->id);
        return response()->json(null, 204);
    }
}
