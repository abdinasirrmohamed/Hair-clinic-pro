<?php

namespace Tests\Feature;

use App\Models\{Appointment, Doctor, DoctorBlockedDate, DoctorSchedule, Expense, LabRequest, LabTest, Patient, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ValidationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'Administrator']), 'sanctum');
    }

    private function doctor(): Doctor
    {
        return Doctor::create(['full_name' => 'Validation Doctor', 'specialization' => 'Dermatologist', 'phone' => '1', 'license_number' => uniqid('VAL-'), 'status' => 'Active']);
    }

    public function test_expense_updates_reject_invalid_money_and_receipts_without_changing_records(): void
    {
        $expense = Expense::create(['category' => 'Rent', 'expense_date' => today(), 'amount' => 50]);
        foreach ([-1, 0, '1.001', 100000000] as $amount) {
            $this->putJson('/api/expenses/'.$expense->id, ['amount' => $amount])->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        $this->putJson('/api/expenses/'.$expense->id, ['receipt' => UploadedFile::fake()->create('bad.html', 1, 'text/html')])->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->assertEquals(50, $expense->fresh()->amount);
        $this->putJson('/api/expenses/'.$expense->id, ['amount' => 12.50])->assertOk();
    }

    public function test_bad_filter_dates_and_pagination_return_validation_errors(): void
    {
        foreach ([['from' => 'garbage'], ['from' => '2026-02-30'], ['from' => '2026-09-10', 'to' => '2026-09-01'], ['per_page' => 0], ['per_page' => 101], ['page' => -1], ['search' => ['bad']]] as $query) {
            $this->getJson('/api/reports?'.http_build_query($query))->assertUnprocessable();
        }
        $this->getJson('/api/expenses?from=2026-09-01&to=2026-09-10')->assertOk();
    }

    public function test_profile_password_change_requires_current_password(): void
    {
        $this->putJson('/api/users/profile', ['full_name' => 'Name', 'password' => 'NewPassword123!'])
            ->assertUnprocessable()->assertJsonValidationErrors('old_password');
    }

    public function test_overlong_text_and_array_foreign_keys_are_rejected(): void
    {
        $this->postJson('/api/suppliers', ['company_name' => str_repeat('x', 151), 'phone' => '1'])->assertUnprocessable()->assertJsonValidationErrors('company_name');
        $this->postJson('/api/payments', ['patient_id' => [1], 'amount' => 10, 'payment_method' => 'Cash', 'payment_status' => 'Paid'])->assertUnprocessable()->assertJsonValidationErrors('patient_id');
    }

    public function test_schedule_batch_rejects_duplicates_foreign_ids_and_invalid_duration(): void
    {
        $doctor = $this->doctor();
        $row = ['day_of_week' => 'Saturday', 'shift' => 'Morning', 'start_time' => '08:00', 'end_time' => '12:00', 'slot_minutes' => 30, 'is_working' => true];
        $url = '/api/doctors/'.$doctor->id.'/schedules';
        $this->putJson($url, ['schedules' => [$row, $row]])->assertUnprocessable();
        $other = DoctorSchedule::create($row + ['doctor_id' => $this->doctor()->id]);
        $this->putJson($url, ['schedules' => [$row + ['id' => $other->id]]])->assertUnprocessable()->assertJsonValidationErrors('schedules.0.id');
        $this->postJson($url, array_replace($row, ['end_time' => '08:10']))->assertUnprocessable()->assertJsonValidationErrors('slot_minutes');
        $this->putJson($url, ['schedules' => [$row]])->assertOk();
        $this->putJson($url, ['schedules' => [$row]])->assertUnprocessable();
        $this->assertEquals(1, $doctor->schedules()->count());
    }

    public function test_doctor_profile_cannot_link_to_non_doctor_or_another_profiles_user(): void
    {
        $doctor = $this->doctor();
        $this->putJson('/api/doctors/'.$doctor->id, ['user_id' => User::factory()->create(['role' => 'Receptionist'])->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $otherUser = User::factory()->create(['role' => 'Doctor']);
        $this->doctor()->update(['user_id' => $otherUser->id]);
        $this->putJson('/api/doctors/'.$doctor->id, ['user_id' => $otherUser->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_medicine_edit_accepts_own_barcode_but_rejects_duplicate_and_overlong_barcode(): void
    {
        $medicine = \App\Models\Medicine::create(['medicine_name' => 'One', 'category' => 'General', 'barcode' => 'BAR-ONE', 'expiry_date' => now()->addYear()]);
        \App\Models\Medicine::create(['medicine_name' => 'Two', 'category' => 'General', 'barcode' => 'BAR-TWO', 'expiry_date' => now()->addYear()]);
        $url = '/api/medicines/'.$medicine->id;
        $this->putJson($url, ['barcode' => 'BAR-ONE', 'buying_price' => null])->assertOk();
        $this->putJson($url, ['barcode' => 'BAR-TWO'])->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->putJson($url, ['barcode' => str_repeat('x', 101)])->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->putJson($url, ['quantity' => -1, 'unit_price' => '1.001'])->assertUnprocessable()->assertJsonValidationErrors(['quantity', 'unit_price']);
    }

    public function test_lab_requests_cannot_use_another_patients_appointment_or_inactive_test(): void
    {
        $patient = Patient::create(['full_name' => 'One', 'phone' => '1', 'gender' => 'Female']);
        $other = Patient::create(['full_name' => 'Two', 'phone' => '2', 'gender' => 'Male']);
        $appointment = Appointment::create(['patient_id' => $other->id, 'doctor_id' => $this->doctor()->id, 'appointment_date' => today()->addDay(), 'appointment_time' => '09:00', 'reason' => 'Visit']);
        $test = LabTest::create(['test_name' => 'Lab Test', 'category' => 'Blood', 'price' => 10, 'status' => 'Active']);
        $payload = ['patient_id' => $patient->id, 'lab_test_id' => $test->id, 'request_date' => today()->toDateString()];
        $this->postJson('/api/lab/requests', $payload + ['appointment_id' => $appointment->id])->assertUnprocessable()->assertJsonValidationErrors('appointment_id');
        $request = $this->postJson('/api/lab/requests', $payload)->assertCreated()->json('id');
        $this->putJson('/api/lab/requests/'.$request, ['appointment_id' => $appointment->id])->assertUnprocessable();
        $test->update(['status' => 'Inactive']);
        $this->postJson('/api/lab/requests', $payload)->assertUnprocessable()->assertJsonValidationErrors('lab_test_id');
    }

    public function test_appointment_rescheduling_checks_time_slot_conflicts_and_blocked_dates(): void
    {
        $doctor = $this->doctor();
        $date = now()->next('Saturday')->toDateString();
        DoctorSchedule::create(['doctor_id' => $doctor->id, 'day_of_week' => 'Saturday', 'shift' => 'Morning', 'start_time' => '08:00', 'end_time' => '12:00', 'slot_minutes' => 30, 'is_working' => true]);
        $patient = Patient::create(['full_name' => 'Patient', 'phone' => '1', 'gender' => 'Male']);
        $data = ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'appointment_date' => $date, 'appointment_time' => '08:00', 'reason' => 'Visit', 'status' => 'Pending'];
        $appointment = Appointment::create($data);
        Appointment::create(array_replace($data, ['appointment_time' => '09:00']));
        $url = '/api/appointments/'.$appointment->id;
        foreach (['garbage', '25:00', '08:15', '09:00', '12:00'] as $time) $this->putJson($url, ['appointment_time' => $time])->assertUnprocessable();
        $this->putJson($url, ['appointment_time' => '09:30'])->assertOk();
        DoctorBlockedDate::create(['doctor_id' => $doctor->id, 'block_date' => $date, 'block_type' => 'Leave']);
        $this->putJson($url, ['appointment_time' => '10:00'])->assertUnprocessable();
        $this->getJson('/api/appointments/available-slots?doctor_id='.$doctor->id.'&appointment_date='.$date)->assertOk()->assertJsonCount(0, 'slots');
    }
}
