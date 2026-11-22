<section><h2>团课</h2>
@can('manage',$studio)
<details><summary>发布团课</summary>
<form method="post" action="{{ route('groups.store',$studio) }}">@csrf <x-token/>
<fieldset><legend>授课教练</legend>@foreach($studio->coaches as $coach)
<label><span><input type="checkbox" name="coaches[]" value="{{ $coach->id }}"> {{ $coach->nickname }}</span></label>@endforeach</fieldset>
<label>开始时间（北京时间）<input type="datetime-local" name="starts_at" step="1800" required></label>
<label>开班人数<input type="number" name="minimum" min="1" max="1000" required></label>
<label>人数上限<input type="number" name="maximum" min="1" max="1000" required></label>
<label>每人课时<input type="number" name="price" min=".01" step=".01" required></label><button>发布</button></form></details>
@endcan
@forelse($groups as $group)
@php($seat = $group->enrollments->firstWhere('user_id', auth()->id()))
<article><h3>{{ $group->starts_at->format('Y-m-d H:i') }} · {{ $group->statusLabel() }}</h3>
<p>{{ $group->coaches->pluck('nickname')->join('、') }} · 每人 {{ $group->price }} 课时</p>
<p>已报 {{ $group->enrollments->whereIn('status',['frozen','paid'])->count() }} / {{ $group->maximum }} 人 · {{ $group->minimum }} 人开班</p>
@if($group->status === 'recruiting')
@if($seat && $seat->status === 'frozen')
<form method="post" action="{{ route('groups.leave',$group) }}">@csrf <x-token/><button>撤回报名</button></form>
@elseif(!$group->coaches->contains(auth()->id()))
<form method="post" action="{{ route('groups.join',$group) }}">@csrf <x-token/><button>报名</button></form>
@endif
@endif</article>
@empty<p>暂无团课</p>@endforelse</section>
