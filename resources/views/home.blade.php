<x-layout title="工作室">
@forelse($studios as $studio)
<article><h2><a href="{{ route('studios.show', $studio) }}">{{ $studio->name }}</a></h2>
<p>{{ $studio->courts }} 片球场</p><p>教练 · {{ $studio->coaches->pluck('nickname')->join('、') }}</p></article>
@empty<p>暂无工作室。</p>@endforelse
</x-layout>
