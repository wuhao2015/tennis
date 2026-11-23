<?php

namespace App\Notifications;

use App\Models\Lesson;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifications;

class StudioMessage extends Notification
{
    public function __construct(private int $studioId, private string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['studio_id' => $this->studioId, 'message' => $this->message];
    }

    public static function send(Studio $studio, array $userIds, string $message): void
    {
        Notifications::sendNow(User::whereKey($userIds)->get(), new self($studio->id, $studio->name.' · '.$message));
    }

    public static function lesson(Lesson $lesson, array $userIds, string $message): void
    {
        $time = $lesson->starts_at->format('Y-m-d H:i');
        $type = $lesson->type === 'private' ? '一对一' : '团课';
        self::send($lesson->studio, $userIds, "{$time} {$type} · {$message}");
    }
}
