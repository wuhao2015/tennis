<?php

namespace Tests\Feature;

use App\Models\CreditRequest;
use App\Models\Lesson;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;
use App\Services\CreditService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_failure_rolls_back_an_approval_and_the_same_token_can_retry(): void
    {
        $this->seed(DemoSeeder::class);
        $studio = Studio::firstOrFail();
        $admin = $studio->coaches()->firstOrFail();
        $student = User::factory()->create();
        $credit = CreditRequest::create([
            'studio_id' => $studio->id, 'user_id' => $student->id, 'requested_by' => $admin->id, 'amount' => '2.50',
        ]);
        $failOnce = true;
        DatabaseNotification::creating(function () use (&$failOnce) {
            if ($failOnce) {
                $failOnce = false;
                throw new \RuntimeException('Notification write failed');
            }
        });
        $service = app(CreditService::class);
        $token = Str::uuid()->toString();
        try {
            $service->decide($studio, $credit, $student, 'approved', $token);
            $this->fail('The injected failure must reach the transaction boundary.');
        } catch (\RuntimeException $failure) {
            $this->assertSame('Notification write failed', $failure->getMessage());
        }
        $this->assertSame('pending', $credit->fresh()->status);
        $this->assertDatabaseCount('operations', 0);
        $this->assertDatabaseCount('credit_entries', 0);
        $this->assertDatabaseCount('credit_accounts', 0);
        $service->decide($studio, $credit, $student, 'approved', $token);
        $service->decide($studio, $credit, $student, 'approved', $token);
        $this->assertDatabaseHas('credit_accounts', ['balance' => 2.5, 'frozen' => 0]);
        $this->assertDatabaseCount('credit_entries', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_approvals_confirmations_and_cancellations_reach_the_next_decision_maker(): void
    {
        $this->seed(DemoSeeder::class);
        $studio = Studio::firstOrFail();
        [$admin,$coach] = $studio->coaches()->orderBy('users.id')->get();
        $student = User::factory()->create();
        $this->actingAs($student)->post(route('studios.join', $studio), ['token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get('/reminders')->assertSee($student->nickname.' 申请入会');
        $application = Membership::where('user_id', $student->id)->sole();
        $this->post(route('memberships.decide', [$studio, $application]), ['decision' => 'approved', 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->get('/reminders')->assertSee('暂无待办');
        $this->actingAs($student)->get('/reminders')->assertSee('入会申请已通过');
        $this->post(route('credits.store', $studio), ['amount' => '2.00', 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get('/reminders')->assertSee('审批课时');
        $this->post(route('credits.decide', [$studio, CreditRequest::sole()]), ['decision' => 'approved', 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->get('/reminders')->assertSee('暂无待办');
        $start = now()->startOfHour()->addDay()->format('Y-m-d\TH:i');
        $this->actingAs($coach)->post(route('bookings.slot', $studio), ['starts_at' => $start]);
        $this->actingAs($student)->post(route('bookings.store', $studio), ['coach_id' => $coach->id, 'starts_at' => $start, 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->actingAs($coach)->get('/reminders')->assertSee('确认预约')->assertSee('提交预约');
        $url = route('bookings.act', Lesson::sole());
        $this->post($url, ['action' => 'confirm', 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->get('/reminders')->assertSee('暂无待办');
        $this->actingAs($student)->get('/reminders')->assertSee('预约已确认');
        $this->post($url, ['action' => 'cancel', 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->get('/reminders')->assertSee('暂无待办');
        $this->actingAs($coach)->get('/reminders')->assertSee('确认取消');
        $this->post($url, ['action' => 'cancel', 'token' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->get('/reminders')->assertSee('暂无待办')->assertSee('已取消');
        $this->actingAs($student)->get('/reminders')->assertSee('已取消');
        $this->post(route('reminders.read'))->assertRedirect();
        $this->assertSame(0,$student->unreadNotifications()->count());
        $this->assertGreaterThan(0,$coach->unreadNotifications()->count());
    }
}
