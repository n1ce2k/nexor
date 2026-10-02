<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../core.js';

/**
 * Попапы о заказах, изменившихся без менеджера.
 *
 * Статусы посылок проверяет расписание, пока в панели никого нет. Человек
 * заходит — и сбоку всплывает, что с какими заказами случилось. Увиденное
 * сразу отмечается, поэтому после перезагрузки те же попапы не повторяются.
 *
 * Вид — как у тоста «добавлен в корзину» на сайте: тёмная карточка справа внизу.
 * В тёмной теме панели она светлее фона и с обводкой, иначе сливалась бы с ним.
 */

/** Как часто спрашивать о новом, пока панель открыта. */
const POLL = 120000;

/** Больше этого на экране не держим — остальное в «ещё N». */
const LIMIT = 5;

const notices = ref([]);
const more = ref(0);
let timer = null;

async function load() {
    try {
        const data = await api.get('shop/notices');
        const known = new Set(notices.value.map((notice) => notice.id));
        const fresh = data.data.filter((notice) => !known.has(notice.id));

        if (fresh.length === 0) {
            return;
        }

        notices.value = [...fresh, ...notices.value].slice(0, LIMIT);
        more.value = data.more;

        await api.post('shop/notices/seen', { id: data.last_id });
    } catch {
        // Попапы — подсказка, а не работа: без них панель работает как обычно.
    }
}

function close(id) {
    notices.value = notices.value.filter((notice) => notice.id !== id);

    if (notices.value.length === 0) {
        more.value = 0;
    }
}

function closeAll() {
    notices.value = [];
    more.value = 0;
}

onMounted(() => {
    load();
    timer = setInterval(load, POLL);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <div class="pointer-events-none fixed right-4 bottom-4 z-[85] flex w-full max-w-sm flex-col items-end gap-2">
        <TransitionGroup enter-from-class="translate-y-2 opacity-0" enter-active-class="transition duration-200"
                         leave-to-class="opacity-0" leave-active-class="transition duration-150">
            <div v-for="notice in notices" :key="notice.id"
                 class="pointer-events-auto flex w-full items-start gap-3 rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white shadow-2xl ring-1 ring-black/5 dark:bg-slate-700 dark:ring-white/15">
                <svg class="mt-0.5 size-5 shrink-0 text-sky-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7h11v9H3zM14 10h4l3 3v3h-7" />
                    <circle cx="7" cy="17.5" r="1.5" />
                    <circle cx="17" cy="17.5" r="1.5" />
                </svg>

                <div class="min-w-0 flex-1">
                    <p class="font-semibold">Заказ №{{ notice.number }}</p>
                    <p class="mt-0.5 text-slate-200">Доставка: «{{ notice.current }}»</p>
                    <p v-if="notice.previous" class="mt-0.5 text-xs text-slate-400">было: «{{ notice.previous }}»</p>

                    <router-link :to="{ name: 'shop.orders.show', params: { order: notice.order_id } }"
                                 class="mt-1 inline-block font-semibold text-white underline underline-offset-2"
                                 @click="close(notice.id)">
                        Открыть заказ
                    </router-link>
                </div>

                <button type="button" class="ml-1 text-lg leading-none text-slate-400 hover:text-white"
                        title="Закрыть" @click="close(notice.id)">
                    ×
                </button>
            </div>

            <div v-if="notices.length > 0 && (more > 0 || notices.length > 1)" key="footer"
                 class="pointer-events-auto flex w-full items-center justify-between gap-3 rounded-2xl bg-slate-900/90 px-4 py-2 text-xs text-slate-300 shadow-2xl dark:bg-slate-700/90 dark:ring-1 dark:ring-white/15">
                <router-link v-if="more > 0" :to="{ name: 'shop.orders' }" class="underline underline-offset-2 hover:text-white"
                             @click="closeAll">
                    И ещё {{ more }} — к заказам
                </router-link>
                <span v-else></span>

                <button type="button" class="hover:text-white" @click="closeAll">Закрыть все</button>
            </div>
        </TransitionGroup>
    </div>
</template>
