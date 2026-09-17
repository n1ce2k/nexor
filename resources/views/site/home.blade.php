@extends('site.layout')

@section('title', \Nexor\Cms\Models\Setting::get('site.name', config('app.name')).' — '.\Nexor\Cms\Models\Setting::get('site.tagline'))

@section('content')
    <section class="border-b border-slate-200 bg-gradient-to-b from-slate-50 to-white">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
            <p class="mb-4 inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700">
                Проект на Laravel {{ app()->version() }}
            </p>

            <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                {{ \Nexor\Cms\Models\Setting::get('site.name', config('app.name')) }}
            </h1>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="/admin"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Панель управления
                </a>
            </div>
        </div>
    </section>



    {{--
        Витрина компонентов NEXOR: каждый вызван по одному разу, над ним — сам тег.
        Данные демо-сайта: инфоблоки news (id 1) и katalog (id 2), разделы каталога
        razdel1 (id 2) и razdel2 (id 3), товар suschnost1 (id 7), новость novost1 (id 13),
        меню main, footer, top. Недоступный инфоблок показывает заглушку.
    --}}
    <div class="mx-auto max-w-6xl space-y-10 px-4 py-16 sm:px-6 lg:px-8">
        <header>
            <h2 class="text-2xl font-semibold text-slate-900">Компоненты</h2>
            <p class="mt-2 text-sm text-slate-600">
                Все компоненты сайта в одном месте. Свой шаблон любого из них — <code class="font-mono">php artisan nexor:component &lt;компонент&gt; &lt;имя&gt;</code>.
            </p>
        </header>

        {{-- ------------------------------------------------------------ навигация --}}

        <section class="space-y-4 rounded-2xl border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-900">Навигация</h3>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::menu code="main" />@endverbatim</code>
                <x-nexor::menu code="main" />
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::menu code="footer" template="stacked" />@endverbatim</code>
                <x-nexor::menu code="footer" template="stacked" />
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::menu iblock="katalog" :depth="2" />@endverbatim — разделы инфоблока меню</code>
                <x-nexor::menu iblock="katalog" :depth="2" />
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-2">
                    <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::menu.sections iblock="katalog" />@endverbatim</code>
                    <x-nexor::menu.sections iblock="katalog" />
                </div>

                <div class="space-y-2">
                    <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::menu.sections :iblock="2" template="chips" />@endverbatim</code>
                    <x-nexor::menu.sections :iblock="2" template="chips" />
                </div>
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::breadcrumbs iblock="katalog" />@endverbatim</code>
                <x-nexor::breadcrumbs iblock="katalog" />
            </div>
        </section>

        {{-- -------------------------------------------------------------- каталог --}}

        <section class="space-y-6 rounded-2xl border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-900">Каталог</h3>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::catalog.section-list iblock="katalog" />@endverbatim — карточки разделов</code>
                <x-nexor::catalog.section-list iblock="katalog" />
            </div>

            <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <div class="space-y-2">
                    <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::catalog.filter iblock="katalog" />@endverbatim</code>
                    <x-nexor::catalog.filter iblock="katalog" action="/katalog" />
                </div>

                <div class="space-y-2">
                    <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::catalog.section iblock="katalog" :paginate="false" :limit="4" />@endverbatim</code>
                    <x-nexor::catalog.section iblock="katalog" :paginate="false" :limit="4" />
                </div>
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::catalog.section iblock="katalog" template="tiles" :section_id="2" />@endverbatim — только раздел id 2</code>
                <x-nexor::catalog.section iblock="katalog" template="tiles" :section_id="2" />
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::catalog.element :iblock="2" :id="7" />@endverbatim — товар по id, с предложениями и ценой</code>
                <x-nexor::catalog.element :iblock="2" :id="7" />
            </div>
        </section>

        {{-- -------------------------------------------------------------- новости --}}

        <section class="space-y-6 rounded-2xl border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-900">Новости</h3>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::news.list iblock="news" :per-page="3" />@endverbatim</code>
                <x-nexor::news.list iblock="news" :per-page="3" />
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::news.list :iblock="1" template="compact" :paginate="false" :limit="5" />@endverbatim</code>
                <x-nexor::news.list :iblock="1" template="compact" :paginate="false" :limit="5" />
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::news.detail iblock="news" code="novost1" />@endverbatim — новость по коду; блоки конструктора выводятся здесь же</code>
{{--                <x-nexor::news.detail iblock="news" code="novost1" />--}}
            </div>
        </section>

        {{-- ------------------------------------------------------ поиск и формы --}}

        <section class="space-y-6 rounded-2xl border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-900">Поиск и форма</h3>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::search.form />@endverbatim — ведёт на /search</code>
                <x-nexor::search.form />
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::search.page :iblocks="['katalog', 'news']" />@endverbatim — результаты по ?q= (попробуйте /?q=Сущность)</code>
                <x-nexor::search.page :iblocks="['katalog', 'news']" />
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::form :fields="['name', 'phone']" title="Заказать звонок" button="Перезвоните мне" />@endverbatim</code>
                <x-nexor::form :fields="['name', 'phone']" title="Заказать звонок" button="Перезвоните мне" />
            </div>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::form :id="1" />@endverbatim — форма из админки «Формы ОС» (или form="код")</code>
                <x-nexor::form :id="1" />
            </div>
        </section>

        {{-- --------------------------------------------------------- магазин --}}

        @feature('shop')
            <section class="space-y-6 rounded-2xl border border-slate-200 p-6">
                <h3 class="text-lg font-semibold text-slate-900">Магазин <span class="text-sm font-normal text-slate-500">— внутри @@feature('shop')</span></h3>

                <div class="flex flex-wrap items-center gap-8">
                    <div class="space-y-2">
                        <code class="block font-mono text-xs text-slate-500">@verbatim<livewire:nexor-shop::cart-button />@endverbatim</code>
                        <livewire:nexor-shop::cart-button />
                    </div>

                    <div class="space-y-2">
                        <code class="block font-mono text-xs text-slate-500">@verbatim<livewire:nexor-shop::add-to-cart :element-id="7" />@endverbatim</code>
                        <livewire:nexor-shop::add-to-cart :element-id="7" />
                    </div>
                </div>

                <p class="text-xs text-slate-500">
                    Выезжающая корзина — <code class="font-mono">@verbatim<livewire:nexor-shop::cart-offcanvas />@endverbatim</code> в макете,
                    страницы корзины и оформления — /cart и /checkout.
                </p>
            </section>
        @endfeature

        {{-- ------------------------------------------------------- заглушка --}}

        <section class="space-y-4 rounded-2xl border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-900">Недоступный инфоблок</h3>

            <div class="space-y-2">
                <code class="block font-mono text-xs text-slate-500">@verbatim<x-nexor::news.list iblock="net-takogo" />@endverbatim — вместо ошибки заглушка</code>
                <x-nexor::news.list iblock="net-takogo" />
            </div>
        </section>
    </div>
@endsection
