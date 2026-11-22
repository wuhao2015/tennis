<x-layout :title="$studio->name">
<p>{{ $studio->courts }} 片球场 · 北京时间</p>
@guest<a href="{{ route('login') }}">登录后申请加入</a>@else
@if(!$membership || $membership->status === 'rejected')
@if($membership)<p>入会申请未通过</p>@endif
<form method="post" action="{{ route('studios.join', $studio) }}">@csrf<button>申请加入</button></form>
@elseif($membership->status === 'pending')<p>入会申请待审批</p>
@else<p>已加入工作室</p>
@endif
@endguest
@can('manage', $studio)
<section><h2>工作室管理</h2>
<form method="post" action="{{ route('studios.courts', $studio) }}">@csrf
<label>球场数<input type="number" name="courts" min="1" max="1000" required value="{{ $studio->courts }}"></label><button>保存球场数</button></form>
<h3>待审批入会申请</h3>
@forelse($studio->memberships()->with('user')->where('status','pending')->get() as $applicant)
<form method="post" action="{{ route('memberships.decide', [$studio,$applicant]) }}">@csrf
{{ $applicant->user->nickname }}
<button name="decision" value="approved">同意</button> <button name="decision" value="rejected">拒绝</button></form>
@empty<p>暂无申请</p>@endforelse</section>
@endcan
</x-layout>
