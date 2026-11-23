<x-layout title="消息与待办">
<section><h2>待我处理</h2>
<ul>
@foreach($applications as $application)<li><a href="{{ route('studios.show',$application->studio_id) }}">{{ $application->user->nickname }} 申请入会</a></li>@endforeach
@foreach($credits as $credit)<li><a href="{{ route('studios.show',$credit->studio_id) }}">审批课时 · {{ $credit->user->nickname }} · {{ $credit->amount }}</a></li>@endforeach
@foreach($confirmations as $lesson)<li><a href="{{ route('studios.show',$lesson->studio_id) }}">确认预约 · {{ $lesson->starts_at->format('m-d H:i') }}</a></li>@endforeach
@foreach($cancellations as $lesson)<li><a href="{{ route('studios.show',$lesson->studio_id) }}">确认取消 · {{ $lesson->starts_at->format('m-d H:i') }}</a></li>@endforeach
</ul>
@if($applications->isEmpty() && $credits->isEmpty() && $confirmations->isEmpty() && $cancellations->isEmpty())<p>暂无待办</p>@endif
</section><section><h2>站内消息</h2>
@if(auth()->user()->unreadNotifications()->exists())<form method="post" action="{{ route('reminders.read') }}">@csrf<button>全部标为已读</button></form>@endif
@forelse($messages as $message)
<article><small>{{ $message->created_at->format('Y-m-d H:i') }} · {{ $message->read_at ? '已读' : '未读' }}</small>
<p><a href="{{ route('studios.show',$message->data['studio_id']) }}">{{ $message->data['message'] }}</a></p></article>
@empty<p>暂无消息</p>@endforelse
<div class="actions">@if($messages->previousPageUrl())<a href="{{ $messages->previousPageUrl() }}">上一页</a>@endif
@if($messages->nextPageUrl())<a href="{{ $messages->nextPageUrl() }}">下一页</a>@endif</div>
</section></x-layout>
