<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Studio;
use App\Services\GroupLessonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupLessonController extends Controller
{
    public function store(Request $request, Studio $studio, GroupLessonService $groups): RedirectResponse
    {
        $data = $request->validate([
            'starts_at' => 'required|date_format:Y-m-d\TH:i', 'coaches' => 'required|array|min:1',
            'coaches.*' => 'required|integer|distinct', 'minimum' => 'required|integer|between:1,1000',
            'maximum' => 'required|integer|between:1,1000|gte:minimum', 'price' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/D', 'numeric', 'gt:0'],
            'token' => 'required|uuid',
        ]);
        $groups->publish($studio, $request->user(), $data);

        return back()->with('status', '团课已发布');
    }

    public function join(Request $request, Lesson $lesson, GroupLessonService $groups): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|uuid']);
        $groups->join($lesson, $request->user(), $data['token']);

        return back();
    }

    public function cancel(Request $request, Lesson $lesson, GroupLessonService $groups): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|uuid']);
        $groups->cancel($lesson, $request->user(), $data['token']);

        return back();
    }

    public function leave(Request $request, Lesson $lesson, GroupLessonService $groups): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|uuid']);
        $groups->leave($lesson, $request->user(), $data['token']);

        return back();
    }
}
