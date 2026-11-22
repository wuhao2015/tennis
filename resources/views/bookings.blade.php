<section><h2>一对一约课</h2><p>每节 60 分钟，1 课时。请提前 2–168 小时提交。</p>
@if($membership->is_coach)
<form action="{{ route('bookings.slot',$studio) }}" method="post">@csrf
<label>开放授课时段<input type="datetime-local" name="starts_at" step="1800" required></label><button>开放时段</button></form>
@endif
<details><summary>查看教练可约时段</summary>@forelse($slots as $slot)<p>{{ $slot->nickname }} · {{ $slot->starts_at }}</p>@empty<p>暂无开放时段</p>@endforelse</details>
<form action="{{ route('bookings.store',$studio) }}" method="post">@csrf <x-token/>
<label>教练<select name="coach_id">@foreach($studio->coaches as $coach)<option value="{{ $coach->id }}">{{ $coach->nickname }}</option>@endforeach</select></label>
<label>开始时间（北京时间）<input type="datetime-local" name="starts_at" step="1800" required></label><button>提交预约</button></form>
<h3>我的一对一课程</h3>
@forelse($bookings as $lesson)
<article><strong>{{ $lesson->starts_at->format('m-d H:i') }} · {{ $lesson->coaches->first()->nickname }}</strong>
<p>{{ $lesson->enrollments->first()->user->nickname }} · 待确认</p></article>
@empty<p>暂无预约</p>@endforelse</section>
