<script setup>
import { onMounted, ref } from 'vue';
import NBadge from '../components/ui/NBadge.vue';
import NCard from '../components/ui/NCard.vue';
import NEmpty from '../components/ui/NEmpty.vue';
import NIcon from '../components/ui/NIcon.vue';
import NPageHeader from '../components/ui/NPageHeader.vue';
import { api } from '../api';
import { useSession } from '../stores/session';
import { useUi } from '../stores/ui';

const session = useSession();
const ui = useUi();

const stats = ref([]);
const iblocks = ref([]);
const recent = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const data = await api.get('dashboard');

        stats.value = data.stats;
        iblocks.value = data.iblocks.data ?? data.iblocks;
        recent.value = data.recent.data ?? data.recent;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
});

function relative(iso) {
    if (!iso) {
        return '';
    }

    const diff = Math.round((new Date(iso) - Date.now()) / 1000);
    const units = [['second', 60], ['minute', 60], ['hour', 24], ['day', 30], ['month', 12], ['year', Infinity]];
    const formatter = new Intl.RelativeTimeFormat('ru', { numeric: 'auto' });

    let value = diff;

    for (const [unit, size] of units) {
        if (Math.abs(value) < size) {
            return formatter.format(Math.round(value), unit);
        }

        value /= size;
    }

    return '';
}
</script>

<template>
    <div>
        <NPageHeader title="Рабочий стол"
                     :description="`Здравствуйте, ${session.user?.name ?? ''}. Ниже — сводка по проекту.`" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <component v-for="stat in stats" :key="stat.key"
                       :is="stat.route ? 'router-link' : 'div'"
                       :to="stat.route ? { name: stat.route } : undefined"
                       class="surface flex items-center gap-4 rounded-[var(--radius-card)] border p-5 shadow-sm transition"
                       :class="stat.route && 'hover:border-brand-400 hover:shadow-md'">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                    <NIcon :name="stat.icon" />
                </span>

                <div class="min-w-0">
                    <p class="text-2xl font-semibold text-[var(--text-strong)]">
                        {{ stat.value.toLocaleString('ru-RU') }}
                    </p>
                    <p class="truncate text-xs text-[var(--text-muted)]">{{ stat.label }}</p>
                </div>
            </component>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <NCard title="Инфоблоки" description="Быстрый переход к наполнению контентом" :padding="false">
                    <NEmpty v-if="!loading && iblocks.length === 0" icon="layers" title="Инфоблоков пока нет"
                            description="Создайте первый инфоблок, чтобы начать наполнять сайт контентом." />

                    <ul v-else class="divide-y divide-[var(--surface-border)]">
                        <li v-for="iblock in iblocks" :key="iblock.id">
                            <router-link :to="{ name: 'elements.index', params: { iblock: iblock.id } }"
                                         class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-[var(--surface-muted)]">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[var(--surface-muted)] text-[var(--text-muted)]">
                                    <NIcon name="folder" size="size-4" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-[var(--text-strong)]">{{ iblock.name }}</p>
                                    <p class="truncate text-xs text-[var(--text-muted)]">
                                        {{ iblock.type?.name }} · код <code class="font-mono">{{ iblock.code }}</code>
                                    </p>
                                </div>

                                <NBadge>{{ iblock.elements_count }}</NBadge>
                                <NIcon name="chevron-right" size="size-4 shrink-0 text-[var(--text-faint)]" />
                            </router-link>
                        </li>
                    </ul>
                </NCard>
            </div>

            <NCard title="Последние действия" :padding="false">
                <NEmpty v-if="!loading && recent.length === 0" icon="clock" title="Записей нет"
                        description="Здесь появятся действия пользователей в админке." />

                <ul v-else class="divide-y divide-[var(--surface-border)]">
                    <li v-for="entry in recent" :key="entry.id" class="px-5 py-3">
                        <p class="text-sm text-[var(--text-strong)]">
                            {{ entry.action_label }}
                            <span v-if="entry.description" class="text-[var(--text-muted)]">
                                — {{ entry.description }}
                            </span>
                        </p>
                        <p class="mt-0.5 text-xs text-[var(--text-faint)]">
                            {{ entry.user?.name ?? 'Система' }} · {{ relative(entry.created_at) }}
                        </p>
                    </li>
                </ul>
            </NCard>
        </div>
    </div>
</template>
