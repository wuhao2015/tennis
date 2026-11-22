<section><h2>我的课时</h2>
<p>可用 {{ $account?->available() ?? '0.00' }} · 已冻结 {{ $account?->frozen ?? '0.00' }} · 余额 {{ $account?->balance ?? '0.00' }}</p>
<form method="post" action="{{ route('credits.store', $studio) }}">@csrf <x-token/>
<label>申请增加课时<input name="amount" type="number" step=".01" min=".01" required></label><button>申请增加</button></form>
@can('manage',$studio)
<form method="post" action="{{ route('credits.store', $studio) }}">@csrf <x-token/>
<label>调账学员<select name="user_id">@foreach($studio->members()->where('users.id','!=',auth()->id())->get() as $member)<option value="{{ $member->id }}">{{ $member->nickname }} · {{ $member->email }}</option>@endforeach</select></label>
<label>增减课时<input name="amount" type="number" step=".01" required></label><button>提交调账</button></form>
@endcan
<h3>课时申请</h3>
@forelse($creditRequests as $credit)
<p>{{ $credit->user->nickname }} · {{ $credit->amount }} · {{ ['pending'=>'待确认','approved'=>'已同意','rejected'=>'已拒绝'][$credit->status] }}</p>
@if($credit->status === 'pending' && auth()->id() !== $credit->requested_by && ($credit->user_id === auth()->id() || $credit->requested_by === $credit->user_id && auth()->user()->can('manage', $studio)))
<form method="post" action="{{ route('credits.decide',[$studio,$credit]) }}">@csrf <x-token/>
<button name="decision" value="approved">同意</button> <button name="decision" value="rejected">拒绝</button></form>
@endif
@empty<p>暂无申请</p>@endforelse
<details><summary>课时流水</summary>
@foreach($account?->entries()->latest('id')->get() ?? [] as $entry)<p>{{ $entry->created_at }} · {{ $entry->reason }} · 余额变动 {{ $entry->balance_change }} · 冻结变动 {{ $entry->frozen_change }}</p>@endforeach
</details></section>
