<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Medicine;
use App\Models\NotificationLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClinicalWorkflowEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Administrator']);
    }

    public function test_duplicate_doctor_day_and_shift_is_rejected(): void
    {
        $doctor = $this->doctor();
        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'day_of_week' => 'Saturday',
            'shift' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'slot_minutes' => 30,
            'is_working' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/doctors/{$doctor->id}/schedules", [
                'day_of_week' => 'Saturday',
                'shift' => 'Morning',
                'start_time' => '09:00',
                'end_time' => '12:00',
                'slot_minutes' => 30,
                'is_working' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shift');
    }

    public function test_doctor_profile_name_is_taken_from_the_selected_user_account(): void
    {
        $doctorUser = User::factory()->create(['role' => 'Doctor', 'full_name' => 'Dr Account Name']);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/doctors', [
            'user_id' => $doctorUser->id,
            'full_name' => 'Duplicate Typed Name',
            'specialization' => 'Dermatologist',
            'phone' => '0612555555',
            'license_number' => 'NAME-SOURCE-1',
            'experience_years' => 4,
        ])->assertCreated()->assertJsonPath('full_name', 'Dr Account Name');

        $this->assertDatabaseHas('doctors', ['user_id' => $doctorUser->id, 'full_name' => 'Dr Account Name']);
    }

    public function test_receptionist_can_load_working_dates_without_doctor_management_permission(): void
    {
        $doctor = $this->doctor();
        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'day_of_week' => 'Saturday',
            'shift' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'slot_minutes' => 30,
            'is_working' => true,
        ]);
        $receptionist = User::factory()->create(['role' => 'Receptionist']);

        $this->actingAs($receptionist, 'sanctum')
            ->getJson("/api/appointments/doctors/{$doctor->id}/schedules")
            ->assertOk()
            ->assertJsonCount(1, 'schedules')
            ->assertJsonPath('schedules.0.day_of_week', 'Saturday');
    }

    public function test_partial_appointment_payment_is_calculated_and_notifications_are_idempotent(): void
    {
        $doctor = $this->doctor();
        $patient = Patient::create(['full_name' => 'Notification Patient', 'phone' => '0612345678', 'gender' => 'Male']);
        $date = now()->next('Saturday')->toDateString();
        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'day_of_week' => 'Saturday',
            'shift' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'slot_minutes' => 30,
            'is_working' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/appointments/book', [
            'patient_id' => $patient->id,
            'patient_name' => 'Notification Patient',
            'patient_phone' => '0612345678',
            'gender' => 'Male',
            'doctor_id' => $doctor->id,
            'appointment_date' => $date,
            'appointment_time' => '08:30',
            'payment_method' => 'Cash',
            'payment_status' => 'Partial Paid',
            'paid_amount' => 40,
        ])->assertCreated();

        $response->assertJsonPath('payment.total_amount', '100.00')
            ->assertJsonPath('payment.paid_amount', '40.00')
            ->assertJsonPath('payment.remaining_amount', '60.00')
            ->assertJsonPath('payment.payment_status', 'Partial Paid');

        $appointmentId = $response->json('appointment.id');
        $this->assertSame(1, NotificationLog::where('notification_type', 'appointment_confirmation')
            ->where('notifiable_id', $appointmentId)->count());
    }

    public function test_configured_sms_provider_receives_one_appointment_confirmation(): void
    {
        config([
            'services.sms.driver' => 'http',
            'services.sms.endpoint' => 'https://sms.test/messages',
            'services.sms.token' => 'test-token',
        ]);
        Http::fake(['https://sms.test/messages' => Http::response(['id' => 'SMS-1'])]);
        $doctor = $this->doctor();
        $patient = Patient::create(['full_name' => 'SMS Patient', 'phone' => '0612000000', 'gender' => 'Female']);
        $date = now()->next('Saturday')->toDateString();
        DoctorSchedule::create([
            'doctor_id' => $doctor->id, 'day_of_week' => 'Saturday', 'shift' => 'Morning',
            'start_time' => '08:00', 'end_time' => '12:00', 'slot_minutes' => 30, 'is_working' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/appointments/book', [
            'patient_id' => $patient->id,
            'patient_name' => $patient->full_name,
            'patient_phone' => $patient->phone,
            'gender' => $patient->gender,
            'doctor_id' => $doctor->id,
            'appointment_date' => $date,
            'appointment_time' => '09:00',
            'payment_method' => 'Cash',
            'payment_status' => 'Full Paid',
        ])->assertCreated();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request['message'], 'SMS Patient')
            && str_contains($request['message'], 'Workflow Doctor')
            && str_contains($request['message'], $date));
        $this->assertDatabaseCount('notification_logs', 2);
    }

    public function test_prescription_saves_and_returns_multiple_complete_medicine_rows(): void
    {
        $doctor = $this->doctor();
        $patient = Patient::create(['full_name' => 'Multi Medicine', 'phone' => '1', 'gender' => 'Female']);
        $first = $this->medicine('Medicine A');
        $second = $this->medicine('Medicine B');

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/prescriptions', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'medicines' => [
                ['medicine_id' => $first->id, 'quantity' => 2, 'frequency' => 'Twice daily', 'instructions' => 'After food'],
                ['medicine_id' => $second->id, 'quantity' => 1, 'frequency' => 'Once daily', 'instructions' => 'At night'],
            ],
        ])->assertCreated();

        $prescriptionId = $response->json('prescription.id');
        $this->assertDatabaseCount('prescription_medicines', 2);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/prescriptions/{$prescriptionId}")
            ->assertOk()
            ->assertJsonCount(2, 'medicines')
            ->assertJsonPath('medicines.0.frequency', 'Twice daily');
    }

    public function test_pharmacy_sale_only_dispenses_items_from_the_selected_patients_prescription(): void
    {
        $doctor = $this->doctor();
        $patient = Patient::create(['full_name' => 'Pharmacy Patient', 'phone' => '2', 'gender' => 'Male']);
        $medicine = $this->medicine('Prescription Sale Medicine');
        $prescription = $this->actingAs($this->admin, 'sanctum')->postJson('/api/prescriptions', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'medicines' => [[
                'medicine_id' => $medicine->id,
                'quantity' => 3,
                'frequency' => 'Three times daily',
                'instructions' => 'Take with water',
            ]],
        ])->assertCreated()->json('prescription');

        $lineId = $prescription['medicines'][0]['id'];
        $sale = $this->actingAs($this->admin, 'sanctum')->postJson('/api/pharmacy/sales', [
            'patient_id' => $patient->id,
            'prescription_id' => $prescription['id'],
            'payment_method' => 'Cash',
            'discount_type' => 'None',
            'discount_value' => 0,
            'tax_percent' => 0,
            'medicines' => [[
                'medicine_id' => $medicine->id,
                'prescription_medicine_id' => $lineId,
                'quantity' => 3,
            ]],
        ])->assertCreated();

        $sale->assertJsonPath('medicines.0.frequency', 'Three times daily')
            ->assertJsonPath('medicines.0.instructions', 'Take with water')
            ->assertJsonPath('remaining_balance', '0.00');
        $this->assertDatabaseHas('prescriptions', ['id' => $prescription['id'], 'status' => 'Dispensed']);
    }

    private function doctor(): Doctor
    {
        return Doctor::create([
            'full_name' => 'Workflow Doctor',
            'specialization' => 'Dermatologist',
            'phone' => '0611111111',
            'license_number' => 'WF-'.uniqid(),
            'experience_years' => 5,
            'consultation_fee' => 100,
            'status' => 'Active',
        ]);
    }

    private function medicine(string $name): Medicine
    {
        return Medicine::create([
            'medicine_name' => $name,
            'category' => 'General',
            'quantity' => 100,
            'unit_price' => 5,
            'expiry_date' => now()->addYear()->toDateString(),
        ]);
    }
}
