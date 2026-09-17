<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NFilters from '../../components/ui/NFilters.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NPagination from '../../components/ui/NPagination.vue';
import NTable from '../../components/ui/NTable.vue';
import { api } from '../../api';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

const session = useSession();
const ui = useUi();

const rows = ref([]);
const meta = ref(null);
const loading = ref(true);
const query = reactive({ search: '', page: 1 });

const canCreate = computed(() => session.can('agreements.create'));
const canEdit = computed(() => session.can('agreements.update'));
const canDelete = computed(() => session.can('agreements.delete'));

const columns = [
    { key: 'id', label: 'ID', muted: true, width: '4rem' },
    { key: 'name', label: 'Соглашение' },
    { key: 'label', label: 'Как выглядит у формы' },
    { key: 'forms_count', label: 'Форм', align: 'center', width: '6rem' },
    { key: 'is_active', label: 'Статус', align: 'center', width: '7rem' },
    { key: 'actions', label: 'Действия', align: 'right', width: '9rem' },
];

async function load() {
    loading.value = true;

    try {
        const data = await api.get('agreements', query);

        rows.value = data.data;
        meta.value = data.meta;
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
    apply();
}

function goToPage(page) {
    query.page = page;
    load();
}

async function remove(agreement) {
    const confirmed = await ui.confirm({
        title: 'Удалить соглашение?',
        message: agreement.forms_count
            ? `Соглашение «${agreement.name}» стоит в формах (${agreement.forms_count}) — они останутся без галочки.`
            : `Соглашение «${agreement.name}» будет удалено.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`agreements/${agreement.id}`);

        ui.notify(data.message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(load);
</script>

<template>
    <div>
        <NPageHeader title="Соглашения"
                     description="Галочки согласия у форм: текст у галочки и полный текст соглашения.">
            <template #actions>
                <NButton v-if="session.can('forms.view')" variant="secondary" icon="document"
                         :to="{ name: 'forms.index' }">Формы ОС</NButton>
                <NButton v-if="canCreate" icon="plus" :to="{ name: 'agreements.create' }">Добавить соглашение</NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <div class="border-b border-[var(--surface-border)] p-4">
                <NFilters v-model="query.search" placeholder="Название или код..." :dirty="Boolean(query.search)"
                          @apply="apply" @reset="reset" />
            </div>

            <NEmpty v-if="!loading && rows.length === 0" icon="check" title="Соглашений нет"
                    description="Например, «Согласие на обработку персональных данных».">
                <NButton v-if="canCreate" icon="plus" :to="{ name: 'agreements.create' }">Создать соглашение</NButton>
            </NEmpty>

            <template v-else>
                <NTable :columns="columns" :rows="rows" :loading="loading">
                    <template #cell-name="{ row }">
                        <router-link v-if="canEdit" :to="{ name: 'agreements.edit', params: { agreement: row.id } }"
                                     class="font-medium text-[var(--text-strong)] hover:underline">{{ row.name }}</router-link>
                        <p v-else class="font-medium text-[var(--text-strong)]">{{ row.name }}</p>
                        <code class="mt-0.5 block font-mono text-xs text-[var(--text-muted)]">{{ row.code }}</code>
                    </template>

                    <template #cell-label="{ row }">
                        <span class="flex items-start gap-2 text-xs">
                            <span class="mt-px size-3.5 shrink-0 rounded border border-[var(--surface-border)]"></span>
                            <span>{{ row.label_parts.before }}<span class="text-brand-600 underline dark:text-brand-400">{{ row.label_parts.link }}</span>{{ row.label_parts.after }}</span>
                        </span>
                    </template>

                    <template #cell-is_active="{ row }">
                        <NBadge :color="row.is_active ? 'green' : 'gray'">
                            {{ row.is_active ? 'активно' : 'выключено' }}
                        </NBadge>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-0.5">
                            <a :href="row.url" target="_blank" rel="noopener" title="Открыть на сайте"
                               class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                <NIcon name="eye" size="size-4" />
                            </a>

                            <router-link v-if="canEdit" :to="{ name: 'agreements.edit', params: { agreement: row.id } }"
                                         title="Изменить"
                                         class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                <NIcon name="pencil" size="size-4" />
                            </router-link>

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
        </NCard>
    </div>
</template>
