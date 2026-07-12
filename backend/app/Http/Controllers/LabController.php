<?php

namespace App\Http\Controllers;

use App\Models\LabRequest;
use App\Models\LabTest;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LabController extends Controller
{
    public function tests(Request $request): JsonResponse
    {
        $query = LabTest::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('test_name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }

        return response()->json($query->orderBy('test_name')->paginate($request->integer('per_page', 15)));
    }

    public function storeTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'test_name' => 'required|string|max:150',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'sample_type' => 'nullable|string|max:80',
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'description' => 'nullable|string',
        ]);

        $validated['status'] = $validated['status'] ?? 'Active';
        $test = LabTest::create($validated);
        AuditLogService::log('Created lab test', 'Laboratory', $test->id);

        return response()->json($test, 201);
    }

    public function updateTest(Request $request, LabTest $test): JsonResponse
    {
        $validated = $request->validate([
            'test_name' => 'string|max:150',
            'category' => 'string|max:100',
            'price' => 'numeric|min:0',
            'sample_type' => 'nullable|string|max:80',
            'status' => [Rule::in(['Active', 'Inactive'])],
            'description' => 'nullable|string',
        ]);

        $test->update($validated);
        AuditLogService::log('Updated lab test', 'Laboratory', $test->id);

        return response()->json($test);
    }

    public function destroyTest(LabTest $test): JsonResponse
    {
        $test->delete();
        AuditLogService::log('Deleted lab test', 'Laboratory', $test->id);

        return response()->json(null, 204);
    }

    public function requests(Request $request): JsonResponse
    {
        $query = LabRequest::with(['patient', 'appointment', 'doctor', 'test', 'creator']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        return response()->json($query->latest('request_date')->paginate($request->integer('per_page', 15)));
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'doctor_id' => 'nullable|exists:doctors,id',
            'lab_test_id' => 'required|exists:lab_tests,id',
            'request_date' => 'required|date|after_or_equal:today',
            'status' => ['nullable', Rule::in(['Requested', 'In Progress', 'Completed', 'Cancelled'])],
            'result' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['request_number'] = 'LAB-' . date('Ymd') . '-' . random_int(1000, 9999);
        $validated['status'] = $validated['status'] ?? 'Requested';
        $validated['created_by'] = auth()->id();
        $validated['completed_at'] = $validated['status'] === 'Completed' ? now() : null;

        DB::beginTransaction();
        try {
            $requestModel = LabRequest::create($validated);
            DB::commit();
            AuditLogService::log('Created lab request', 'Laboratory', $requestModel->id);

            return response()->json($requestModel->load(['patient', 'appointment', 'doctor', 'test', 'creator']), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error creating lab request: ' . $e->getMessage()], 422);
        }
    }

    public function updateRequest(Request $request, LabRequest $labRequest): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => 'nullable|exists:appointments,id',
            'doctor_id' => 'nullable|exists:doctors,id',
            'lab_test_id' => 'exists:lab_tests,id',
            'request_date' => 'date',
            'status' => [Rule::in(['Requested', 'In Progress', 'Completed', 'Cancelled'])],
            'result' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if (($validated['status'] ?? null) === 'Completed') {
            $validated['completed_at'] = now();
        }

        $labRequest->update($validated);
        AuditLogService::log('Updated lab request', 'Laboratory', $labRequest->id);

        return response()->json($labRequest->load(['patient', 'appointment', 'doctor', 'test', 'creator']));
    }
}
