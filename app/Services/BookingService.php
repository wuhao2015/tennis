<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private ScheduleService $schedule, private CreditService $credits) {}

    public function act(Lesson $lesson, User $actor, string $action, string $token): void
    {
        abort_unless($lesson->type === 'private', 404);
        StudioTransaction::run($lesson->studio, fn () => $lesson->participantIds(), $actor->id, 'booking.'.$action, $token, function (Studio $studio, int $operation) use ($lesson, $actor, $action) {
            $lesson->refresh();
            $seat = $lesson->enrollments()->firstOrFail();
            $coach = $lesson->coaches()->whereKey($actor->id)->exists();
            $student = $seat->user_id === $actor->id;
            if ($action === 'cancel') {
                abort_unless($coach || $student, 403);
                if ($lesson->status !== 'confirmed' || $lesson->cancel_requested_by === $actor->id) {
                    return;
                }
                if (! $lesson->cancel_requested_by) {
                    $lesson->update(['cancel_requested_by' => $actor->id]);

                    return;
                }
                $this->credits->release($studio, $seat, $operation);
                $lesson->update(['status' => 'cancelled']);

                return;
            }
            abort_unless($action === 'withdraw' ? $student : $coach, 403);
            if ($lesson->status !== 'pending') {
                return;
            }
            $status = match (true) {
                now() > $lesson->starts_at->subHours(2) => 'expired',
                $action === 'withdraw' => 'withdrawn',
                $action === 'decline' => 'declined',
                ! $this->schedule->courtAvailable($studio, $lesson->starts_at) => 'no_court',
                default => 'confirmed',
            };
            if ($status === 'confirmed') {
                $this->credits->charge($studio, $seat, $operation);
            } else {
                $this->credits->release($studio, $seat, $operation);
            }
            $lesson->update(['status' => $status]);
        });
    }

    public function submit(Studio $studio, User $student, int $coachId, string $input, string $token): void
    {
        StudioTransaction::run($studio, [$student->id, $coachId], $student->id, 'booking.submit', $token, function (Studio $studio, int $operation) use ($student, $coachId, $input) {
            abort_unless($studio->members()->whereKey($student->id)->exists(), 403);
            $start = $this->schedule->start($input);
            $this->schedule->checkWindow($start);
            $this->schedule->checkCoaches($studio, [$coachId], $start);
            if ($student->id === $coachId || $this->schedule->peopleBusy([$student->id, $coachId], $start)) {
                throw ValidationException::withMessages(['starts_at' => '学员或教练时段冲突。']);
            }
            $lesson = Lesson::create(['studio_id' => $studio->id, 'starts_at' => $start, 'type' => 'private', 'status' => 'pending']);
            $lesson->coaches()->attach($coachId);
            $lesson->enrollments()->create(['user_id' => $student->id, 'amount' => '1.00', 'status' => 'frozen']);
            $this->credits->change($studio, $student->id, 0, 100, '预约冻结', $operation);
        });
    }
}
