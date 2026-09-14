{{--
    Синхронизация корзины между вкладками браузера.

    Корзина лежит в cookie, общей для всех вкладок, — не хватает только сигнала
    «перечитай». Вкладка, где корзину поменяли, пишет отметку в localStorage;
    остальные ловят событие `storage` и просят свои компоненты перерисоваться.

    Рассылается только `cart-changed` (корзину изменил человек), а принимающая
    вкладка шлёт себе `cart-updated` — поэтому вкладки не перекидывают сигнал
    друг другу бесконечно. @assets подключает скрипт на страницу один раз, сколько
    бы компонентов корзины на ней ни стояло.
--}}

@assets
<script>
    (() => {
        if (window.__nexorCartSync) {
            return;
        }

        window.__nexorCartSync = true;

        const key = 'nexor-cart-changed';

        window.addEventListener('cart-changed', () => {
            try {
                localStorage.setItem(key, String(Date.now()));
            } catch (error) {
                // Хранилище запрещено — вкладки просто не синхронизируются.
            }
        });

        window.addEventListener('storage', (event) => {
            if (event.key === key && window.Livewire) {
                window.Livewire.dispatch('cart-updated');
            }
        });
    })();
</script>
@endassets
