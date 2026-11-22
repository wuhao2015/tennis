<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Models\Studio;
use App\Services\CreditService;
use App\Services\StudioTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ExpireLessons extends Command
{
    protected $signature = 'lessons:expire';

    protected $description = 'Release pending bookings and underfilled groups after the confirmation deadline';

    public function handle(CreditService $credits): int
    {
        $due = Lesson::whereIn('status', ['pending', 'recruiting'])->where('starts_at', '<', now()->addHours(2));
        foreach ($due->lazyById(100) as $lesson) {
            StudioTransaction::run($lesson->studio, fn () => $lesson->participantIds(), null, 'lesson.expire', Str::uuid()->toString(), function (Studio $studio, int $operation) use ($lesson, $credits) {
                $lesson->refresh();
                if (! in_array($lesson->status, ['pending', 'recruiting'], true) || now() <= $lesson->starts_at->subHours(2)) {
                    return;
                }
                $credits->closeLesson($studio, $lesson, $operation, 'expired');
            });
        }

        return self::SUCCESS;
    }
}
