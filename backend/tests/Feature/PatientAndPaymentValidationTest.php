<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientAndPaymentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Administrator']);
    }

    public function test_patient_age_is_calculated_from_date_of_birth_and_cannot_be_overridden(): void
    {
        $dateOfBirth = now()->subYears(30)->subDay()->toDateString();

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/patients', [
            'full_name' => 'Calculated Age',
            'phone' => '0612345678',
            'gender' => 'Female',
            'date_of_birth' => $dateOfBirth,
            'age' => 7,
        ])->assertCreated();

        $response->assertJsonPath('age', 30);
        $this->assertDatabaseHas('patients', ['full_name' => 'Calculated Age', 'age' => 30]);
    }

    public function test_future_patient_date_of_birth_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/patients', [
            'full_name' => 'Future Patient',
            'phone' => '0612345678',
            'gender' => 'Male',
            'date_of_birth' => now()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');
    }

    public function test_doctor_experience_rejects_decimal_values(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/doctors', [
            'full_name' => 'Doctor Integer',
            'specialization' => 'Dermatologist',
            'phone' => '0612345678',
            'license_number' => 'LIC-INT-1',
            'experience_years' => 5.5,
        ])->assertUnprocessable()->assertJsonValidationErrors('experience_years');
    }

    public function test_cash_payment_is_stored_with_paid_timestamp_and_receipt(): void
    {
        $patient = Patient::create([
            'full_name' => 'Payment Patient',
            'phone' => '0612345678',
            'gender' => 'Male',
            'age' => 25,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/payments', [
            'patient_id' => $patient->id,
            'amount' => 25,
            'payment_method' => 'Cash',
            'payment_status' => 'Paid',
        ])->assertCreated()->assertJsonPath('message', 'Payment recorded successfully.');

        $paymentId = $response->json('payment.id');
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'patient_id' => $patient->id,
            'amount' => 25,
            'payment_status' => 'Paid',
        ]);
        $this->assertNotNull($response->json('payment.paid_at'));
        $this->assertDatabaseHas('receipts', ['payment_id' => $paymentId]);
    }

    public function test_payment_rejects_an_appointment_owned_by_another_patient(): void
    {
        $doctor = Doctor::create([
            'full_name' => 'Payment Doctor',
            'specialization' => 'Dermatologist',
            'phone' => '0611111111',
            'license_number' => 'LIC-PAY-1',
            'experience_years' => 5,
            'status' => 'Active',
        ]);
        $owner = Patient::create(['full_name' => 'Owner', 'phone' => '1', 'gender' => 'Male']);
        $other = Patient::create(['full_name' => 'Other', 'phone' => '2', 'gender' => 'Female']);
        $appointment = Appointment::create([
            'patient_id' => $owner->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '09:00',
            'reason' => 'Consultation',
            'status' => 'Pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/payments', [
            'patient_id' => $other->id,
            'appointment_id' => $appointment->id,
            'amount' => 25,
            'payment_method' => 'Cash',
            'payment_status' => 'Paid',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
    }
}
