<?php

namespace Tests\Feature;

use App\Models\Studio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_and_management_are_scoped_to_each_studio(): void
    {
        $this->seed(DemoSeeder::class);
        [$first, $second] = Studio::orderBy('id')->get();
        $this->get('/')->assertSee($first->name)->assertSee($second->name);
        $student = User::factory()->create();
        foreach ([$first, $second] as $studio) {
            $this->actingAs($student)->post(route('studios.join', $studio))->assertRedirect();
            $application = $studio->memberships()->where('user_id', $student->id)->firstOrFail();
            $this->actingAs($studio->coaches()->first())->post(route('memberships.decide', [$studio, $application]), ['decision' => 'approved'])->assertRedirect();
            $this->post(route('studios.courts', $studio), ['courts' => 3])->assertRedirect();
            $this->assertSame('approved', $application->fresh()->status);
            $this->assertEquals(3, $studio->fresh()->courts);
        }
        $this->post(route('studios.courts', $first), ['courts' => 9])->assertForbidden();
    }
}
