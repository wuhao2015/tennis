<?php

namespace App\Http\Controllers;

use App\Models\CreditRequest;
use App\Models\Lesson;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $managed = Membership::where('user_id', $user->id)->where('is_admin', true)->where('status', 'approved')->pluck('studio_id');
        $applications = Membership::whereIn('studio_id', $managed)->where('status', 'pending')->with('user')->get();
        $credits = CreditRequest::where('status', 'pending')->where('requested_by', '!=', $user->id)
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhere(fn ($q) => $q->whereIn('studio_id', $managed)->whereColumn('user_id', 'requested_by')))
            ->with('user')->get();
        $confirmations = Lesson::where('status', 'pending')->whereHas('coaches', fn ($q) => $q->whereKey($user->id))->get();
        $cancellations = Lesson::where('status', 'confirmed')->where('cancel_requested_by', '!=', $user->id)
            ->where(fn ($q) => $q->whereHas('coaches', fn ($q) => $q->whereKey($user->id))
                ->orWhereHas('enrollments', fn ($q) => $q->where('user_id', $user->id)))->get();
        $messages = $user->notifications()->paginate(25);

        return view('reminders', compact('applications', 'credits', 'confirmations', 'cancellations', 'messages'));
    }

    public function read(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
