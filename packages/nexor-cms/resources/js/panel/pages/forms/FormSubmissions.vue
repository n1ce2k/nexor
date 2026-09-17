<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NFilters from '../../components/ui/NFilters.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NModal from '../../components/ui/NModal.vue';
import NPagination from '../../components/ui/NPagination.vue';
import NTable from '../../components/ui/NTable.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * Вкладка «Записи»: что прислали посетители через форму.
 */
const props = defineProps({ form: { type: [String, Number], required: true } });
const emit = defineEmits(['unread']);

const session = useSession();
const ui = useUi();

const rows = ref([]);
const meta = ref(null);
const loading = ref(true);
const query = reactive({ search: '', unread: false, page: 1 });

const current = ref(null);
const open = ref(false);

const canDelete = computed(() => session.can('forms.submissions.delete'));

const columns = [
    { key: 'id', label: 'ID', muted: true, width: '4rem' },
    { key: 'created_at', label: 'Дата', width: '10rem' },
    { key: 'preview', label: 'Содержание' },
    { key: 'page_url', label: 'Страница', width: '14rem' },
    { key: 'actions', label: '', align: 'right', width: '6rem' },
];

const dateFormat = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' });

function formatDate(value) {
    return value ? dateFormat.format(new Date(value)) : '';
}

