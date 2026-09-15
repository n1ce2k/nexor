<script setup>
import { computed, onMounted, ref, toRaw, watch } from 'vue';
import { api, blockId, Draggable, NInput, NToggle, useUi } from '../core.js';
import { resolveEditor } from '../editors.js';
import BlockIcon from './BlockIcon.vue';

/**
 * Вкладка «Конструктор» в форме элемента.
 *
 * Слева палитра: блок перетаскивается в страницу или добавляется кликом в
 * конец. Справа сама страница — блоки меняются местами перетаскиванием за
 * ручку, сворачиваются, дублируются и удаляются.
 *
 * Значение — документ `{ version, settings, blocks }`; форма элемента отправит
 * его JSON-строкой вместе с остальными полями по «Сохранить».
 */
const props = defineProps({
    modelValue: { type: Object, default: null },
    iblock: { type: Object, default: null },
    element: { type: [String, Number], default: null },
    label: { type: String, default: '' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const ui = useUi();

const palette = ref([]);
const blocks = ref([]);

/** Параметры всей раскладки: боковое оглавление со ссылками на блоки. */
const settings = ref({ showSidebar: false, sidebarItems: [] });
const collapsed = ref(new Set());
const loading = ref(true);

let catalogue = null;

/** Палитра одна на все формы — грузится один раз за сессию панели. */
function loadCatalogue() {
    catalogue ??= api.get('pagebuilder/blocks', { surface: 'detail' }).then((data) => data.data);

    return catalogue;
}

const definitions = ref({});

function definition(type) {
    return definitions.value[type] ?? { type, label: type, icon: 'document' };
}

function createBlock(item) {
    return { id: blockId(), type: item.type, data: structuredClone(toRaw(item.defaults ?? {})) };
}

function add(item) {
    const block = createBlock(item);

    blocks.value.push(block);

    // Новый блок сразу в поле зрения — иначе на длинной странице его не найти.
    requestAnimationFrame(() => document.getElementById(`pb-${block.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}

function duplicate(index) {
    const copy = structuredClone(toRaw(blocks.value[index]));

    copy.id = blockId();
    blocks.value.splice(index + 1, 0, copy);
}

function move(index, step) {
    const target = index + step;

    if (target < 0 || target >= blocks.value.length) {
        return;
    }

    const [block] = blocks.value.splice(index, 1);

    blocks.value.splice(target, 0, block);
}

async function remove(index) {
    const block = blocks.value[index];

    const confirmed = await ui.confirm({
        title: 'Удалить блок?',
        message: `Блок «${definition(block.type).label}» пропадёт со страницы после сохранения элемента.`,
    });

    if (confirmed) {
        blocks.value.splice(index, 1);
    }
}

function toggle(id) {
    const next = new Set(collapsed.value);

    next.has(id) ? next.delete(id) : next.add(id);
    collapsed.value = next;
}

/** Строка-подсказка у свёрнутого блока: чтобы понимать, что внутри. */
function summary(block) {
    const data = block.data ?? {};
    const text = (data.text ?? '').replace(/<[^>]+>/g, ' ').trim() || data.title || data.author || data.url
        || data.items?.[0]?.title
        || (data.images?.length ? `${data.images.length} фото` : '')
        || (data.rows?.length ? `${data.rows.length} × ${data.rows[0]?.length ?? 0}` : '');

    return String(text).replace(/\s+/g, ' ').trim().slice(0, 90);
}

// Внешняя смена значения — другой элемент открыли в той же форме.
let lastEmitted = null;

watch(() => props.modelValue, (value) => {
    if (value && toRaw(value) === lastEmitted) {
        return;
    }

    blocks.value = structuredClone(toRaw(value?.blocks ?? [])).map((block) => ({ ...block, id: String(block.id || blockId()) }));
    settings.value = {
        showSidebar: Boolean(value?.settings?.showSidebar),
        sidebarItems: structuredClone(toRaw(value?.settings?.sidebarItems ?? [])),
    };
    collapsed.value = new Set();
}, { immediate: true });

watch([blocks, settings], () => {
    lastEmitted = { version: 1, settings: toRaw(settings.value), blocks: toRaw(blocks.value) };
    emit('update:modelValue', lastEmitted);
}, { deep: true });

/** Куда может вести пункт оглавления: блоки со своим текстом-подсказкой. */
const targets = computed(() => blocks.value.map((block, index) => ({
    value: block.id,
    label: `${index + 1}. ${definition(block.type).label}${summary(block) ? ` — ${summary(block)}` : ''}`,
})));

function addSidebarItem() {
    const header = blocks.value.find((block) => block.type === 'header'
        && !settings.value.sidebarItems.some((item) => item.targetId === block.id));

    settings.value.sidebarItems.push({ text: header?.data?.text ?? '', targetId: header?.id ?? blocks.value[0]?.id ?? '' });
}

/** Все заголовки страницы — пунктами оглавления разом. */
function fillFromHeaders() {
    settings.value.sidebarItems = blocks.value
        .filter((block) => block.type === 'header' && block.data?.text)
        .map((block) => ({ text: block.data.text, targetId: block.id }));
}

onMounted(async () => {
    try {
        palette.value = await loadCatalogue();
        definitions.value = Object.fromEntries(palette.value.map((item) => [item.type, item]));
    } catch (error) {
        catalogue = null;
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="grid gap-5 lg:grid-cols-[13rem_minmax(0,1fr)]">
        <aside class="lg:sticky lg:top-19 lg:self-start">
            <p class="mb-2 text-xs font-semibold tracking-wide text-[var(--text-muted)] uppercase">Добавить блок</p>

            <p v-if="loading" class="text-sm text-[var(--text-muted)]">Загружаем…</p>

            <Draggable v-else :list="palette" item-key="type" :sort="false" :clone="createBlock"
                       :group="{ name: 'pagebuilder', pull: 'clone', put: false }"
                       class="grid grid-cols-2 gap-2 lg:grid-cols-1">
                <template #item="{ element: item }">
                    <button type="button" :title="`Перетащите в страницу или нажмите, чтобы добавить в конец`"
                            class="flex cursor-grab items-center gap-2 rounded-lg border border-[var(--surface-border)] bg-[var(--surface-panel)] px-3 py-2 text-left text-sm text-[var(--text-strong)] transition hover:border-brand-500 hover:text-brand-600 active:cursor-grabbing"
                            @click="add(item)">
                        <BlockIcon :name="item.icon" />
                        <span class="truncate">{{ item.label }}</span>
                    </button>
                </template>
            </Draggable>
        </aside>

        <div class="min-w-0 space-y-3">
            <div class="rounded-xl border border-[var(--surface-border)] bg-[var(--surface-panel)] p-3">
                <NToggle v-model="settings.showSidebar" label="Боковое меню со ссылками на блоки"
                         hint="Оглавление сбоку от контента (pb-aside). Пункт ведёт к выбранному блоку." />

                <div v-if="settings.showSidebar" class="mt-3 space-y-2">
                    <div v-for="(item, index) in settings.sidebarItems" :key="index" class="flex flex-wrap items-center gap-2">
                        <NInput v-model="item.text" placeholder="Текст пункта" class="min-w-48 flex-1" />
                        <select v-model="item.targetId" class="field-input min-w-48 flex-1">
                            <option v-for="target in targets" :key="target.value" :value="target.value">{{ target.label }}</option>
                        </select>
                        <button type="button" title="Убрать пункт" class="rounded p-1 text-[var(--text-muted)] hover:text-red-600"
                                @click="settings.sidebarItems.splice(index, 1)">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-4 text-sm">
                        <button type="button" class="font-medium text-brand-600 hover:underline dark:text-brand-400"
                                :disabled="!blocks.length" @click="addSidebarItem">+ Пункт</button>
                        <button type="button" class="font-medium text-brand-600 hover:underline dark:text-brand-400"
                                :disabled="!blocks.length" @click="fillFromHeaders">Собрать из заголовков</button>
                    </div>
                </div>
            </div>

            <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
                {{ error }}
            </p>

            <Draggable v-model="blocks" item-key="id" handle="[data-pb-handle]" :group="{ name: 'pagebuilder' }"
                       ghost-class="opacity-40" :animation="150"
                       :class="['min-h-40 space-y-3 rounded-xl', !blocks.length && 'border-2 border-dashed border-[var(--surface-border)]']">
                <template #item="{ element: block, index }">
                    <section :id="`pb-${block.id}`"
                             class="overflow-hidden rounded-xl border border-[var(--surface-border)] bg-[var(--surface-panel)]">
                        <header class="flex items-center gap-2 border-b border-[var(--surface-border)] bg-[var(--surface-muted)] px-3 py-2">
                            <button type="button" data-pb-handle title="Перетащить"
                                    class="cursor-grab rounded p-1 text-[var(--text-faint)] hover:text-[var(--text-strong)] active:cursor-grabbing">
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="9" cy="6" r="1.5" /><circle cx="15" cy="6" r="1.5" />
                                    <circle cx="9" cy="12" r="1.5" /><circle cx="15" cy="12" r="1.5" />
                                    <circle cx="9" cy="18" r="1.5" /><circle cx="15" cy="18" r="1.5" />
                                </svg>
                            </button>

                            <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" @click="toggle(block.id)">
                                <BlockIcon :name="definition(block.type).icon" class="text-[var(--text-muted)]" />
                                <span class="text-xs font-semibold tracking-wide text-[var(--text-strong)] uppercase">
                                    {{ definition(block.type).label }}
                                </span>
                                <span v-if="collapsed.has(block.id)" class="truncate text-xs text-[var(--text-muted)]">
                                    {{ summary(block) }}
                                </span>
                            </button>

                            <div class="flex items-center text-[var(--text-muted)]">
                                <button type="button" title="Выше" :disabled="index === 0"
                                        class="rounded p-1 hover:text-[var(--text-strong)] disabled:opacity-30" @click="move(index, -1)">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6" /></svg>
                                </button>
                                <button type="button" title="Ниже" :disabled="index === blocks.length - 1"
                                        class="rounded p-1 hover:text-[var(--text-strong)] disabled:opacity-30" @click="move(index, 1)">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg>
                                </button>
                                <button type="button" title="Дублировать" class="rounded p-1 hover:text-[var(--text-strong)]"
                                        @click="duplicate(index)">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h8" /></svg>
                                </button>
                                <button type="button" title="Удалить" class="rounded p-1 hover:text-red-600"
                                        @click="remove(index)">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg>
                                </button>
                            </div>
                        </header>

                        <div v-show="!collapsed.has(block.id)" class="p-4">
                            <component :is="resolveEditor(block.type)" v-if="resolveEditor(block.type)"
                                       :data="block.data" :iblock="iblock" />
                            <p v-else class="text-sm text-[var(--text-muted)]">
                                Для блока «{{ block.type }}» в панели нет редактора — данные сохранятся как есть.
                            </p>

                            <div class="mt-4 flex items-center gap-2 border-t border-[var(--surface-border)] pt-3">
                                <span class="text-xs text-[var(--text-muted)]">CSS класс</span>
                                <NInput v-model="block.data.cssClass" placeholder="например, mb-0 bg-light" class="max-w-xs font-mono text-xs" />
                            </div>
                        </div>
                    </section>
                </template>

                <template #footer>
                    <p v-if="!blocks.length" class="px-4 py-12 text-center text-sm text-[var(--text-muted)]">
                        Перетащите блок сюда или нажмите на него слева.<br>
                        Пока блоков нет, на сайте выводится подробный текст.
                    </p>
                </template>
            </Draggable>
        </div>
    </div>
</template>
