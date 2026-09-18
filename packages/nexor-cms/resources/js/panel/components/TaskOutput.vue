<script setup>
import { nextTick, ref, watch } from 'vue';
import NBadge from './ui/NBadge.vue';
import NIcon from './ui/NIcon.vue';

/**
 * Вывод фоновой задачи: composer пишет туда по мере работы.
 */
const props = defineProps({
    task: { type: Object, default: null },
});

const box = ref(null);

// Пока задача идёт, держим окно прокрученным к последней строке.
watch(() => props.task?.output, async () => {
    await nextTick();

    if (box.value) {
        box.value.scrollTop = box.value.scrollHeight;
    }
});
</script>

<template>
    <div v-if="task" class="surface overflow-hidden rounded-xl border">
        <div class="flex flex-wrap items-center gap-3 border-b border-[var(--surface-border)] px-4 py-3">
            <NIcon v-if="task.state === 'running'" name="clock" size="size-4 animate-pulse text-brand-600" />
            <span class="min-w-0 flex-1 truncate text-sm font-medium text-[var(--text-strong)]">{{ task.title }}</span>

            <NBadge :color="{ running: 'blue', done: 'green', failed: 'red' }[task.state] ?? 'gray'">
                {{ { running: 'выполняется', done: 'готово', failed: 'ошибка' }[task.state] ?? task.state }}
            </NBadge>
        </div>

        <pre ref="box"
             class="max-h-96 overflow-auto bg-[var(--surface-muted)] px-4 py-3 font-mono text-xs leading-relaxed whitespace-pre-wrap text-[var(--text-muted)]">{{ task.output || 'Ждём вывод…' }}</pre>
    </div>
</template>
