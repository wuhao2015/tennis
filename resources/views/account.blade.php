<x-layout :title="['register'=>'注册', 'login'=>'登录', 'profile'=>'编辑昵称'][$mode]">
<section><form method="post" action="{{ route($mode) }}">@csrf
@if($mode !== 'login')<label>昵称<input name="nickname" required maxlength="60" value="{{ old('nickname', auth()->user()?->nickname) }}"></label>@endif
@if($mode !== 'profile')
<label>邮箱<input type="email" name="email" autocomplete="email" value="{{ old('email') }}" required></label>
<label>密码<input type="password" name="password" autocomplete="{{ $mode === 'login' ? 'current-password' : 'new-password' }}" required></label>
@if($mode === 'register')<label>确认密码<input type="password" name="password_confirmation" autocomplete="new-password" required></label>@endif
@endif
<button>{{ ['register'=>'注册', 'login'=>'登录', 'profile'=>'保存昵称'][$mode] }}</button>
</form></section></x-layout>
