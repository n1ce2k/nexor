<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import SectionTree from '../../components/sections/SectionTree.vue';
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

const abilities = computed(() => info.value?.abilities ?? {});

const addLabel = computed(() => info.value?.add_element_label ?? 'Добавить');

/** «Использовать разделы» is the master switch: without it nothing below applies. */
const hasSections = computed(() => Boolean(info.value?.has_sections));

// -------------------------------------------------------------------- вид

const VIEW_KEY = 'admin.elements.view';

const view = ref('split');

/** Плоский список — единственный возможный вид, пока разделы выключены. */
const layout = computed(() => (hasSections.value ? view.value : 'plain'));

function readView() {
    try {
        const stored = localStorage.getItem(`${VIEW_KEY}.${props.iblock}`);

        view.value = stored === 'nested' ? 'nested' : 'split';
    } catch {
        view.value = 'split';
    }
}

function setView(next) {
    view.value = next;

    try {
        localStorage.setItem(`${VIEW_KEY}.${props.iblock}`, next);
    } catch {
        // Приватное окно — вид просто не запомнится.
    }
}

// Дерево рядом со списком просит у макета больше ширины.
watch(layout, (value) => {
    ui.wideContent = value === 'split';
}, { immediate: true });

onUnmounted(() => {
    ui.wideContent = false;
});

// ---------------------------------------------------------------- разделы

const sectionRows = ref([]);
const sectionsLoading = ref(false);
const expanded = reactive({});

/** Плоский список разделов, собранный в дерево по parent_id. */
const tree = computed(() => {
    const nodes = new Map(sectionRows.value.map((section) => [section.id, { ...section, children: [] }]));
    const roots = [];

    nodes.forEach((node) => {
        const parent = node.parent_id ? nodes.get(node.parent_id) : null;

        parent ? parent.children.push(node) : roots.push(node);
    });

    return roots;
});

const selectedSection = computed(() => (typeof query.section === 'number' ? query.section : null));

const selectedName = computed(() => sectionRows.value.find((s) => s.id === selectedSection.value)?.name ?? null);

async function loadSections() {
    if (!hasSections.value) {
        sectionRows.value = [];

        return;
    }

    sectionsLoading.value = true;

    try {
        const data = await api.get(`iblocks/${props.iblock}/sections`);

        sectionRows.value = data.data;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        sectionsLoading.value = false;
    }
}

function toggleSection(id) {
    expanded[id] = !expanded[id];

    if (expanded[id] && layout.value === 'nested') {
        loadBucket(id);
    }
}

function selectSection(id) {
    query.section = id;
    query.page = 1;
    load();
}

function selectAll() {
    query.section = null;
    query.page = 1;
    load();
}

