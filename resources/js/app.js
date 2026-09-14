/**
 * Livewire приносит свой Alpine и запускает его сам. Второй экземпляр ломает оба:
 * меню перестают открываться, а компоненты корзины — обновляться. Поэтому свой
 * Alpine стартуем, только если Livewire на странице нет.
 *
 * Скрипт Livewire обычный, а этот — модуль, он выполняется позже, так что к
 * этому моменту window.Livewire уже известен.
 */
if (!window.Livewire) {
    import('alpinejs').then(({ default: Alpine }) => {
        window.Alpine = Alpine;
        Alpine.start();
    });
}
