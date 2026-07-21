<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\PrescriptionMedicine;
use App\Models\Doctor;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrescriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Prescription::with(['patient', 'doctor', 'medicines.medicine'])->withCount('medicines');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('prescription_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($patientQuery) => $patientQuery->where('full_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('prescription_date', $request->date);
        }

        return response()->json($query->latest('prescription_date')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'required|exists:doctors,id',
            'medicines' => 'required|array|min:1',
            'medicines.*.medicine_id' => 'required|distinct|exists:medicines,id',
            'medicines.*.quantity' => 'required|integer|min:1',
            'medicines.*.frequency' => 'required|string|max:100',
            'medicines.*.instructions' => 'required|string|max:255',
        ]);

        if (auth()->user()->role === 'Doctor') {
            $validated['doctor_id'] = Doctor::where('user_id', auth()->id())->value('id');
        }

        DB::beginTransaction();
        try {
            $prescription = Prescription::create([
                'prescription_number' => 'RX-' . date('Ymd') . '-' . rand(1000, 9999),
                'patient_id' => $validated['patient_id'],
                'doctor_id' => $validated['doctor_id'],
                'prescription_date' => now(),
                'status' => 'Pending',
            ]);

            foreach ($validated['medicines'] as $med) {
                PrescriptionMedicine::create([
                    'prescription_id' => $prescription->id,
                    'medicine_id' => $med['medicine_id'],
                    'quantity' => $med['quantity'],
                    'frequency' => $med['frequency'],
                    'instructions' => $med['instructions'],
                ]);
            }

            DB::commit();
            AuditLogService::log('Created prescription', 'Prescriptions', $prescription->id);

            return response()->json([
                'message' => 'Prescription created successfully.',
                'prescription' => $prescription->load(['patient', 'doctor', 'medicines.medicine']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show(Prescription $prescription): JsonResponse
    {
        $prescription->load(['patient', 'doctor', 'medicines.medicine']);
        return response()->json($prescription);
    }

    public function update(Request $request, Prescription $prescription): JsonResponse
    {
        abort_if(in_array($prescription->status, ['Dispensed', 'Completed'], true), 422, 'A dispensed prescription cannot be edited.');
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'required|exists:doctors,id',
            'medicines' => 'required|array|min:1',
            'medicines.*.medicine_id' => 'required|distinct|exists:medicines,id',
            'medicines.*.quantity' => 'required|integer|min:1',
            'medicines.*.frequency' => 'required|string|max:100',
            'medicines.*.instructions' => 'required|string|max:255',
        ]);
        if (auth()->user()->role === 'Doctor') {
            $validated['doctor_id'] = Doctor::where('user_id', auth()->id())->value('id');
        }

        DB::transaction(function () use ($prescription, $validated) {
            $prescription->update([
                'patient_id' => $validated['patient_id'],
                'doctor_id' => $validated['doctor_id'],
            ]);
            $prescription->medicines()->delete();
            $prescription->medicines()->createMany($validated['medicines']);
        });
        AuditLogService::log('Updated prescription', 'Prescriptions', $prescription->id);
        return response()->json([
            'message' => 'Prescription updated successfully.',
            'prescription' => $prescription->load(['patient', 'doctor', 'medicines.medicine']),
        ]);
    }
}
