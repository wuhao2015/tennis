<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate(['nickname' => 'required|string|max:60']));

        return back()->with('status', '昵称已保存');
    }
}
