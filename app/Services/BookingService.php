<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private ScheduleService $schedule, private CreditService $credits) {}

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
