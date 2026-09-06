<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NFilters from '../../components/ui/NFilters.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NPagination from '../../components/ui/NPagination.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NTable from '../../components/ui/NTable.vue';
import { api } from '../../api';
import { resolveColumn } from '../../registry';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

const props = defineProps({ iblock: { type: [String, Number], required: true } });

const session = useSession();
const ui = useUi();

const rows = ref([]);
const meta = ref(null);
const schema = ref(null);
const loading = ref(true);

const query = reactive({ search: '', section: null, status: null, prop: {}, page: 1, sort: 'sort', direction: 'asc' });

const info = computed(() => schema.value?.iblock ?? session.iblock(props.iblock));

const listProperties = computed(() => (schema.value?.properties ?? []).filter((p) => p.is_shown_in_list));

const filterProperties = computed(() => (schema.value?.properties ?? []).filter((p) => p.is_filterable));

const sections = computed(() => (schema.value?.sections ?? []).map((section) => ({
    value: section.id,
    label: section.indented_name,
})));

const abilities = computed(() => info.value?.abilities ?? {});

/** «Добавить товар» when the infoblock named its elements, «Добавить» otherwise. */
const addLabel = computed(() => info.value?.add_element_label ?? 'Добавить');

const columns = computed(() => {
    const base = [
        { key: 'sort', label: 'Сорт.', width: '5rem', muted: true, sortable: true },
        { key: 'name', label: 'Название', sortable: true },
    ];

    if (info.value?.has_sections) {
        base.push({ key: 'section', label: 'Раздел', muted: true });
    }

    listProperties.value.forEach((property) => {
        base.push({ key: `prop:${property.code}`, label: property.name, muted: true });
    });

    base.push(
        { key: 'is_active', label: 'Статус', align: 'center', width: '7rem' },
        { key: 'updated_at', label: 'Изменён', width: '9rem', muted: true, sortable: true },
        { key: 'actions', label: 'Действия', align: 'right', width: '7rem' },
    );

    return base;
});

function customCell(code) {
    return resolveColumn(info.value?.code, code);
}

async function loadSchema() {
    try {
        schema.value = await api.get(`iblocks/${props.iblock}/schema`);
    } catch (error) {
        ui.notifyError(error);
    }
}

