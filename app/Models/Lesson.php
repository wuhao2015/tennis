<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime'];
    }

    public function participantIds(): array
    {
        return $this->coaches()->pluck('users.id')->merge(
            $this->enrollments()->whereIn('status', ['frozen', 'paid'])->pluck('user_id')
        )->all();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'recruiting' => '招募中', 'open' => '已开班',
            'pending' => '待确认', 'confirmed' => $this->cancel_requested_by ? '等待对方同意取消' : '已确认',
            'no_court' => '无可用球场', 'declined' => '已拒绝', 'withdrawn' => '已撤回',
            'cancelled' => '已取消', 'expired' => '已逾期',
        };
    }

    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lesson_coach');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }
}
