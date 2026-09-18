<script setup>
import { computed, onMounted, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import TaskOutput from '../../components/TaskOutput.vue';
import { api } from '../../api';
import { useComposerTask } from '../../composables/useComposerTask';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * «Обновления»: версии пакетов NEXOR и запуск composer прямо из панели.
 */
const session = useSession();
const ui = useUi();
const { task, starting, running, start, adopt } = useComposerTask();

const loading = ref(true);
const state = ref(null);

const packages = computed(() => state.value?.packages ?? []);
const installed = computed(() => packages.value.filter((item) => item.installed));
const outdated = computed(() => installed.value.filter((item) => item.update_available));
const availability = computed(() => state.value?.availability ?? { ok: true, reason: null });
const canRun = computed(() => session.can('updates.manage') && state.value?.enabled && availability.value.ok);

async function load(refresh = false) {
    loading.value = true;

    try {
        state.value = await api.get('updates', refresh ? { refresh: 1 } : {});
        adopt(state.value.current);
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function update(code = null) {
    start('updates/update', code ? { package: code } : {}, () => load(true));
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <NPageHeader title="Обновления"
                     description="Версии NEXOR на этом сайте. Обновление запускает composer на сервере.">
            <template #actions>
                <NButton variant="secondary" icon="search" :loading="loading" @click="load(true)">
                    Проверить обновления
                </NButton>
                <NButton v-if="canRun" icon="upload" :loading="starting || running"
                         :disabled="!outdated.length" @click="update()">
                    Обновить всё
                </NButton>
            </template>
        </NPageHeader>

        <div v-if="state && !state.enabled"
             class="surface rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            Обновление из панели выключено настройкой <code class="font-mono">NEXOR_UPDATES</code>.
            Обновляйте сайт командой <code class="font-mono">composer update</code>.
        </div>

        <div v-else-if="state && !availability.ok"
             class="surface rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            {{ availability.reason }}
        </div>

        <NCard title="Пакеты" :padding="false">
            <ul class="divide-y divide-[var(--surface-border)]">
                <li v-for="item in packages" :key="item.code" class="flex flex-wrap items-center gap-4 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-medium text-[var(--text-strong)]">
                            {{ item.name }}
                            <code class="rounded bg-[var(--surface-muted)] px-1.5 py-0.5 font-mono text-xs font-normal text-[var(--text-muted)]">
                                {{ item.package }}
                            </code>
                        </p>
                        <p class="mt-1 text-sm text-[var(--text-muted)]">{{ item.description }}</p>
                    </div>

                    <div class="text-right text-sm">
                        <p v-if="item.installed" class="text-[var(--text-strong)]">{{ item.version }}</p>
                        <p v-else class="text-[var(--text-muted)]">не установлен</p>
                        <p v-if="item.latest && item.update_available" class="text-xs text-emerald-600 dark:text-emerald-400">
                            доступна {{ item.latest }}
                        </p>
                        <p v-else-if="item.branch" class="text-xs text-[var(--text-faint)]">ветка разработки</p>
                        <p v-else-if="item.installed && item.latest" class="text-xs text-[var(--text-faint)]">последняя</p>
                    </div>

                    <NBadge v-if="item.update_available" color="amber">есть обновление</NBadge>

                    <NButton v-if="canRun && item.installed" variant="secondary" size="sm"
                             :disabled="!item.update_available || starting || running"
                             @click="update(item.code)">
                        Обновить
                    </NButton>

                    <router-link v-else-if="!item.installed && session.can('modules.view')"
                                 :to="{ name: 'modules.index', query: { tab: 'available' } }"
                                 class="text-sm text-brand-600 hover:underline dark:text-brand-400">
                        Установить
                    </router-link>
                </li>
            </ul>
        </NCard>

        <TaskOutput :task="task" />

        <p class="flex items-start gap-2 text-xs text-[var(--text-muted)]">
            <NIcon name="info" size="size-4 shrink-0" />
            <span>
                После обновления сами собой выполняются миграции, синхронизация прав и сброс кешей.
                Если на сервере закрыт запуск процессов, обновляйтесь как обычно — командой composer в консоли.
            </span>
        </p>
    </div>
</template>
