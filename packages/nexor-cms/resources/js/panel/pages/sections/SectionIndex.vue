<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NFilters from '../../components/ui/NFilters.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NTable from '../../components/ui/NTable.vue';
import { api } from '../../api';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * Sections of one infoblock, as the tree they are.
 *
 * The API returns the whole tree already ordered by its materialised path, so
 * the table only has to indent by depth — the nesting is visible at a glance
 * the way it is in Bitrix, without a folder-by-folder walk.
 */
const props = defineProps({ iblock: { type: [String, Number], required: true } });

const session = useSession();
const ui = useUi();

const rows = ref([]);
const loading = ref(true);

const query = reactive({ search: '' });

const info = computed(() => session.iblock(props.iblock));

const abilities = computed(() => info.value?.abilities ?? {});

const columns = [
    { key: 'id', label: 'ID', width: '4.5rem', muted: true },
    { key: 'sort', label: 'Сорт.', width: '5rem', muted: true },
    { key: 'name', label: 'Название' },
    { key: 'elements_count', label: 'Элементов', align: 'center', width: '8rem', muted: true },
    { key: 'is_active', label: 'Статус', align: 'center', width: '7rem' },
    { key: 'actions', label: 'Действия', align: 'right', width: '9rem' },
];

async function load() {
    loading.value = true;

    try {
        const data = await api.get(`iblocks/${props.iblock}/sections`, query.search ? { search: query.search } : {});

        rows.value = data.data;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function reset() {
    query.search = '';
    load();
}

async function remove(section) {
    const nested = section.children_count > 0;

    const confirmed = await ui.confirm({
        title: 'Удалить раздел?',
        message: nested
            ? `Раздел «${section.name}» и все вложенные разделы будут удалены.`
            : `Раздел «${section.name}» будет удалён.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`iblocks/${props.iblock}/sections/${section.id}`);

        ui.notify(data.message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

watch(() => props.iblock, () => {
    query.search = '';
    load();
});

onMounted(load);
</script>

<template>
    <div>
        <NPageHeader title="Разделы"
                     :description="info ? `Структура инфоблока «${info.name}»` : 'Структура инфоблока'"
                     :back="{ name: 'elements.index', params: { iblock } }"
                     :breadcrumbs="[
                         { label: info?.name ?? '', to: { name: 'elements.index', params: { iblock } } },
                         { label: 'Разделы' },
                     ]">
            <template #actions>
                <NButton variant="secondary" icon="document" :to="{ name: 'elements.index', params: { iblock } }">
                    Наполнение
                </NButton>

                <NButton v-if="abilities.create" icon="plus" :to="{ name: 'sections.create', params: { iblock } }">
                    Добавить раздел
                </NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <div class="border-b border-[var(--surface-border)] p-4">
                <NFilters v-model="query.search" placeholder="Название раздела..." :dirty="Boolean(query.search)"
                          @apply="load" @reset="reset" />
            </div>

            <NEmpty v-if="!loading && rows.length === 0" icon="folder" title="Разделов нет"
                    description="Разделы задают структуру инфоблока — элементы раскладываются по ним.">
                <NButton v-if="abilities.create" icon="plus" :to="{ name: 'sections.create', params: { iblock } }">
                    Добавить раздел
                </NButton>
            </NEmpty>

            <NTable v-else :columns="columns" :rows="rows" :loading="loading">
                <template #cell-id="{ row }">
                    <span class="font-mono text-xs">{{ row.id }}</span>
                </template>

                <template #cell-sort="{ row }">
                    <span class="font-mono text-xs">{{ row.sort }}</span>
                </template>

                <template #cell-name="{ row }">
                    <div class="flex items-center gap-3" :style="{ paddingLeft: `${row.depth * 1.25}rem` }">
                        <img v-if="row.picture_url" :src="row.picture_url" alt=""
                             class="size-9 shrink-0 rounded object-cover">

                        <span v-else class="flex size-9 shrink-0 items-center justify-center rounded bg-[var(--surface-muted)] text-[var(--text-faint)]">
                            <NIcon name="folder" size="size-4" />
                        </span>

                        <div class="min-w-0">
                            <router-link :to="{ name: 'sections.edit', params: { iblock, section: row.id } }"
                                         class="truncate font-medium text-[var(--text-strong)] hover:text-brand-600 hover:underline">
                                {{ row.name }}
                            </router-link>
                            <p v-if="row.code" class="truncate text-xs text-[var(--text-muted)]">
                                <code class="font-mono">{{ row.code }}</code>
                            </p>
                        </div>
                    </div>
                </template>

                <template #cell-elements_count="{ row }">
                    <span class="text-xs">{{ row.elements_count ?? 0 }}</span>
                </template>

                <template #cell-is_active="{ row }">
                    <NBadge :color="row.is_active ? 'green' : 'gray'">
                        {{ row.is_active ? 'активен' : 'скрыт' }}
                    </NBadge>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-0.5">
                        <router-link v-if="abilities.create"
                                     :to="{ name: 'sections.create', params: { iblock }, query: { parent: row.id } }"
                                     title="Добавить вложенный раздел"
                                     class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                            <NIcon name="plus" size="size-4" />
                        </router-link>

                        <router-link v-if="abilities.update"
                                     :to="{ name: 'sections.edit', params: { iblock, section: row.id } }"
                                     title="Изменить"
                                     class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                            <NIcon name="pencil" size="size-4" />
                        </router-link>

                        <button v-if="abilities.delete" type="button" title="Удалить"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="remove(row)">
                            <NIcon name="trash" size="size-4" />
                        </button>
                    </div>
                </template>
            </NTable>
        </NCard>
    </div>
</template>
