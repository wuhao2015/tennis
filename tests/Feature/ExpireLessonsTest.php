<?php

namespace Tests\Feature;

use App\Models\CreditAccount;
use App\Models\Lesson;
use App\Models\Studio;
use App\Models\User;
use App\Services\ScheduleService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireLessonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiry_releases_private_and_group_holds_once_after_the_inclusive_deadline(): void
    {
        $this->seed(DemoSeeder::class);
        $this->travelTo(now()->startOfHour());
        $studio = Studio::firstOrFail();
        $start = now()->toImmutable()->addHours(2);
        foreach (['pending', 'recruiting'] as $index => $status) {
            $lesson = Lesson::create([
                'studio_id' => $studio->id, 'type' => $index ? 'group' : 'private', 'status' => $status, 'starts_at' => $start,
                'minimum' => $index ? 2 : null, 'maximum' => $index ? 3 : null,
            ]);
            $lesson->coaches()->attach($studio->coaches[$index]);
            $student = User::factory()->create();
            $amount = $index ? '1.25' : '1.00';
            $lesson->enrollments()->create(['user_id' => $student->id, 'amount' => $amount, 'status' => 'frozen']);
            CreditAccount::create(['studio_id' => $studio->id, 'user_id' => $student->id, 'balance' => 2, 'frozen' => $amount]);
        }
        $this->artisan('lessons:expire')->assertSuccessful();
        $this->assertDatabaseCount('credit_entries', 0);
        $this->travel(1)->seconds();
        $this->artisan('lessons:expire')->assertSuccessful();
        $this->artisan('lessons:expire')->assertSuccessful();
        $this->assertEquals(2, Lesson::where('status', 'expired')->count());
        $this->assertEquals(2, CreditAccount::where('frozen', 0)->where('balance', 2)->count());
        $this->assertFalse(app(ScheduleService::class)->peopleBusy($studio->coaches->modelKeys(), $start));
        $this->assertTrue(app(ScheduleService::class)->courtAvailable($studio, $start));
        $this->assertDatabaseCount('credit_entries', 2);
        $this->assertDatabaseCount('operations', 2);
    }
}