function formatSize(bytes) {
    if (!bytes) {
        return '';
    }

    return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} МБ` : `${Math.max(1, Math.round(bytes / 1024))} КБ`;
}

function shortUrl(value) {
    try {
        const parsed = new URL(value);

        return parsed.pathname + parsed.search;
    } catch {
        return value ?? '';
    }
}

async function load() {
    loading.value = true;

    try {
        const data = await api.get(`forms/${props.form}/submissions`, { ...query, unread: query.unread ? 1 : null });

        rows.value = data.data;
        meta.value = data.meta;
        emit('unread', data.meta?.unread ?? 0);
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
    query.unread = false;
    apply();
}

function goToPage(page) {
    query.page = page;
    load();
}

async function show(row) {
    try {
        const data = await api.get(`forms/${props.form}/submissions/${row.id}`);

        current.value = data.data;
        open.value = true;

        if (!row.is_read) {
            row.is_read = true;
            emit('unread', Math.max(0, (meta.value?.unread ?? 1) - 1));

            if (meta.value) {
                meta.value.unread = Math.max(0, meta.value.unread - 1);
            }
        }
    } catch (error) {
        ui.notifyError(error);
    }
}

async function remove(row) {
    const confirmed = await ui.confirm({
        title: 'Удалить запись?',
        message: `Запись #${row.id} будет удалена вместе с файлами.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`forms/${props.form}/submissions/${row.id}`);

        ui.notify(data.message);
        open.value = false;
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-4 border-b border-[var(--surface-border)] p-4">
            <div class="min-w-0 flex-1">
                <NFilters v-model="query.search" placeholder="Поиск по содержимому..."
                          :dirty="Boolean(query.search) || query.unread" @apply="apply" @reset="reset" />
            </div>

            <NToggle v-model="query.unread" label="Только новые" @update:model-value="apply" />
        </div>

        <NEmpty v-if="!loading && rows.length === 0" icon="document" title="Записей нет"
                description="Здесь появится всё, что отправят через форму на сайте." />

        <template v-else>
            <NTable :columns="columns" :rows="rows" :loading="loading">
                <template #cell-created_at="{ row }">
                    <span class="flex items-center gap-1.5 text-xs">
                        <span v-if="!row.is_read" class="size-2 shrink-0 rounded-full bg-brand-600" title="Новая"></span>
                        {{ formatDate(row.created_at) }}
                    </span>
                </template>

                <template #cell-preview="{ row }">
                    <button type="button" :class="['text-left text-sm hover:underline', !row.is_read && 'font-semibold text-[var(--text-strong)]']"
                            @click="show(row)">
                        {{ row.preview || 'пустая запись' }}
                    </button>
                </template>

                <template #cell-page_url="{ row }">
                    <a v-if="row.page_url" :href="row.page_url" target="_blank" rel="noopener"
                       class="block max-w-[14rem] truncate text-xs text-[var(--text-muted)] hover:underline">
                        {{ shortUrl(row.page_url) }}
                    </a>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-0.5">
                        <button type="button" title="Открыть"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
                                @click="show(row)">
                            <NIcon name="eye" size="size-4" />
                        </button>

                        <button v-if="canDelete" type="button" title="Удалить"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="remove(row)">
                            <NIcon name="trash" size="size-4" />
                        </button>
                    </div>
                </template>
            </NTable>

            <NPagination :meta="meta" @change="goToPage" />
        </template>

        <NModal v-model="open" :title="current ? `Запись #${current.id}` : ''" max-width="max-w-2xl">
            <div v-if="current" class="space-y-5">
                <dl class="divide-y divide-[var(--surface-border)] rounded-lg border border-[var(--surface-border)]">
                    <div v-for="field in current.fields" :key="field.code" class="grid gap-1 px-4 py-3 sm:grid-cols-3">
                        <dt class="text-sm text-[var(--text-muted)]">{{ field.label }}</dt>
                        <dd class="text-sm break-words whitespace-pre-line text-[var(--text-strong)] sm:col-span-2">
                            <template v-if="field.type === 'file'">
                                <a v-if="field.file" :href="field.file.url"
                                   class="inline-flex items-center gap-1.5 text-brand-600 hover:underline dark:text-brand-400">
                                    <NIcon name="upload" size="size-4" class="rotate-180" />
                                    {{ field.file.name }}
                                    <span class="text-xs text-[var(--text-muted)]">{{ formatSize(field.file.size) }}</span>
                                </a>
                                <span v-else class="text-[var(--text-faint)]">—</span>
                            </template>
                            <a v-else-if="field.type === 'email' && field.value" :href="`mailto:${field.value}`"
                               class="hover:underline">{{ field.value }}</a>
                            <a v-else-if="field.type === 'phone' && field.value" :href="`tel:${field.value}`"
                               class="hover:underline">{{ field.value }}</a>
                            <template v-else>{{ field.value || '—' }}</template>
                        </dd>
                    </div>
                </dl>

                <dl class="grid gap-x-6 gap-y-2 text-xs text-[var(--text-muted)] sm:grid-cols-2">
                    <div><dt class="inline">Дата:</dt> <dd class="inline">{{ formatDate(current.created_at) }}</dd></div>
                    <div><dt class="inline">IP:</dt> <dd class="inline font-mono">{{ current.ip || '—' }}</dd></div>
                    <div v-if="current.agreement" class="sm:col-span-2">
                        <dt class="inline">Согласие:</dt>
                        <dd class="inline">
                            <NBadge color="green">{{ current.agreement.name || 'соглашение удалено' }}</NBadge>
                            {{ formatDate(current.agreement.agreed_at) }}
                        </dd>
                    </div>
                    <div v-if="current.page_url" class="sm:col-span-2">
                        <dt class="inline">Страница:</dt>
                        <dd class="inline break-all"><a :href="current.page_url" target="_blank" rel="noopener" class="hover:underline">{{ current.page_url }}</a></dd>
                    </div>
                    <div v-if="current.user_agent" class="sm:col-span-2">
                        <dt class="inline">Браузер:</dt> <dd class="inline break-all">{{ current.user_agent }}</dd>
                    </div>
                </dl>
            </div>

            <template #footer>
                <NButton v-if="canDelete && current" variant="danger" size="sm" icon="trash" @click="remove(current)">Удалить</NButton>
                <NButton variant="secondary" size="sm" @click="open = false">Закрыть</NButton>
            </template>
        </NModal>
    </div>
</template>
