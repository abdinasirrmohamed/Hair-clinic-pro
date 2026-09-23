<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_treatment_routes_and_bootstrap_entries_are_removed(): void
    {
        $user = User::factory()->create([
            'role' => 'Administrator',
            'module_permissions' => ['dashboard', 'treatments', 'followups'],
        ]);
        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/treatments')->assertNotFound();
        $this->postJson('/api/treatments', [])->assertNotFound();
        $bootstrap = $this->getJson('/api/bootstrap')->assertOk();
        $this->assertNotContains('treatments', $bootstrap->json('permissions'));
        $this->assertArrayNotHasKey('treatments', $bootstrap->json('lookups'));
        $this->assertArrayNotHasKey('total_treatments', $this->getJson('/api/dashboard')->assertOk()->json());
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
