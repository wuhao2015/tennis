<?php

namespace App\Services;

use App\Models\Studio;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

class StudioTransaction
{
    /** Resolve changing participant lists only after acquiring the studio lock. */
    public static function run(Studio $studio, array|Closure $userIds, ?int $actorId, string $action, string $token, Closure $apply): void
    {
        DB::transaction(function () use ($studio, $userIds, $actorId, $action, $token, $apply) {
            $studio = Studio::lockForUpdate()->findOrFail($studio->id);
            $ids = $userIds instanceof Closure ? $userIds() : $userIds;
            User::whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            if (DB::table('operations')->where('studio_id', $studio->id)->where('token', $token)->exists()) {
                return;
            }
            $operation = DB::table('operations')->insertGetId([
                'studio_id' => $studio->id, 'actor_id' => $actorId, 'action' => $action,
                'token' => $token, 'created_at' => now(),
            ]);
            $apply($studio, $operation);
        }, 3);
    }
}
