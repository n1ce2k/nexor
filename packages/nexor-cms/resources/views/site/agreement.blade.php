{{--
    Страница соглашения: /agreement/<код>.

    Приходит: $agreement — название, текст (HTML или обычный), дата изменения.
    Макет — site.layout сайта.
--}}

@extends('site.layout')

@section('title', $agreement->name)

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <h1 class="mb-6 text-3xl font-semibold tracking-tight text-slate-900">{{ $agreement->name }}</h1>

        <div class="prose-site">
            @include('nexor::components.form.partials.agreement-text', ['agreement' => $agreement])
        </div>

        <p class="mt-10 text-xs text-slate-400">Редакция от {{ $agreement->updated_at?->format('d.m.Y') }}</p>
    </article>
@endsection
