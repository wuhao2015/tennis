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

class GroupLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_reserves_resources_and_charges_everyone_at_minimum(): void
    {
        $this->seed(DemoSeeder::class);
        $studio = Studio::firstOrFail();
        [$admin,$coach] = $studio->coaches()->orderBy('users.id')->get();
        $start = now()->startOfHour()->addDay()->format('Y-m-d\TH:i');
        foreach ([$admin, $coach] as $teacher) {
            $this->actingAs($teacher)->post(route('bookings.slot', $studio), ['starts_at' => $start])->assertSessionHasNoErrors();
        }
        $data = ['starts_at' => $start, 'coaches' => [$admin->id], 'minimum' => 2, 'maximum' => 3, 'price' => '1.25'];
        $this->actingAs($admin)->post(route('groups.store', $studio), $data + ['token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->post(route('groups.store', $studio), $data + ['token' => Str::uuid()->toString()])->assertSessionHasErrors('starts_at');
        $data['coaches'] = [$coach->id];
        $this->post(route('groups.store', $studio), $data + ['token' => Str::uuid()->toString()])->assertSessionHasErrors('starts_at');
        $group = Lesson::sole();
        $students = User::factory()->count(2)->create();
        foreach ($students as $index => $student) {
            $studio->memberships()->create(['user_id' => $student->id, 'status' => 'approved']);
            CreditAccount::create(['studio_id' => $studio->id, 'user_id' => $student->id, 'balance' => 3]);
            $this->actingAs($student)->post(route('groups.join', $group), ['token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
            if ($index === 0) {
                $this->assertDatabaseHas('credit_accounts', ['user_id' => $student->id, 'frozen' => 1.25, 'balance' => 3]);
                $this->post(route('groups.leave', $group), ['token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
                $this->assertDatabaseHas('credit_accounts', ['user_id' => $student->id, 'frozen' => 0, 'balance' => 3]);
                $this->post(route('groups.join', $group), ['token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
            }
        }
        $this->assertSame('open', $group->fresh()->status);
        $this->assertEquals(2, CreditAccount::where('balance', 1.75)->where('frozen', 0)->count());
        $this->get(route('studios.show', $studio))->assertOk()->assertSee('已开班');

        $third = User::factory()->create();
        $studio->memberships()->create(['user_id' => $third->id, 'status' => 'approved']);
        CreditAccount::create(['studio_id' => $studio->id, 'user_id' => $third->id, 'balance' => 3]);
        $this->actingAs($third)->post(route('groups.join', $group), ['token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('credit_accounts', ['user_id' => $third->id, 'balance' => 1.75, 'frozen' => 0]);
        $this->travelTo($group->starts_at->subHours(2));
        $this->post(route('groups.leave', $group), ['token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->actingAs($students->first())->post(route('groups.leave', $group), ['token' => Str::uuid()->toString()])->assertSessionHasErrors('lesson');
        $this->post(route('groups.cancel', $group), ['token' => Str::uuid()->toString()])->assertForbidden();
        $this->travel(3)->hours();
        $this->actingAs($admin)->post(route('groups.cancel', $group), ['token' => Str::uuid()->toString()])->assertRedirect();
        $this->assertSame('cancelled', $group->fresh()->status);
        $this->assertEquals(3, CreditAccount::where('balance', 3)->where('frozen', 0)->count());
        $this->assertEquals(3, $group->enrollments()->where('status','released')->count());
    }
}
