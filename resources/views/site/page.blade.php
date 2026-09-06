@extends('site.layout')

@section('title', $page->meta_title ?: $page->name)
@section('description', $page->meta_description ?: Str::limit(strip_tags((string) $page->preview_text), 160))

@if ($page->meta_keywords)
    @section('keywords', $page->meta_keywords)
@endif

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:px-8">
        <nav class="mb-6 flex items-center gap-1.5 text-xs text-slate-500">
            <a href="{{ route('home') }}" class="transition hover:text-brand-600">Главная</a>
            <span>/</span>
            <span class="text-slate-700">{{ $page->name }}</span>
        </nav>

        <header class="mb-8">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">{{ $page->name }}</h1>

            @if ($subtitle = $page->property('subtitle'))
                <p class="mt-3 text-lg text-slate-600">{{ $subtitle }}</p>
            @endif
        </header>

        @if ($page->detail_picture_url)
            <img src="{{ $page->detail_picture_url }}" alt="{{ $page->name }}"
                 class="mb-8 w-full rounded-2xl object-cover">
        @endif

        @if ($page->preview_text)
            <p class="mb-8 border-l-4 border-brand-200 pl-4 text-lg text-slate-600">
                {{ $page->preview_text }}
            </p>
        @endif

        <div class="prose-site">
            @if ($page->detail_text_type === 'html')
                {{-- Detail text is authored in the admin panel by trusted editors. --}}
                {!! $page->detail_text !!}
            @else
                {!! nl2br(e($page->detail_text)) !!}
            @endif
        </div>
    </article>
@endsection
