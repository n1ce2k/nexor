<script setup>
import { onMounted, reactive, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NFilters from '../../components/ui/NFilters.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NPagination from '../../components/ui/NPagination.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NTable from '../../components/ui/NTable.vue';
import { api } from '../../api';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

const session = useSession();
const ui = useUi();

const rows = ref([]);
const meta = ref(null);
const types = ref([]);
const loading = ref(true);

const query = reactive({ search: '', type: null, page: 1, sort: 'sort', direction: 'asc' });

const columns = [
    { key: 'name', label: 'Инфоблок', sortable: true },
    { key: 'type', label: 'Тип', muted: true },
    { key: 'properties_count', label: 'Свойств', align: 'center', width: '7rem' },
    { key: 'sections_count', label: 'Разделов', align: 'center', width: '7rem' },
    { key: 'elements_count', label: 'Элементов', align: 'center', width: '8rem' },
    { key: 'actions', label: 'Действия', align: 'right', width: '12rem' },
];

async function load() {
    loading.value = true;

    try {
        const data = await api.get('iblocks', query);

        rows.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

async function loadTypes() {
    try {
        const data = await api.get('iblock-types', { per_page: 200 });

        types.value = data.data.map((type) => ({ value: type.id, label: type.name }));
    } catch {
        types.value = [];
    }
}

function sort(key) {
    query.direction = query.sort === key && query.direction === 'asc' ? 'desc' : 'asc';
    query.sort = key;
    load();
}

function goToPage(page) {
    query.page = page;
    load();
}

function apply() {
    query.page = 1;
    load();
}

function reset() {
    query.search = '';
    query.type = null;
    apply();
}

async function remove(iblock) {
    const confirmed = await ui.confirm({
        title: 'Удалить инфоблок?',
        message: `Инфоблок «${iblock.name}» и все его элементы будут удалены.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`iblocks/${iblock.id}`);

        ui.notify(data.message);
        await Promise.all([load(), session.refreshIblocks()]);
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(() => {
    load();
    loadTypes();
});
</script>

<template>
    <div>
        <NPageHeader title="Инфоблоки"
                     description="Структура данных сайта: наборы элементов со своими свойствами.">
            <template #actions>
                <NButton v-if="session.can('iblocks.create')" :to="{ name: 'iblocks.create' }" icon="plus">
                    Создать инфоблок
                </NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <div class="border-b border-[var(--surface-border)] p-4">
                <NFilters v-model="query.search" placeholder="Название или код..."
                          :dirty="Boolean(query.search || query.type)"
                          @apply="apply" @reset="reset">
                    <NSelect v-model="query.type" :options="types" placeholder="Все типы" class="w-auto" />
                </NFilters>
            </div>

            <NEmpty v-if="!loading && rows.length === 0" icon="layers" title="Инфоблоков нет"
                    description="Инфоблок — это набор однотипных записей: новости, товары, палитра цветов.">
                <NButton v-if="session.can('iblocks.create')" :to="{ name: 'iblocks.create' }" icon="plus">
                    Создать инфоблок
                </NButton>
            </NEmpty>

            <template v-else>
                <NTable :columns="columns" :rows="rows" :sort="query.sort" :direction="query.direction"
                        :loading="loading" @sort="sort">
                    <template #cell-name="{ row }">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-[var(--text-strong)]">{{ row.name }}</p>
                            <NBadge v-if="!row.is_active">выключен</NBadge>
                        </div>
                        <p class="mt-0.5 text-xs text-[var(--text-muted)]">
                            код <code class="font-mono">{{ row.code }}</code>
                        </p>
                    </template>

                    <template #cell-type="{ row }">{{ row.type?.name ?? '—' }}</template>

                    <template #cell-properties_count="{ row }">
                        <router-link :to="{ name: 'properties.index', params: { iblock: row.id } }"
                                     class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                            {{ row.properties_count }}
                        </router-link>
                    </template>

                    <template #cell-sections_count="{ row }">
                        <span class="text-[var(--text-muted)]">{{ row.has_sections ? row.sections_count : '—' }}</span>
                    </template>

                    <template #cell-elements_count="{ row }">
                        <router-link :to="{ name: 'elements.index', params: { iblock: row.id } }"
                                     class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                            {{ row.elements_count }}
                        </router-link>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-0.5">
                            <router-link :to="{ name: 'elements.index', params: { iblock: row.id } }" title="Наполнение"
                                         class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                <NIcon name="document" size="size-4" />
                            </router-link>

                            <router-link v-if="session.can('iblocks.update')"
                                         :to="{ name: 'properties.index', params: { iblock: row.id } }" title="Свойства"
                                         class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                <NIcon name="grip" size="size-4" />
                            </router-link>

                            <router-link v-if="session.can('iblocks.update')"
                                         :to="{ name: 'iblocks.edit', params: { iblock: row.id } }" title="Настройки"
                                         class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                <NIcon name="pencil" size="size-4" />
                            </router-link>

                            <button v-if="session.can('iblocks.delete')" type="button" title="Удалить"
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
