<?php

namespace App\Services;

use App\Models\CreditAccount;
use App\Models\CreditRequest;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Studio;
use App\Models\User;
use App\Notifications\StudioMessage;
use Illuminate\Validation\ValidationException;

class CreditService
{
    public static function hundredths(string $decimal): int
    {
        [$whole, $fraction] = array_pad(explode('.', ltrim($decimal, '-')), 2, '');
        $units = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        return str_starts_with($decimal, '-') ? -$units : $units;
    }

    public static function decimal(int $units): string
    {
        return ($units < 0 ? '-' : '').intdiv(abs($units), 100).'.'.str_pad((string) (abs($units) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Called inside the studio transaction with the account owner locked. */
    public function change(Studio $studio, int $userId, int $balanceChange, int $frozenChange, string $reason, int $operation): void
    {
        $account = CreditAccount::firstOrNew(['studio_id' => $studio->id, 'user_id' => $userId]);
        $balance = self::hundredths($account->balance ?? '0') + $balanceChange;
        $frozen = self::hundredths($account->frozen ?? '0') + $frozenChange;
        if ($balance < $frozen || $frozen < 0 || $balance > 999999999999) {
            throw ValidationException::withMessages(['amount' => '余额不足或超出课时范围。']);
        }
        $account->fill(['balance' => self::decimal($balance), 'frozen' => self::decimal($frozen)])->save();
        $account->entries()->create([
            'operation_id' => $operation, 'reason' => $reason,
            'balance_change' => self::decimal($balanceChange), 'frozen_change' => self::decimal($frozenChange),
        ]);
    }

    public function charge(Studio $studio, Enrollment $seat, int $operation): void
    {
        $amount = self::hundredths($seat->amount);
        $this->change($studio, $seat->user_id, -$amount, $seat->status === 'frozen' ? -$amount : 0, '上课扣款', $operation);
        $seat->fill(['status' => 'paid'])->save();
    }

    public function closeLesson(Studio $studio, Lesson $lesson, int $operation, string $status): void
    {
        $recipients = $lesson->participantIds();
        foreach ($lesson->enrollments()->whereIn('status', ['frozen', 'paid'])->get() as $seat) {
            $this->release($studio, $seat, $operation);
        }
        $lesson->update(['status' => $status]);
        StudioMessage::lesson($lesson, $recipients, $lesson->statusLabel());
    }

    public function release(Studio $studio, Enrollment $seat, int $operation): void
    {
        $amount = self::hundredths($seat->amount);
        if ($seat->status === 'frozen') {
            $this->change($studio, $seat->user_id, 0, -$amount, '取消解冻', $operation);
        } elseif ($seat->status === 'paid') {
            $this->change($studio, $seat->user_id, $amount, 0, '退还课时', $operation);
        } else {
            return;
        }
        $seat->update(['status' => 'released']);
    }

    public function submit(Studio $studio, User $actor, int $userId, string $amount, string $token): void
    {
        StudioTransaction::run($studio, [$userId], $actor->id, 'credit.request', $token, function (Studio $studio) use ($actor, $userId, $amount) {
            abort_unless($studio->members()->whereKey($userId)->exists(), 403);
            if ($actor->id === $userId) {
                abort_unless(self::hundredths($amount) > 0, 403);
            } else {
                abort_unless($actor->can('manage', $studio), 403);
            }
            CreditRequest::create(['studio_id' => $studio->id, 'user_id' => $userId, 'requested_by' => $actor->id, 'amount' => $amount]);
            $recipients = $actor->id === $userId ? $studio->adminIds() : [$userId];
            StudioMessage::send($studio, array_diff($recipients, [$actor->id]), $actor->nickname.' 请求调整 '.$amount.' 课时');
        });
    }

    public function decide(Studio $studio, CreditRequest $credit, User $actor, string $decision, string $token): void
    {
        abort_unless($credit->studio_id === $studio->id, 404);
        StudioTransaction::run($studio, [$credit->user_id], $actor->id, 'credit.'.$decision, $token, function (Studio $studio, int $operation) use ($credit, $actor, $decision) {
            $credit->refresh();
            $studentRequest = $credit->user_id === $credit->requested_by;
            abort_unless($actor->id !== $credit->requested_by && ($studentRequest ? $actor->can('manage', $studio) : $actor->id === $credit->user_id), 403);
            if ($credit->status !== 'pending') {
                return;
            }
            if ($decision === 'approved') {
                $this->change($studio, $credit->user_id, self::hundredths($credit->amount), 0, '课时调整', $operation);
            }
            $credit->update(['status' => $decision]);
            StudioMessage::send($studio, [$credit->requested_by], $credit->amount.' 课时调整'.($decision === 'approved' ? '已通过' : '已拒绝'));
        });
    }
}