async function removeSection(section) {
    const confirmed = await ui.confirm({
        title: 'Удалить раздел?',
        message: section.children.length
            ? `Раздел «${section.name}» и все вложенные разделы будут удалены.`
            : `Раздел «${section.name}» будет удалён.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`iblocks/${props.iblock}/sections/${section.id}`);

        ui.notify(data.message);

        if (selectedSection.value === section.id) {
            selectAll();
        }

        await loadSections();
        refreshBuckets();
    } catch (error) {
        ui.notifyError(error);
    }
}

// ------------------------------------------------- элементы внутри дерева

/**
 * Элементы раскрытого раздела, по одному запросу на раздел.
 *
 * Ключ — id раздела либо `none` для элементов, которые не лежат ни в одном.
 */
const buckets = reactive({});

function resetBuckets() {
    Object.keys(buckets).forEach((key) => delete buckets[key]);
}

/** Сбрасывает загруженные элементы и сразу набирает заново раскрытые ветки. */
function refreshBuckets() {
    const open = Object.keys(expanded).filter((key) => expanded[key]);

    resetBuckets();

    if (layout.value === 'nested') {
        open.forEach(loadBucket);
    }
}

async function loadBucket(key) {
    if (buckets[key]) {
        return;
    }

    buckets[key] = { rows: [], total: 0, loading: true };

    try {
        const data = await api.get(`iblocks/${props.iblock}/elements`, {
            section: key,
            per_page: 200,
            sort: query.sort,
            direction: query.direction,
        });

        buckets[key] = { rows: data.data, total: data.meta?.total ?? data.data.length, loading: false };
    } catch (error) {
        delete buckets[key];
        ui.notifyError(error);
    }
}

/** Фильтр показывает плоский список: по дереву искать нечего. */
const dirty = computed(() => Boolean(
    query.search || query.status || Object.values(query.prop).some(Boolean),
));

const nested = computed(() => layout.value === 'nested' && !dirty.value);

// Ветку могли раскрыть ещё в боковом дереве, где элементы не нужны, — при
// переходе к общему списку они должны догрузиться.
watch(nested, (on) => {
    if (on) {
        Object.keys(expanded).filter((key) => expanded[key]).forEach(loadBucket);
    }
});

function walk(node, out) {
    out.push({ uid: `s-${node.id}`, kind: 'section', depth: node.depth, section: node });

    if (!expanded[node.id]) {
        return;
    }

    node.children.forEach((child) => walk(child, out));

    const bucket = buckets[node.id];

    if (bucket?.loading) {
        out.push({ uid: `l-${node.id}`, kind: 'loading', depth: node.depth + 1 });

        return;
    }

    (bucket?.rows ?? []).forEach((element) => out.push({
        uid: `e-${node.id}-${element.id}`, kind: 'element', depth: node.depth + 1, element,
    }));

    if (bucket && bucket.total > bucket.rows.length) {
        out.push({ uid: `m-${node.id}`, kind: 'more', depth: node.depth + 1, section: node, total: bucket.total });
    }

    if (bucket && !bucket.rows.length && !node.children.length) {
        out.push({ uid: `z-${node.id}`, kind: 'blank', depth: node.depth + 1 });
    }
}

const looseGroup = computed(() => ({
    id: 'none',
    name: 'Без раздела',
    depth: 0,
    is_active: true,
    children: [],
    elements_count: buckets.none?.total,
}));

const nestedRows = computed(() => {
    const out = [];

    tree.value.forEach((node) => walk(node, out));

    out.push({ uid: 's-none', kind: 'section', depth: 0, section: looseGroup.value, loose: true });

    if (expanded.none) {
        const bucket = buckets.none;

        if (bucket?.loading) {
            out.push({ uid: 'l-none', kind: 'loading', depth: 1 });
        } else {
            (bucket?.rows ?? []).forEach((element) => out.push({
                uid: `e-none-${element.id}`, kind: 'element', depth: 1, element,
            }));

            if (bucket && !bucket.rows.length) {
                out.push({ uid: 'z-none', kind: 'blank', depth: 1 });
            }
        }
    }

    return out;
});

/** Одна таблица на оба вида: строки различаются полем `kind`. */
const tableRows = computed(() => (nested.value
    ? nestedRows.value
    : rows.value.map((element) => ({ uid: `e-${element.id}`, kind: 'element', depth: 0, element }))));

// ---------------------------------------------------------------- таблица

const columns = computed(() => {
    const base = [
        { key: 'sort', label: 'Сорт.', width: '5rem', muted: true, sortable: !nested.value },
        { key: 'name', label: 'Название', sortable: !nested.value },
    ];

    // В дереве раздел виден по вложенности — отдельная колонка ни к чему.
    if (hasSections.value && !nested.value) {
        base.push({ key: 'section', label: 'Раздел', muted: true });
    }

    listProperties.value.forEach((property) => {
        base.push({ key: `prop:${property.code}`, label: property.name, muted: true });
    });

    base.push(
        { key: 'is_active', label: 'Статус', align: 'center', width: '7rem' },
        { key: 'updated_at', label: 'Изменён', width: '9rem', muted: true, sortable: !nested.value },
        { key: 'actions', label: 'Действия', align: 'right', width: '9rem' },
    );

    return base;
});

function customCell(code) {
    return resolveColumn(info.value?.code, code);
}

function indent(depth) {
    return { paddingLeft: `${depth * 1.5}rem` };
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
    if (nested.value) {
        return;
    }

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
    query.status = null;
    query.prop = {};
    apply();
}

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
        refreshBuckets();
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' }) : '';
}

async function boot() {
    readView();
    await loadSchema();
    await loadSections();
    load();
}

// Switching infoblocks reuses the same route component.
watch(() => props.iblock, async () => {
    query.search = '';
    query.section = null;
    query.status = null;
    query.prop = {};
    query.page = 1;
    schema.value = null;
    Object.keys(expanded).forEach((key) => delete expanded[key]);
    resetBuckets();
    await boot();
});

onMounted(boot);
</script>

<template>
    <div>
        <NPageHeader :title="info?.name ?? 'Элементы'"
                     :description="info?.description || 'Наполнение инфоблока'"
                     :breadcrumbs="[{ label: info?.type?.name ?? 'Контент' }, { label: info?.name ?? '' }]">
            <template #actions>
                <div v-if="hasSections" class="mr-1 flex items-center rounded-lg border border-[var(--surface-border-strong)] p-0.5">
                    <button type="button" title="Дерево слева, элементы справа"
                            :class="['rounded-md p-1.5 transition',
                                     view === 'split'
                                         ? 'bg-[var(--surface-muted)] text-[var(--text-strong)]'
                                         : 'text-[var(--text-muted)] hover:text-[var(--text-strong)]']"
                            @click="setView('split')">
                        <NIcon name="panel-left" size="size-4" />
                    </button>

                    <button type="button" title="Разделы и элементы одним списком"
                            :class="['rounded-md p-1.5 transition',
                                     view === 'nested'
                                         ? 'bg-[var(--surface-muted)] text-[var(--text-strong)]'
                                         : 'text-[var(--text-muted)] hover:text-[var(--text-strong)]']"
                            @click="setView('nested')">
                        <NIcon name="list-tree" size="size-4" />
                    </button>
                </div>

                <NButton v-if="session.can('iblocks.update')" variant="secondary" icon="grip"
                         :to="{ name: 'properties.index', params: { iblock } }">
                    Свойства
                </NButton>

                <NButton v-if="hasSections" variant="secondary" icon="folder"
                         :to="{ name: 'sections.index', params: { iblock } }">
                    Разделы
                </NButton>

                <NButton v-if="abilities.create" icon="plus"
                         :to="{ name: 'elements.create', params: { iblock } }">
                    {{ addLabel }}
                </NButton>

                <NButton v-if="hasSections && abilities.create" variant="secondary" icon="folder"
                         :to="{ name: 'sections.create', params: { iblock } }">
                    Добавить раздел
                </NButton>
            </template>
        </NPageHeader>

        <div :class="layout === 'split' ? 'flex flex-col gap-6 lg:flex-row lg:items-start' : ''">
            <!-- Дерево разделов -->
            <NCard v-if="layout === 'split'" :padding="false"
                   class="w-full shrink-0 lg:sticky lg:top-6 lg:w-72 xl:w-80">
                <div class="border-b border-[var(--surface-border)] px-3 py-2.5">
                    <button type="button"
                            :class="['flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium transition',
                                     selectedSection === null
                                         ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'
                                         : 'text-[var(--text-strong)] hover:bg-[var(--surface-muted)]']"
                            @click="selectAll">
                        <NIcon name="layers" size="size-4 shrink-0" />
                        <span class="min-w-0 flex-1 truncate text-left">Все элементы</span>
                        <span class="shrink-0 text-xs text-[var(--text-faint)]">{{ meta?.total ?? '' }}</span>
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto p-2">
                    <SectionTree v-if="tree.length" :nodes="tree" :selected="selectedSection" :expanded="expanded"
                                 :iblock="iblock" :abilities="abilities"
                                 @select="selectSection" @toggle="toggleSection" @remove="removeSection" />

                    <p v-else-if="!sectionsLoading" class="px-2 py-6 text-center text-xs text-[var(--text-muted)]">
                        Разделов пока нет.
                    </p>
                </div>

                <div v-if="abilities.create" class="border-t border-[var(--surface-border)] px-3 py-2.5">
                    <router-link :to="{ name: 'sections.create', params: { iblock } }"
                                 class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 transition hover:underline dark:text-brand-400">
                        <NIcon name="plus" size="size-3.5" />
                        Добавить раздел
                    </router-link>
                </div>
            </NCard>

            <!-- Элементы -->
            <NCard :padding="false" class="min-w-0 flex-1">
                <div class="border-b border-[var(--surface-border)] p-4">
                    <NFilters v-model="query.search" placeholder="Название или код..." :dirty="dirty"
                              @apply="apply" @reset="reset">
                        <NSelect v-model="query.status" class="w-auto" placeholder="Любой статус"
                                 :options="[{ value: 'active', label: 'Активные' }, { value: 'hidden', label: 'Скрытые' }]" />

                        <template v-for="property in filterProperties" :key="property.id">
                            <NSelect v-if="property.type === 'select'" v-model="query.prop[property.code]"
                                     class="w-auto" :placeholder="property.name"
                                     :options="property.enums.map((e) => ({ value: e.id, label: e.value }))" />
                            <NInput v-else v-model="query.prop[property.code]" :placeholder="property.name" class="w-auto" />
                        </template>
                    </NFilters>

                    <p v-if="layout === 'nested' && dirty" class="mt-3 text-xs text-[var(--text-muted)]">
                        Пока включён фильтр, элементы показаны плоским списком — по дереву искать нечего.
                    </p>

                    <p v-else-if="layout === 'split' && selectedName" class="mt-3 flex items-center gap-2 text-xs text-[var(--text-muted)]">
                        Раздел: <span class="font-medium text-[var(--text-strong)]">{{ selectedName }}</span>
                        <button type="button" class="text-brand-600 hover:underline dark:text-brand-400"
                                @click="selectAll">
                            показать все
                        </button>
                    </p>
                </div>

                <NEmpty v-if="!loading && !nested && rows.length === 0" icon="document" title="Элементов нет"
                        description="Добавьте первую запись в этот инфоблок.">
                    <NButton v-if="abilities.create" icon="plus" :to="{ name: 'elements.create', params: { iblock } }">
                        {{ addLabel }}
                    </NButton>
                </NEmpty>

                <template v-else>
                    <NTable :columns="columns" :rows="tableRows" row-key="uid" :sort="query.sort"
                            :direction="query.direction" :loading="loading" @sort="sort">
                        <template #cell-sort="{ row }">
                            <span v-if="row.kind === 'element'" class="font-mono text-xs">{{ row.element.sort }}</span>
                            <span v-else-if="row.kind === 'section' && !row.loose" class="font-mono text-xs">
                                {{ row.section.sort }}
                            </span>
                        </template>

                        <template #cell-name="{ row }">
                            <!-- Раздел -->
                            <div v-if="row.kind === 'section'" class="flex items-center gap-2" :style="indent(row.depth)">
                                <button type="button"
                                        class="shrink-0 rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]"
                                        :title="expanded[row.section.id] ? 'Свернуть' : 'Развернуть'"
                                        @click="toggleSection(row.section.id)">
                                    <NIcon name="chevron-right" size="size-3.5 transition"
                                           :class="expanded[row.section.id] && 'rotate-90'" />
                                </button>

                                <NIcon name="folder" size="size-4 shrink-0 text-[var(--text-faint)]" />

                                <router-link v-if="!row.loose"
                                             :to="{ name: 'sections.edit', params: { iblock, section: row.section.id } }"
                                             class="truncate font-semibold text-[var(--text-strong)] hover:text-brand-600 hover:underline">
                                    {{ row.section.name }}
                                </router-link>

                                <span v-else class="truncate font-semibold text-[var(--text-muted)]">
                                    {{ row.section.name }}
                                </span>

                                <span class="shrink-0 text-xs text-[var(--text-faint)]">
                                    {{ row.section.elements_count ?? '' }}
                                </span>
                            </div>

                            <!-- Элемент -->
                            <div v-else-if="row.kind === 'element'" class="flex items-center gap-3" :style="indent(row.depth)">
                                <img v-if="row.element.preview_picture_url" :src="row.element.preview_picture_url" alt=""
                                     class="size-9 shrink-0 rounded object-cover">

                                <div class="min-w-0">
                                    <router-link :to="{ name: 'elements.edit', params: { iblock, element: row.element.id } }"
                                                 class="truncate font-medium text-[var(--text-strong)] hover:text-brand-600 hover:underline">
                                        {{ row.element.name }}
                                    </router-link>
                                    <p v-if="row.element.code" class="truncate text-xs text-[var(--text-muted)]">
                                        <code class="font-mono">{{ row.element.code }}</code>
                                    </p>
                                </div>
                            </div>

                            <p v-else-if="row.kind === 'loading'" class="text-xs text-[var(--text-muted)]"
                               :style="indent(row.depth)">
                                Загружаем…
                            </p>

                            <p v-else-if="row.kind === 'blank'" class="text-xs text-[var(--text-faint)]"
                               :style="indent(row.depth)">
                                Пусто
                            </p>

                            <button v-else-if="row.kind === 'more'" type="button"
                                    class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400"
                                    :style="indent(row.depth)"
                                    @click="setView('split'); selectSection(row.section.id)">
                                Показать все {{ row.total }} — открыть раздел
                            </button>
                        </template>

                        <template #cell-section="{ row }">
                            <span v-if="row.kind === 'element'" class="text-xs">
                                {{ row.element.section?.name ?? '—' }}
                            </span>
                        </template>

                        <template v-for="property in listProperties" :key="property.id"
                                  #[`cell-prop:${property.code}`]="{ row }">
                            <template v-if="row.kind === 'element'">
                                <component v-if="customCell(property.code)" :is="customCell(property.code)"
                                           :row="row.element" :property="property" />

                                <span v-else-if="property.type === 'color'" class="inline-flex items-center gap-1.5">
                                    <span class="size-4 rounded border border-[var(--surface-border)]"
                                          :style="{ backgroundColor: row.element.display?.[property.code] }"></span>
                                    <code class="font-mono text-xs">{{ row.element.display?.[property.code] }}</code>
                                </span>

                                <span v-else class="text-xs">{{ row.element.display?.[property.code] ?? '—' }}</span>
                            </template>
                        </template>

                        <template #cell-is_active="{ row }">
                            <NBadge v-if="row.kind === 'element'" :color="row.element.is_active ? 'green' : 'gray'">
                                {{ row.element.is_active ? 'активен' : 'скрыт' }}
                            </NBadge>

                            <NBadge v-else-if="row.kind === 'section' && !row.loose"
                                    :color="row.section.is_active ? 'green' : 'gray'">
                                {{ row.section.is_active ? 'активен' : 'скрыт' }}
                            </NBadge>
                        </template>

                        <template #cell-updated_at="{ row }">
                            <span v-if="row.kind === 'element'" class="text-xs">
                                {{ formatDate(row.element.updated_at) }}
                            </span>
                        </template>

                        <template #cell-actions="{ row }">
                            <div v-if="row.kind === 'element'" class="flex items-center justify-end gap-0.5">
                                <router-link v-if="abilities.update"
                                             :to="{ name: 'elements.edit', params: { iblock, element: row.element.id } }"
                                             title="Изменить"
                                             class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                    <NIcon name="pencil" size="size-4" />
                                </router-link>

                                <button v-if="abilities.delete" type="button" title="Удалить"
                                        class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                        @click="remove(row.element)">
                                    <NIcon name="trash" size="size-4" />
                                </button>
                            </div>

                            <div v-else-if="row.kind === 'section' && !row.loose"
                                 class="flex items-center justify-end gap-0.5 opacity-0 transition group-hover:opacity-100">
                                <router-link v-if="abilities.create"
                                             :to="{ name: 'sections.create', params: { iblock }, query: { parent: row.section.id } }"
                                             title="Добавить вложенный раздел"
                                             class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                    <NIcon name="plus" size="size-4" />
                                </router-link>

                                <router-link v-if="abilities.update"
                                             :to="{ name: 'sections.edit', params: { iblock, section: row.section.id } }"
                                             title="Изменить раздел"
                                             class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                    <NIcon name="pencil" size="size-4" />
                                </router-link>

                                <button v-if="abilities.delete" type="button" title="Удалить раздел"
                                        class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                        @click="removeSection(row.section)">
                                    <NIcon name="trash" size="size-4" />
                                </button>
                            </div>
                        </template>
                    </NTable>

                    <NPagination v-if="!nested" :meta="meta" @change="goToPage" />
                </template>
            </NCard>
        </div>
    </div>
</template>
