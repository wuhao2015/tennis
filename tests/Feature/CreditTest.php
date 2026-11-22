<?php

namespace Tests\Feature;

use App\Models\CreditRequest;
use App\Models\Studio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_credit_workflows_require_the_other_party_and_apply_once(): void
    {
        $this->seed(DemoSeeder::class);
        $studio = Studio::firstOrFail();
        $admin = $studio->coaches()->firstOrFail();
        $student = User::factory()->create();
        $studio->memberships()->create(['user_id' => $student->id, 'status' => 'approved']);
        foreach ([[$student, $admin, '2.25'], [$admin, $student, '-0.50']] as [$from,$to,$amount]) {
            $input = ['user_id' => $student->id, 'amount' => $amount, 'token' => (string) Str::uuid()];
            $this->actingAs($from)->post(route('credits.store', $studio), $input)->assertSessionHasNoErrors();
            $this->post(route('credits.store', $studio), $input)->assertSessionHasNoErrors();
            $url = route('credits.decide', [$studio, CreditRequest::latest('id')->firstOrFail()]);
            $decision = ['decision' => 'approved', 'token' => (string) Str::uuid()];
            $this->post($url, $decision)->assertForbidden();
            $this->actingAs($to)->post($url, $decision)->assertSessionHasNoErrors();
            $this->post($url, $decision)->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('credit_entries', 2);
        $this->assertDatabaseCount('credit_requests', 2);
        $this->assertDatabaseHas('credit_accounts', ['balance' => 1.75, 'frozen' => 0]);
        $this->get(route('studios.show', $studio))->assertOk()->assertSee('1.75');
    }
}
