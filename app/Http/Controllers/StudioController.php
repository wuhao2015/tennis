<?php

namespace App\Http\Controllers;

use App\Models\CreditAccount;
use App\Models\CreditRequest;
use App\Models\Lesson;
use App\Models\Membership;
use App\Models\Studio;
use App\Notifications\StudioMessage;
use App\Services\AppConfig;
use App\Services\StudioTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'studios' => Studio::with('coaches')->orderBy('id')->get(),
            'greeting' => AppConfig::get('GREETING', config('app.name')),
        ]);
    }

    public function show(Request $request, Studio $studio): View
    {
        $membership = $studio->memberships()->where('user_id', $request->user()?->id)->first();

        if ($membership?->status !== 'approved') {
            return view('studio', compact('studio', 'membership'));
        }

        $account = CreditAccount::where('studio_id', $studio->id)->where('user_id', $request->user()?->id)->first();
        $creditRequests = CreditRequest::where('studio_id', $studio->id)
            ->when(! $request->user()?->can('manage', $studio), fn ($query) => $query->where('user_id', $request->user()?->id))
            ->with('user')->latest('id')->get();

        $slots = DB::table('coach_slots')->join('users', 'users.id', '=', 'coach_slots.user_id')
            ->where('studio_id', $studio->id)->where('starts_at', '>=', now())->orderBy('starts_at')->get(['starts_at', 'nickname']);
        $bookings = Lesson::where('studio_id', $studio->id)->where('type', 'private')
            ->where(fn ($q) => $q->whereHas('coaches', fn ($q) => $q->whereKey([$request->user()?->id]))
                ->orWhereHas('enrollments', fn ($q) => $q->where('user_id', $request->user()?->id)))
            ->with('coaches', 'enrollments.user')->orderByDesc('starts_at')->get();

        $groups = Lesson::where('studio_id', $studio->id)->where('type', 'group')->with('coaches', 'enrollments')->orderByDesc('starts_at')->get();

        return view('studio', compact('studio', 'membership', 'account', 'creditRequests', 'slots', 'bookings', 'groups'));
    }

    public function join(Request $request, Studio $studio): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|uuid']);
        $actor = $request->user();
        StudioTransaction::run($studio, [$actor->id], $actor->id, 'membership.join', $data['token'], function (Studio $studio) use ($actor) {
            $membership = $studio->memberships()->firstOrNew(['user_id' => $actor->id]);
            if (! $membership->exists || $membership->status === 'rejected') {
                $membership->fill(['status' => 'pending'])->save();
                StudioMessage::send($studio, $studio->adminIds(), $actor->nickname.' 申请入会');
            }
        });

        return back()->with('status', '申请已提交');
    }

    public function decide(Request $request, Studio $studio, Membership $membership): RedirectResponse
    {
        Gate::authorize('manage', $studio);
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'token' => 'required|uuid']);
        abort_unless($membership->studio_id === $studio->id, 404);
        StudioTransaction::run($studio, [$membership->user_id], $request->user()->id, 'membership.'.$data['decision'], $data['token'], function (Studio $studio) use ($membership, $data) {
            $membership->refresh();
            if ($membership->status === 'pending') {
                $membership->update(['status' => $data['decision']]);
                StudioMessage::send($studio, [$membership->user_id], $data['decision'] === 'approved' ? '入会申请已通过' : '入会申请已拒绝');
            }
        });

        return back();
    }

    public function courts(Request $request, Studio $studio): RedirectResponse
    {
        Gate::authorize('manage', $studio);
        $data = $request->validate(['courts' => 'required|integer|between:1,1000']);
        $studio->update($data);

        return back()->with('status', '球场数已保存');
    }
}
