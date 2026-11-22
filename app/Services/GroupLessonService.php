<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class GroupLessonService
{
    public function __construct(private ScheduleService $schedule, private CreditService $credits) {}

    public function publish(Studio $studio, User $admin, array $data): void
    {
        $ids = $data['coaches'];
        StudioTransaction::run($studio, $ids, $admin->id, 'group.publish', $data['token'], function (Studio $studio) use ($data, $admin, $ids) {
            abort_unless($admin->can('manage', $studio), 403);
            $start = $this->schedule->start($data['starts_at']);
            if ($start < now()->addHours(2) || ! $this->schedule->coachesAvailable($studio, $ids, $start) || $this->schedule->peopleBusy($ids, $start)) {
                throw ValidationException::withMessages(['starts_at' => '教练不可约或报名已截止。']);
            }
            if (! $this->schedule->courtAvailable($studio, $start)) {
                throw ValidationException::withMessages(['starts_at' => '该时段无可用球场。']);
            }
            $lesson = Lesson::create([
                'studio_id' => $studio->id, 'type' => 'group', 'status' => 'recruiting', 'starts_at' => $start,
                'minimum' => $data['minimum'], 'maximum' => $data['maximum'], 'price' => $data['price'],
            ]);
            $lesson->coaches()->attach($ids);
        });
    }

    public function join(Lesson $lesson, User $student, string $token): void
    {
        abort_unless($lesson->type === 'group', 404);
        StudioTransaction::run($lesson->studio, fn () => [...$lesson->participantIds(), $student->id], $student->id, 'group.join', $token, function (Studio $studio, int $operation) use ($lesson, $student) {
            $lesson->refresh();
            abort_unless($studio->members()->whereKey($student->id)->exists(), 403);
            $this->schedule->checkWindow($lesson->starts_at);
            if ($lesson->status !== 'recruiting') {
                throw ValidationException::withMessages(['lesson' => '本班已结束招募。']);
            }
            $seat = $lesson->enrollments()->firstOrNew(['user_id' => $student->id]);
            if ($seat->exists && $seat->status !== 'released') {
                return;
            }
            if ($this->schedule->peopleBusy([$student->id], $lesson->starts_at) || $lesson->enrollments()->whereIn('status', ['frozen', 'paid'])->count() >= $lesson->maximum) {
                throw ValidationException::withMessages(['lesson' => '学员时段冲突或人数已满。']);
            }
            $seat->fill(['amount' => $lesson->price, 'status' => 'frozen'])->save();
            $this->credits->change($studio, $student->id, 0, CreditService::hundredths($seat->amount), '团课冻结', $operation);
            $seats = $lesson->enrollments()->where('status', 'frozen')->get();
            if ($seats->count() < $lesson->minimum) {
                return;
            }
            $coaches = $lesson->coaches()->pluck('users.id')->all();
            if (! $this->schedule->coachesAvailable($studio, $coaches, $lesson->starts_at)
                || ! $this->schedule->courtAvailable($studio, $lesson->starts_at, $lesson->id)
                || $this->schedule->peopleBusy([...$coaches, ...$seats->pluck('user_id')], $lesson->starts_at, $lesson->id)) {
                $this->close($studio, $lesson, $operation);

                return;
            }
            foreach ($seats as $enrollment) {
                $this->credits->charge($studio, $enrollment, $operation);
            }
            $lesson->update(['status' => 'open']);
        });
    }

    public function leave(Lesson $lesson, User $student, string $token): void
    {
        abort_unless($lesson->type === 'group', 404);
        StudioTransaction::run($lesson->studio, [$student->id], $student->id, 'group.leave', $token, function (Studio $studio, int $operation) use ($lesson, $student) {
            $lesson->refresh();
            $seat = $lesson->enrollments()->where('user_id', $student->id)->firstOrFail();
            if ($lesson->status === 'recruiting') {
                $this->credits->release($studio, $seat, $operation);
            }
        });
    }

    private function close(Studio $studio, Lesson $lesson, int $operation): void
    {
        foreach ($lesson->enrollments()->whereIn('status', ['frozen', 'paid'])->get() as $seat) {
            $this->credits->release($studio, $seat, $operation);
        }
        $lesson->update(['status' => 'cancelled']);
    }
}
