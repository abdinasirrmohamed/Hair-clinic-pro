<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DoctorScheduleController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DoctorAppointmentController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\FollowupController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\PharmacySaleController;
use App\Http\Controllers\PharmacyInvoiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SystemController;

// Public auth routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/pharmacy-login', [AuthController::class, 'pharmacyLogin']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/bootstrap', [SystemController::class, 'bootstrap']);

    // User profile (any authenticated user)
    Route::get('/users/profile', [UserController::class, 'profile']);
    Route::put('/users/profile', [UserController::class, 'updateProfile']);

    // Users management (Admin only)
    Route::middleware('module:users')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // Doctors
    Route::middleware('module:doctors')->group(function () {
        Route::apiResource('doctors', DoctorController::class);
        Route::get('doctors/{doctor}/schedules', [DoctorScheduleController::class, 'index']);
        Route::put('doctors/{doctor}/schedules', [DoctorScheduleController::class, 'update']);
        Route::get('doctors/{doctor}/blocked-dates', [DoctorScheduleController::class, 'blockedDates']);
        Route::post('doctors/{doctor}/blocked-dates', [DoctorScheduleController::class, 'addBlockedDate']);
        Route::delete('doctors/{doctor}/blocked-dates/{blockedDate}', [DoctorScheduleController::class, 'removeBlockedDate']);
    });

    // Patients
    Route::middleware('module:patients')->apiResource('patients', PatientController::class);

    // Appointments
    Route::middleware('module:appointments')->group(function () {
        Route::get('appointments/reminders', [AppointmentController::class, 'reminders']);
        Route::apiResource('appointments', AppointmentController::class);
    });

    // Doctor's own appointments
    Route::middleware('module:doctor_appointments')->prefix('doctor')->group(function () {
        Route::get('appointments', [DoctorAppointmentController::class, 'index']);
        Route::post('appointments', [DoctorAppointmentController::class, 'store']);
        Route::put('appointments/{appointment}', [DoctorAppointmentController::class, 'update']);
        Route::patch('appointments/{appointment}/status', [DoctorAppointmentController::class, 'updateStatus']);
        Route::patch('appointments/{appointment}/cancel', [DoctorAppointmentController::class, 'cancel']);
    });

    // Treatments
    Route::middleware('module:treatments')->apiResource('treatments', TreatmentController::class);

    // Followups
    Route::middleware('module:followups')->apiResource('followups', FollowupController::class);

    // Prescriptions
    Route::middleware('module:prescriptions')->group(function () {
        Route::apiResource('prescriptions', PrescriptionController::class)->only(['index', 'store', 'show']);
    });

    // Payments
    Route::middleware('module:payments')->group(function () {
        Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt']);
    });

    // Finance (Expenses)
    Route::middleware('module:finance')->apiResource('expenses', ExpenseController::class);

    // Inventory
    Route::middleware('module:inventory')->group(function () {
        Route::apiResource('inventory/items', InventoryItemController::class);
        Route::get('medicines/search', [MedicineController::class, 'search']);
        Route::apiResource('medicines', MedicineController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::get('inventory/movements', [InventoryMovementController::class, 'index']);
        Route::post('inventory/stock-in', [InventoryMovementController::class, 'stockIn']);
        Route::post('inventory/stock-out', [InventoryMovementController::class, 'stockOut']);
    });

    // Pharmacy
    Route::middleware('module:pharmacy')->prefix('pharmacy')->group(function () {
        Route::get('sales', [PharmacySaleController::class, 'index']);
        Route::post('sales', [PharmacySaleController::class, 'store']);
        Route::get('sales/{sale}', [PharmacySaleController::class, 'show']);
        Route::get('sales/{sale}/receipt', [PharmacySaleController::class, 'receipt']);
        Route::post('sales/{sale}/return', [PharmacySaleController::class, 'returnSale']);
        Route::post('dispense', [PharmacySaleController::class, 'dispense']);
        Route::apiResource('invoices', PharmacyInvoiceController::class)->only(['index', 'store', 'show']);
    });

    // Dashboard
    Route::middleware('module:dashboard')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('dashboard/metrics', [DashboardController::class, 'metrics']);
    });

    // Reports
    Route::middleware('module:reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index']);
        Route::get('reports/export', [ReportController::class, 'export']);
        Route::get('reports/finance', [ReportController::class, 'finance']);
    });

    // Audit Logs
    Route::middleware('module:audit_logs')->get('audit-logs', [AuditLogController::class, 'index']);
});
