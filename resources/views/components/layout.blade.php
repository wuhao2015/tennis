<!doctype html>
<html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} · 网球工作室</title><link rel="stylesheet" href="{{ asset('tennis.css') }}"></head>
<body><header><nav aria-label="主导航"><a class="brand" href="{{ route('home') }}">网球工作室</a>
@auth
<a href="{{ route('profile') }}">{{ auth()->user()->nickname }}</a>
<form method="post" action="{{ route('logout') }}">@csrf<button class="link">退出</button></form>
@else
<a href="{{ route('login') }}">登录</a><a href="{{ route('register') }}">注册</a>
@endauth
</nav></header><main>
<h1>{{ $title }}</h1>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<section class="error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</section>@endif
{{ $slot }}</main></body></html>