async function load() {
    loading.value = true;

    try {
        const data = await api.get(`iblocks/${props.iblock}/elements`, query);

        rows.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function sort(key) {
    query.direction = query.sort === key && query.direction === 'asc' ? 'desc' : 'asc';
    query.sort = key;
    load();
}

function apply() {
    query.page = 1;
    load();
}

function reset() {
    query.search = '';
    query.section = null;
    query.status = null;
    query.prop = {};
    apply();
}

const dirty = computed(() => Boolean(
    query.search || query.section || query.status || Object.values(query.prop).some(Boolean),
));

function goToPage(page) {
    query.page = page;
    load();
}

async function remove(element) {
    const confirmed = await ui.confirm({
        title: 'Удалить элемент?',
        message: `Элемент «${element.name}» будет удалён.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`iblocks/${props.iblock}/elements/${element.id}`);

        ui.notify(data.message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' }) : '';
}

// Switching infoblocks reuses the same route component.
watch(() => props.iblock, async () => {
    reset();
    schema.value = null;
    await loadSchema();
    load();
});

onMounted(async () => {
    await loadSchema();
    load();
});
</script>

<template>
    <div>
        <NPageHeader :title="info?.name ?? 'Элементы'"
                     :description="info?.description || 'Наполнение инфоблока'"
                     :breadcrumbs="[{ label: info?.type?.name ?? 'Контент' }, { label: info?.name ?? '' }]">
            <template #actions>
                <NButton v-if="session.can('iblocks.update')" variant="secondary" icon="grip"
                         :to="{ name: 'properties.index', params: { iblock } }">
                    Свойства
                </NButton>

                <NButton v-if="info?.has_sections" variant="secondary" icon="folder"
                         :to="{ name: 'sections.index', params: { iblock } }">
                    Разделы
                </NButton>

                <NButton v-if="abilities.create" icon="plus"
                         :to="{ name: 'elements.create', params: { iblock } }">
                    {{ addLabel }}
                </NButton>

                <NButton v-if="info?.has_sections && abilities.create" variant="secondary" icon="folder"
                         :to="{ name: 'sections.create', params: { iblock } }">
                    Добавить раздел
                </NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <div class="border-b border-[var(--surface-border)] p-4">
                <NFilters v-model="query.search" placeholder="Название или код..." :dirty="dirty"
                          @apply="apply" @reset="reset">
                    <NSelect v-if="sections.length" v-model="query.section" :options="sections"
                             placeholder="Все разделы" class="w-auto" />

                    <NSelect v-model="query.status" class="w-auto" placeholder="Любой статус"
                             :options="[{ value: 'active', label: 'Активные' }, { value: 'hidden', label: 'Скрытые' }]" />

                    <template v-for="property in filterProperties" :key="property.id">
                        <NSelect v-if="property.type === 'select'" v-model="query.prop[property.code]"
                                 class="w-auto" :placeholder="property.name"
                                 :options="property.enums.map((e) => ({ value: e.id, label: e.value }))" />
                        <NInput v-else v-model="query.prop[property.code]" :placeholder="property.name" class="w-auto" />
                    </template>
                </NFilters>
            </div>

            <NEmpty v-if="!loading && rows.length === 0" icon="document" title="Элементов нет"
                    description="Добавьте первую запись в этот инфоблок.">
                <NButton v-if="abilities.create" icon="plus" :to="{ name: 'elements.create', params: { iblock } }">
                    {{ addLabel }}
                </NButton>
            </NEmpty>

            <template v-else>
                <NTable :columns="columns" :rows="rows" :sort="query.sort" :direction="query.direction"
                        :loading="loading" @sort="sort">
                    <template #cell-sort="{ row }">
                        <span class="font-mono text-xs">{{ row.sort }}</span>
                    </template>

                    <template #cell-name="{ row }">
                        <div class="flex items-center gap-3">
                            <img v-if="row.preview_picture_url" :src="row.preview_picture_url" alt=""
                                 class="size-9 shrink-0 rounded object-cover">

                            <div class="min-w-0">
                                <router-link :to="{ name: 'elements.edit', params: { iblock, element: row.id } }"
                                             class="truncate font-medium text-[var(--text-strong)] hover:text-brand-600 hover:underline">
                                    {{ row.name }}
                                </router-link>
                                <p v-if="row.code" class="truncate text-xs text-[var(--text-muted)]">
                                    <code class="font-mono">{{ row.code }}</code>
                                </p>
                            </div>
                        </div>
                    </template>

                    <template #cell-section="{ row }">
                        <span class="text-xs">{{ row.section?.name ?? '—' }}</span>
                    </template>

                    <template v-for="property in listProperties" :key="property.id"
                              #[`cell-prop:${property.code}`]="{ row }">
                        <component v-if="customCell(property.code)" :is="customCell(property.code)"
                                   :row="row" :property="property" />

                        <span v-else-if="property.type === 'color'" class="inline-flex items-center gap-1.5">
                            <span class="size-4 rounded border border-[var(--surface-border)]"
                                  :style="{ backgroundColor: row.display?.[property.code] }"></span>
                            <code class="font-mono text-xs">{{ row.display?.[property.code] }}</code>
                        </span>

                        <span v-else class="text-xs">{{ row.display?.[property.code] ?? '—' }}</span>
                    </template>

                    <template #cell-is_active="{ row }">
                        <NBadge :color="row.is_active ? 'green' : 'gray'">
                            {{ row.is_active ? 'активен' : 'скрыт' }}
                        </NBadge>
                    </template>

                    <template #cell-updated_at="{ row }">
                        <span class="text-xs">{{ formatDate(row.updated_at) }}</span>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-0.5">
                            <router-link v-if="abilities.update"
                                         :to="{ name: 'elements.edit', params: { iblock, element: row.id } }"
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

                <NPagination :meta="meta" @change="goToPage" />
            </template>
        </NCard>
    </div>
</template>
