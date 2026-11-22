<?php

namespace Tests\Feature;

use App\Models\CreditAccount;
use App\Models\Lesson;
use App\Models\Studio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_confirmed_private_lessons_claim_a_court_and_cancellation_requires_both_parties(): void
    {
        $this->seed(DemoSeeder::class);
        $this->travelTo(now()->startOfHour());
        $studio = Studio::firstOrFail();
        $start = now()->addHours(168)->format('Y-m-d\TH:i');
        foreach ($studio->coaches as $coach) {
            $student = User::factory()->create();
            $studio->memberships()->create(['user_id' => $student->id, 'status' => 'approved']);
            CreditAccount::create(['user_id' => $student->id, 'studio_id' => $studio->id, 'balance' => 1]);
            $this->actingAs($coach)->post(route('bookings.slot', $studio), ['starts_at' => $start]);
            $this->actingAs($student)->post(route('bookings.store', $studio), ['starts_at' => $start, 'coach_id' => $coach->id, 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        }
        [$first,$second] = Lesson::orderBy('id')->get();
        foreach ([$first, $second] as $lesson) {
            $this->actingAs($lesson->coaches->first())->post(route('bookings.act', $lesson), ['action' => 'confirm', 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        }
        $this->assertSame('confirmed', $first->fresh()->status);
        $this->assertSame('no_court', $second->fresh()->status);
        $this->assertDatabaseHas('credit_accounts', ['user_id' => $second->enrollments->first()->user_id, 'balance' => 1, 'frozen' => 0]);
        $this->travel(8)->days();
        foreach ([$first->coaches->first(), $first->coaches->first(), $first->enrollments->first()->user] as $actor) {
            $this->actingAs($actor)->post(route('bookings.act', $first), ['action' => 'cancel', 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
            if ($actor->is($first->coaches->first())) {
                $this->assertSame('confirmed', $first->fresh()->status);
            }
        }
        $this->assertDatabaseHas('credit_accounts', ['user_id' => $first->enrollments->first()->user_id, 'balance' => 1, 'frozen' => 0]);
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertDatabaseCount('credit_entries', 5);
    }

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
