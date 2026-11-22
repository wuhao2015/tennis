<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Studio;
use App\Services\BookingService;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function act(Request $request, Lesson $lesson, BookingService $bookings): RedirectResponse
    {
        $data = $request->validate(['action' => 'required|in:confirm,decline,withdraw,cancel', 'token' => 'required|uuid']);
        $bookings->act($lesson, $request->user(), $data['action'], $data['token']);

        return back();
    }

    public function slot(Request $request, Studio $studio, ScheduleService $schedule): RedirectResponse
    {
        $data = $request->validate(['starts_at' => 'required|date_format:Y-m-d\TH:i']);
        abort_unless($studio->coaches()->whereKey($request->user()->id)->exists(), 403);
        $start = $schedule->start($data['starts_at']);
        DB::table('coach_slots')->insertOrIgnore(['studio_id' => $studio->id, 'user_id' => $request->user()->id, 'starts_at' => $start]);

        return back()->with('status', '时段已开放');
    }

    public function store(Request $request, Studio $studio, BookingService $bookings): RedirectResponse
    {
        $data = $request->validate(['starts_at' => 'required|date_format:Y-m-d\TH:i', 'coach_id' => 'required|integer', 'token' => 'required|uuid']);
        $bookings->submit($studio, $request->user(), $data['coach_id'], $data['starts_at'], $data['token']);

        return back()->with('status', '预约已提交');
    }
}
