<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import NBadge from '../components/ui/NBadge.vue';
import NCard from '../components/ui/NCard.vue';
import NEmpty from '../components/ui/NEmpty.vue';
import NFilters from '../components/ui/NFilters.vue';
import NPageHeader from '../components/ui/NPageHeader.vue';
import NPagination from '../components/ui/NPagination.vue';
import NSelect from '../components/ui/NSelect.vue';
import NTable from '../components/ui/NTable.vue';
import { api } from '../api';
import { useUi } from '../stores/ui';

const ui = useUi();

const rows = ref([]);
const meta = ref(null);
const users = ref([]);
const actions = ref([]);
const expanded = ref({});
const loading = ref(true);
const query = reactive({ search: '', user: null, action: null, page: 1 });

const columns = [
    { key: 'created_at', label: 'Дата', width: '11rem', muted: true },
    { key: 'user', label: 'Пользователь', width: '10rem' },
    { key: 'action', label: 'Действие', width: '10rem' },
    { key: 'description', label: 'Объект' },
    { key: 'ip', label: 'IP', width: '10rem', muted: true },
];

const colors = {
    created: 'green',
    updated: 'blue',
    deleted: 'red',
    login_failed: 'red',
    login: 'violet',
    'tool.sql': 'amber',
    'tool.php': 'amber',
};

const dirty = computed(() => Boolean(query.search || query.user || query.action));

async function load() {
    loading.value = true;

    try {
        const data = await api.get('logs', query);

        rows.value = data.data;
        meta.value = data.meta;
        users.value = data.meta?.users ?? [];
        actions.value = data.meta?.actions ?? [];
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function apply() {
    query.page = 1;
    load();
}

function reset() {
    query.search = '';
    query.user = null;
    query.action = null;
    apply();
}

function goToPage(page) {
    query.page = page;
    load();
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'medium' }) : '';
}

function short(value) {
    const text = value === null || value === undefined ? '' : String(value);

    return text.length > 40 ? `${text.slice(0, 40)}…` : text;
}

onMounted(load);
</script>

<template>
    <div>
        <NPageHeader title="Журнал действий" description="Кто и что менял в панели управления." />

        <NCard :padding="false">
            <div class="border-b border-[var(--surface-border)] p-4">
                <NFilters v-model="query.search" placeholder="Описание..." :dirty="dirty"
                          @apply="apply" @reset="reset">
                    <NSelect v-model="query.user" :options="users" placeholder="Все пользователи" class="w-auto" />
                    <NSelect v-model="query.action" :options="actions" placeholder="Все действия" class="w-auto" />
                </NFilters>
            </div>

            <NEmpty v-if="!loading && rows.length === 0" icon="clock" title="Записей нет"
                    description="Действия пользователей появятся здесь автоматически." />

            <template v-else>
                <NTable :columns="columns" :rows="rows" :loading="loading">
                    <template #cell-created_at="{ row }">
                        <span class="text-xs">{{ formatDate(row.created_at) }}</span>
                    </template>

                    <template #cell-user="{ row }">
                        <span class="text-sm">{{ row.user?.name ?? 'Система' }}</span>
                    </template>

                    <template #cell-action="{ row }">
                        <NBadge :color="colors[row.action] ?? 'gray'">{{ row.action_label }}</NBadge>
                    </template>

                    <template #cell-description="{ row }">
                        <p class="text-sm text-[var(--text-strong)]">{{ row.description ?? '—' }}</p>

                        <template v-if="row.changes">
                            <button type="button"
                                    class="mt-0.5 text-xs text-brand-600 hover:underline dark:text-brand-400"
                                    @click="expanded[row.id] = !expanded[row.id]">
                                {{ expanded[row.id] ? 'скрыть изменения' : 'показать изменения' }}
                            </button>

                            <div v-if="expanded[row.id]" class="mt-2 space-y-1">
                                <p v-for="(change, field) in row.changes" :key="field"
                                   class="font-mono text-xs text-[var(--text-muted)]">
                                    <span class="text-[var(--text-base)]">{{ field }}</span>:
                                    <template v-if="change && typeof change === 'object' && 'from' in change">
                                        <span class="line-through">{{ short(change.from) }}</span>
                                        →
                                        <span class="text-emerald-600 dark:text-emerald-400">{{ short(change.to) }}</span>
                                    </template>
                                    <span v-else class="text-[var(--text-base)]">{{ short(change) }}</span>
                                </p>
                            </div>
                        </template>
                    </template>

                    <template #cell-ip="{ row }">
                        <code class="font-mono text-xs">{{ row.ip ?? '—' }}</code>
                    </template>
                </NTable>

                <NPagination :meta="meta" @change="goToPage" />
            </template>
        </NCard>
    </div>
</template>
