<?php

namespace Tests\Feature;

use App\Models\CreditAccount;
use App\Models\Studio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_booking_freezes_credit_and_blocks_overlaps_across_studios(): void
    {
        $this->seed(DemoSeeder::class);
        $this->travelTo(now()->startOfHour());
        $student = User::factory()->create();
        foreach (Studio::orderBy('id')->get() as $index => $studio) {
            $start = now()->addHours(2)->addMinutes(30 * $index)->format('Y-m-d\TH:i');
            $coach = $studio->coaches()->first();
            $studio->memberships()->create(['user_id' => $student->id, 'status' => 'approved']);
            CreditAccount::create(['studio_id' => $studio->id, 'user_id' => $student->id, 'balance' => 5]);
            $this->actingAs($coach)->post(route('bookings.slot', $studio), ['starts_at' => $start])->assertSessionHasNoErrors();
            $response = $this->actingAs($student)->post(route('bookings.store', $studio), [
                'starts_at' => $start, 'coach_id' => $coach->id, 'token' => (string) Str::uuid(),
            ]);
            if ($index === 0) {
                $response->assertSessionHasNoErrors();
                $this->get(route('studios.show', $studio))->assertSee('待确认');
            } else {
                $response->assertSessionHasErrors('starts_at');
            }
        }
        $this->assertDatabaseCount('lessons', 1);
        $this->assertDatabaseHas('credit_accounts', ['frozen' => 1, 'balance' => 5]);
    }
}
