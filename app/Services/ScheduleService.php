<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Studio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function start(string $input): CarbonImmutable
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input, 'Asia/Shanghai');
        if (! in_array($start->minute, [0, 30], true)) {
            throw ValidationException::withMessages(['starts_at' => '课程须在整点或半点开始。']);
        }

        return $start;
    }

    public function checkWindow(CarbonImmutable $start): void
    {
        $now = now()->toImmutable();
        if ($start < $now->addHours(2) || $start > $now->addHours(168)) {
            throw ValidationException::withMessages(['starts_at' => '请提前 2 至 168 小时预约。']);
        }
    }

    public function checkCoaches(Studio $studio, array $ids, CarbonImmutable $start): void
    {
        $available = DB::table('coach_slots')->where('studio_id', $studio->id)->whereIn('user_id', $ids)
            ->where('starts_at', $start)->count();
        if ($studio->coaches()->whereKey($ids)->count() !== count($ids) || $available !== count($ids)) {
            throw ValidationException::withMessages(['coaches' => '教练尚未开放该时段。']);
        }
    }

    public function overlaps(CarbonImmutable $start, ?int $except = null): Builder
    {
        return Lesson::whereIn('status', ['pending', 'confirmed', 'recruiting', 'open'])
            ->where('starts_at', '>', $start->subHour())->where('starts_at', '<', $start->addHour())
            ->when($except, fn ($q) => $q->where('id', '!=', $except));
    }

    public function peopleBusy(array $userIds, CarbonImmutable $start, ?int $except = null): bool
    {
        return $this->overlaps($start, $except)->where(function ($query) use ($userIds) {
            $query->whereHas('coaches', fn ($q) => $q->whereKey($userIds))
                ->orWhereHas('enrollments', fn ($q) => $q->whereIn('user_id', $userIds)->whereIn('status', ['frozen', 'paid']));
        })->exists();
    }
}
