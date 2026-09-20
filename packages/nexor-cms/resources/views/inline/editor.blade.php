{{--
    Полоса режима правки и подключение редактора.

    Вставляется перед </body> только в режиме правки: посетителю сайта ничего
    из этого не достаётся.
--}}

<link rel="stylesheet" href="{{ $assets['css'] }}">

<div class="nexor-edit-bar">
    <span>Режим правки: ЛКМ - правка, Alt+ЛКМ - вернуть изменение, Ctrl+Enter - сохранить многострочный блок</span>
    <button type="button" data-nexor-edit-off>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <path d="m16 17 5-5-5-5"></path>
            <path d="M21 12H9"></path>
        </svg>
    </button>
</div>

<script src="{{ $assets['js'] }}" data-base="{{ $base }}" data-token="{{ csrf_token() }}"></script>
