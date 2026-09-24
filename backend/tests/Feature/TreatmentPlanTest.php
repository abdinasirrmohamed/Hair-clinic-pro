<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_treatment_plan_can_be_created_edited_and_deleted(): void
    {
        $user = User::factory()->create([
            'role' => 'Administrator',
            'module_permissions' => ['dashboard', 'treatments', 'followups'],
        ]);
        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/treatments')->assertOk();
        $this->postJson('/api/treatments', [])->assertUnprocessable();
        $bootstrap = $this->getJson('/api/bootstrap')->assertOk();
        $this->assertContains('treatments', $bootstrap->json('permissions'));
        $patient = Patient::create(['full_name' => 'Plan Patient', 'phone' => '1', 'gender' => 'Female']);
        $id = $this->postJson('/api/treatments', [
            'patient_id' => $patient->id, 'treatment_name' => 'Hair restoration',
            'treatment_date' => '2026-09-24', 'treatment_stage' => 'Pre-Treatment Evaluation',
            'progress' => 'Started', 'cost' => 0, 'grafts_planned' => 1000,
        ])->assertCreated()->json('id');
        $this->putJson('/api/treatments/'.$id, [
            'treatment_date' => '2026-09-25', 'progress' => 'In Progress',
            'grafts_implanted' => 900, 'notes' => 'Review in one week',
        ])->assertOk();
        $this->assertDatabaseHas('treatments', ['id' => $id, 'treatment_date' => '2026-09-25', 'grafts_implanted' => 900]);
        $this->getJson('/api/treatments?search=restoration')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/treatments/'.$id)->assertOk()->assertJsonPath('patient.id', $patient->id);
        $this->deleteJson('/api/treatments/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('treatments', ['id' => $id]);
    }

    public function test_doctor_can_only_manage_assigned_patient_plans(): void
    {
        $user = User::factory()->create(['role' => 'Doctor']);
        $doctor = Doctor::create(['user_id' => $user->id, 'full_name' => 'Doctor', 'specialization' => 'General', 'phone' => '1', 'license_number' => 'TR-1']);
        $patient = Patient::create(['full_name' => 'Assigned', 'phone' => '1', 'gender' => 'Male', 'assigned_doctor_id' => $doctor->id]);
        $other = Patient::create(['full_name' => 'Other', 'phone' => '2', 'gender' => 'Female']);
        $payload = ['patient_id' => $patient->id, 'treatment_name' => 'Plan', 'treatment_date' => '2026-09-24', 'treatment_stage' => 'Surgery', 'progress' => 'Started', 'cost' => 0];
        $this->actingAs($user, 'sanctum');
        $id = $this->postJson('/api/treatments', $payload)->assertCreated()->json('id');
        $otherPlan = Treatment::create(array_replace($payload, ['patient_id' => $other->id]));
        $this->getJson('/api/treatments')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/treatments', array_replace($payload, ['patient_id' => $other->id]))->assertForbidden();
        $this->putJson('/api/treatments/'.$id, ['patient_id' => $other->id])->assertForbidden();
        $this->getJson('/api/treatments/'.$otherPlan->id)->assertForbidden();
        $this->putJson('/api/treatments/'.$otherPlan->id, ['notes' => 'Change'])->assertForbidden();
        $this->deleteJson('/api/treatments/'.$otherPlan->id)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'Receptionist']), 'sanctum');
        $this->getJson('/api/treatments')->assertForbidden();
    }

    public function test_followups_can_be_created_without_a_treatment_plan(): void
    {
        $user = User::factory()->create(['role' => 'Administrator']);
        $patient = Patient::create(['full_name' => 'Followup Patient', 'phone' => '0610000000', 'gender' => 'Female']);
        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/followups', [
            'patient_id' => $patient->id,
            'followup_date' => now()->addDay()->toDateString(),
            'status' => 'Scheduled',
        ])->assertCreated();
        $this->getJson('/api/followups')->assertOk()->assertJsonPath('data.0.patient.id', $patient->id);
    }
}
