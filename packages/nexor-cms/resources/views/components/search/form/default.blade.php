{{--
    Шаблон компонента search.form.

    Приходит: $formAction, $query (что искали), $scope (код инфоблока),
    а также пропы $placeholder и $button.

    Свой шаблон: php artisan nexor:component search.form my_form
--}}

<form method="get" action="{{ $formAction }}" class="flex items-center gap-2" role="search">
    @if ($scope)
        <input type="hidden" name="in" value="{{ $scope }}">
    @endif

    <label class="sr-only" for="nexor-search">{{ $placeholder }}</label>

    <input id="nexor-search" type="search" name="q" value="{{ $query }}" placeholder="{{ $placeholder }}"
           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">

    <button type="submit"
            class="shrink-0 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700">
        {{ $button }}
    </button>
</form>
