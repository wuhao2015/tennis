<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(): View
    {
        return view('home', ['studios' => Studio::with('coaches')->orderBy('id')->get()]);
    }

    public function show(Request $request, Studio $studio): View
    {
        $membership = $studio->memberships()->where('user_id', $request->user()?->id)->first();

        return view('studio', compact('studio', 'membership'));
    }

    public function join(Request $request, Studio $studio): RedirectResponse
    {
        DB::transaction(function () use ($request, $studio) {
            Studio::lockForUpdate()->findOrFail($studio->id);
            $membership = $studio->memberships()->firstOrNew(['user_id' => $request->user()->id]);
            if (! $membership->exists || $membership->status === 'rejected') {
                $membership->fill(['status' => 'pending'])->save();
            }
        });

        return back()->with('status', '申请已提交');
    }

    public function decide(Request $request, Studio $studio, Membership $membership): RedirectResponse
    {
        Gate::authorize('manage', $studio);
        $decision = $request->validate(['decision' => 'required|in:approved,rejected'])['decision'];
        abort_unless($membership->studio_id === $studio->id, 404);
        $membership->whereKey($membership->id)->where('status', 'pending')->update(['status' => $decision]);

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
