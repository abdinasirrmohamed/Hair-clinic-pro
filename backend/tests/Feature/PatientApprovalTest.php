<?php

namespace Tests\Feature;

use App\Models\{Doctor, Medicine, Patient, Prescription, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $doctor;
    private Medicine $medicine;
    private array $registration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'Administrator']), 'sanctum');
        $this->doctor = Doctor::create(['full_name' => 'Approval Doctor', 'specialization' => 'General', 'phone' => '1', 'license_number' => 'APP-1']);
        $this->medicine = Medicine::create(['medicine_name' => 'Test Medicine', 'category' => 'General', 'quantity' => 20, 'unit_price' => 5, 'expiry_date' => now()->addYear()]);
        $this->registration = ['full_name' => 'Patient', 'phone' => '1', 'gender' => 'Female', 'date_of_birth' => '2000-01-01', 'address' => 'Mogadishu', 'assigned_doctor_id' => $this->doctor->id];
    }

    private function prescription(Patient $patient): array
    {
        return ['patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'medicines' => [
            ['medicine_id' => $this->medicine->id, 'quantity' => 2, 'frequency' => 'Daily', 'instructions' => 'After food'],
        ]];
    }

    public function test_registration_requires_all_three_fields_and_rejects_blank_values(): void
    {
        foreach (['date_of_birth', 'address', 'assigned_doctor_id'] as $field) {
            foreach ([null, '', '   '] as $value) {
                $this->postJson('/api/patients', array_replace($this->registration, [$field => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            }
            $data = $this->registration;
            unset($data[$field]);
            $this->postJson('/api/patients', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('patients', 0);
        $this->postJson('/api/patients', $this->registration)->assertCreated()->assertJsonPath('status', 'Pending');
    }

    public function test_registration_cannot_self_approve_or_use_invalid_doctor(): void
    {
        $this->postJson('/api/patients', $this->registration + ['status' => 'Approved'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson('/api/patients', array_replace($this->registration, ['assigned_doctor_id' => 99999]))->assertUnprocessable()->assertJsonValidationErrors('assigned_doctor_id');
        $this->postJson('/api/appointments', ['patient_mode' => 'new', 'new_full_name' => 'Bypass', 'new_phone' => '1', 'new_gender' => 'Male', 'doctor_id' => $this->doctor->id, 'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '09:00', 'reason' => 'Visit'])
            ->assertUnprocessable()->assertJsonValidationErrors(['new_date_of_birth', 'new_address']);
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_pending_patient_cannot_receive_prescription_or_medicine_edits(): void
    {
        $patient = Patient::create($this->registration);
        $payload = $this->prescription($patient);
        $this->postJson('/api/prescriptions', $payload + ['status' => 'Approved'])->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->assertDatabaseCount('prescriptions', 0);
        $rx = Prescription::create(['patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'prescription_number' => 'RX-OLD', 'prescription_date' => today(), 'status' => 'Pending']);
        $rx->medicines()->createMany($payload['medicines']);
        $payload['medicines'][0]['quantity'] = 10;
        $this->putJson('/api/prescriptions/'.$rx->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->assertDatabaseHas('prescription_medicines', ['prescription_id' => $rx->id, 'quantity' => 2]);
        $this->postJson('/api/pharmacy/dispense', ['prescription_id' => $rx->id])->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->postJson('/api/pharmacy/sales', ['patient_id' => $patient->id, 'payment_method' => 'Cash', 'discount_type' => 'None', 'discount_value' => 0, 'tax_percent' => 0, 'medicines' => [['medicine_id' => $this->medicine->id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->assertEquals(20, $this->medicine->fresh()->quantity);
        $this->postJson('/api/pharmacy/invoices', ['patient_id' => $patient->id, 'payment_method' => 'Cash', 'items' => [['medicine_id' => $this->medicine->id, 'quantity' => 1, 'unit_price' => 5]]])
            ->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->assertDatabaseCount('pharmacy_invoices', 0);
    }

    public function test_accepted_and_approved_patients_can_receive_and_edit_prescriptions(): void
    {
        foreach (['Accepted', 'Approved'] as $status) {
            $patient = Patient::create($this->registration + ['status' => $status]);
            $payload = $this->prescription($patient);
            $id = $this->postJson('/api/prescriptions', $payload)->assertCreated()->json('prescription.id');
            $payload['medicines'][0]['quantity'] = 3;
            $this->putJson('/api/prescriptions/'.$id, $payload)->assertOk();
            $this->assertDatabaseHas('prescription_medicines', ['prescription_id' => $id, 'quantity' => 3]);
        }
    }

    public function test_only_authorized_staff_can_approve_and_required_values_cannot_be_cleared(): void
    {
        $patient = Patient::create($this->registration);
        foreach (['date_of_birth', 'address', 'assigned_doctor_id'] as $field) {
            $this->putJson('/api/patients/'.$patient->id, [$field => null])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->putJson('/api/patients/'.$patient->id, ['status' => 'Approved'])->assertOk();
        $this->getJson('/api/bootstrap')->assertOk()->assertJsonPath('lookups.patients.0.status', 'Approved');
        $this->actingAs(User::factory()->create(['role' => 'Receptionist']), 'sanctum');
        $this->putJson('/api/patients/'.$patient->id, ['status' => 'Pending'])->assertForbidden();
        $this->assertSame('Approved', $patient->fresh()->status);
    }

    public function test_assigned_doctor_can_approve_but_another_doctor_cannot(): void
    {
        $patient = Patient::create($this->registration);
        $doctorUser = User::factory()->create(['role' => 'Doctor']);
        $this->doctor->update(['user_id' => $doctorUser->id]);
        $this->actingAs($doctorUser, 'sanctum');
        $this->getJson('/api/patients')->assertOk()->assertJsonPath('data.0.id', $patient->id);
        $this->postJson('/api/prescriptions', $this->prescription($patient))
            ->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->putJson('/api/patients/'.$patient->id, ['status' => 'Accepted'])->assertOk()->assertJsonPath('status', 'Accepted');
        $this->postJson('/api/prescriptions', $this->prescription($patient))->assertCreated()
            ->assertJsonPath('prescription.patient_id', $patient->id)
            ->assertJsonPath('prescription.doctor_id', $this->doctor->id);
        $this->actingAs(User::factory()->create(['role' => 'Doctor']), 'sanctum');
        $this->putJson('/api/patients/'.$patient->id, ['status' => 'Pending'])->assertForbidden();
    }

    public function test_prescription_cannot_be_reassigned_to_pending_patient(): void
    {
        $approved = Patient::create($this->registration + ['status' => 'Approved']);
        $pending = Patient::create($this->registration);
        $payload = $this->prescription($approved);
        $id = $this->postJson('/api/prescriptions', $payload)->assertCreated()->json('prescription.id');
        $payload['patient_id'] = $pending->id;
        $this->putJson('/api/prescriptions/'.$id, $payload)->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->assertDatabaseHas('prescriptions', ['id' => $id, 'patient_id' => $approved->id]);
    }
}
