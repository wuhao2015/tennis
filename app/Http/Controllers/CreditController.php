<?php

namespace App\Http\Controllers;

use App\Models\CreditRequest;
use App\Models\Studio;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function store(Request $request, Studio $studio, CreditService $credits): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'regex:/^-?\d{1,10}(\.\d{1,2})?$/D', 'not_in:0'],
            'user_id' => 'sometimes|required|integer', 'token' => 'required|uuid',
        ]);
        if (CreditService::hundredths($data['amount']) === 0) {
            return back()->withErrors(['amount' => '调整量须大于或小于零。']);
        }
        $credits->submit($studio, $request->user(), $data['user_id'] ?? $request->user()->id, $data['amount'], $data['token']);

        return back()->with('status', '课时申请已提交');
    }

    public function decide(Request $request, Studio $studio, CreditRequest $credit, CreditService $credits): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'token' => 'required|uuid']);
        $credits->decide($studio, $credit, $request->user(), $data['decision'], $data['token']);

        return back();
    }
}
