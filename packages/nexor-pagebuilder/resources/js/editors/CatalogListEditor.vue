<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api, Draggable, NField, NHtmlInput, NInput, NSelect, NToggle, useSession, useUi } from '../core.js';
import Segmented from '../components/Segmented.vue';

/**
 * Каталог: элементы инфоблока каруселью.
 *
 * Инфоблоки — только те, что текущий пользователь видит в панели; разделы и
 * поиск элементов идут через API панели с теми же правами.
 */
const props = defineProps({
    data: { type: Object, required: true },
    iblock: { type: Object, default: null },
});

const session = useSession();
const ui = useUi();

props.data.title ??= '';
props.data.description ??= '';
props.data.cardTemplate ??= '';
props.data.source ??= {};
props.data.source.iblockId ??= null;
props.data.source.selectionMode ??= 'section';
props.data.source.sectionId ??= null;
props.data.source.manualItems ??= [];
props.data.source.sortBy ??= 'sort';
props.data.source.limit = Number(props.data.source.limit ?? 12);
props.data.view ??= {};
props.data.view.slidesPerView = Number(props.data.view.slidesPerView ?? 4);
props.data.view.gap = Number(props.data.view.gap ?? 20);
props.data.view.arrows ??= true;
props.data.view.dots ??= true;

const source = computed(() => props.data.source);

const iblockOptions = computed(() => session.iblocks.map((iblock) => ({ value: iblock.id, label: iblock.name })));

const currentIblock = computed(() => session.iblock(source.value.iblockId));

const modes = computed(() => [
    ...(currentIblock.value?.has_sections ? [{ value: 'section', label: 'Из раздела' }] : []),
    { value: 'all', label: 'Весь инфоблок' },
    { value: 'manual', label: 'Выбрать вручную' },
]);

const sorts = [
    { value: 'sort', label: 'По сортировке' },
    { value: 'active_from', label: 'Сначала новые' },
    { value: 'name', label: 'По названию' },
];

const sections = ref([]);

const sectionOptions = computed(() => sections.value.map((section) => ({ value: section.id, label: section.indented_name })));

async function loadSections() {
    sections.value = [];

    if (!currentIblock.value?.has_sections) {
        return;
    }

    try {
        const data = await api.get(`iblocks/${source.value.iblockId}/sections`);

        sections.value = data.data;
    } catch (error) {
        ui.notifyError(error);
    }
}

// Сменили инфоблок — раздел и выбранные элементы от прежнего больше не подходят.
watch(() => source.value.iblockId, (next, previous) => {
    if (previous !== undefined && previous !== null && String(next) !== String(previous)) {
        source.value.sectionId = null;
        source.value.manualItems = [];
    }

    if (!modes.value.some((mode) => mode.value === source.value.selectionMode)) {
        source.value.selectionMode = modes.value[0]?.value ?? 'all';
    }

    loadSections();
});

const search = ref('');
const found = ref([]);
const searching = ref(false);

let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => findElements(value), 300);
});

async function findElements(value) {
    if (!source.value.iblockId || value.trim() === '') {
        found.value = [];

        return;
    }

    searching.value = true;

    try {
        const data = await api.get(`iblocks/${source.value.iblockId}/elements`, { search: value.trim(), per_page: 20 });

        found.value = data.data;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        searching.value = false;
    }
}

function pick(element) {
    if (!source.value.manualItems.some((item) => item.id === element.id)) {
        source.value.manualItems.push({ id: element.id, name: element.name });
    }
}

/** Шаблоны карточек, которые уже есть в пакете и на сайте, — подсказка при вводе. */
const cardTemplates = ref([]);
const cardPrefix = ref('nexor::components.');
const cardDefault = ref('catalog.card.default');
const helpOpen = ref(false);
const listId = `pb-cards-${Math.random().toString(36).slice(2, 8)}`;

let templatesRequest = null;

async function loadCardTemplates() {
    templatesRequest ??= api.get('pagebuilder/card-templates');

    try {
        const data = await templatesRequest;

        cardTemplates.value = data.data;
        cardPrefix.value = data.prefix;
        cardDefault.value = data.default;
    } catch {
        templatesRequest = null;
    }
}

/** Вставили имя целиком, с префиксом, — префикс уже стоит слева. */
function onCardTemplate(value) {
    props.data.cardTemplate = value.startsWith(cardPrefix.value) ? value.slice(cardPrefix.value.length) : value;
}

onMounted(() => {
    loadSections();
    loadCardTemplates();
});
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <NField label="Заголовок блока">
                <NInput v-model="data.title" placeholder="Например, «Похожие товары»" />
            </NField>

            <NField label="Инфоблок">
                <NSelect v-model="data.source.iblockId" :options="iblockOptions" placeholder="Выберите инфоблок" />
            </NField>
        </div>

        <NField label="Описание">
            <NHtmlInput v-model="data.description" rows="5rem" />
        </NField>

        <NField label="Шаблон карточки" :hint="`Пусто — стандартная карточка ${cardDefault}.`">
            <div class="flex overflow-hidden rounded-lg border border-[var(--surface-border-strong)] focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/40">
                <span class="flex items-center bg-[var(--surface-muted)] px-3 font-mono text-sm text-[var(--text-muted)] select-none">{{ cardPrefix }}</span>
                <input :value="data.cardTemplate" :list="listId" type="text" spellcheck="false" :placeholder="cardDefault"
                       class="min-w-0 flex-1 bg-[var(--surface-panel)] px-3 py-2 font-mono text-sm text-[var(--text-strong)] outline-none placeholder:text-[var(--text-faint)]"
                       @input="onCardTemplate($event.target.value)">
                <datalist :id="listId">
                    <option v-for="name in cardTemplates" :key="name" :value="name" />
                </datalist>
            </div>

            <button type="button" class="mt-1.5 text-xs font-medium text-brand-600 hover:underline dark:text-brand-400"
                    @click="helpOpen = !helpOpen">
                {{ helpOpen ? 'Скрыть пример' : 'Как сделать свою карточку?' }}
            </button>

            <div v-if="helpOpen" class="mt-2 space-y-2 rounded-lg bg-[var(--surface-muted)] p-3 text-xs leading-relaxed text-[var(--text-base)]">
                <p><b>1.</b> Создайте шаблон на основе стандартной карточки:</p>
                <code class="block rounded bg-[var(--surface-panel)] px-2 py-1.5 font-mono text-[var(--text-strong)]">php artisan nexor:component catalog.card mini</code>

                <p><b>2.</b> Появится файл — сверстайте его как нужно, внутри доступна <code class="font-mono">$element</code>:</p>
                <code class="block rounded bg-[var(--surface-panel)] px-2 py-1.5 font-mono break-all text-[var(--text-strong)]">resources/views/vendor/nexor/components/catalog/card/mini.blade.php</code>

                <p><b>3.</b> Впишите сюда имя шаблона:</p>
                <code class="block rounded bg-[var(--surface-panel)] px-2 py-1.5 font-mono text-[var(--text-strong)]"><span class="text-[var(--text-muted)]">{{ cardPrefix }}</span>catalog.card.mini</code>

                <p class="text-[var(--text-muted)]">
                    Имя — это путь к файлу через точки, без <code class="font-mono">.blade.php</code>: папка компонента, <code class="font-mono">card</code>, имя шаблона.
                    Кнопку «В корзину» оставьте внутри <code class="font-mono">@feature('shop')</code> — тогда без модуля «Магазин» карточка не сломается.
                </p>

                <p v-if="cardTemplates.length" class="text-[var(--text-muted)]">
                    Уже есть: <code v-for="(name, index) in cardTemplates" :key="name" class="font-mono">{{ name }}{{ index < cardTemplates.length - 1 ? ', ' : '' }}</code>
                </p>
            </div>
        </NField>

        <template v-if="data.source.iblockId">
            <Segmented v-model="data.source.selectionMode" :options="modes" />

            <div v-if="data.source.selectionMode !== 'manual'" class="grid gap-4 md:grid-cols-3">
                <NField v-if="data.source.selectionMode === 'section'" label="Раздел" hint="Вместе с подразделами. Не выбран — весь инфоблок.">
                    <NSelect v-model="data.source.sectionId" :options="sectionOptions" placeholder="— все разделы —" />
                </NField>
                <NField label="Порядок">
                    <NSelect v-model="data.source.sortBy" :options="sorts" />
                </NField>
                <NField label="Сколько элементов">
                    <NInput v-model.number="data.source.limit" type="number" min="1" max="50" class="w-28" />
                </NField>
            </div>

            <div v-else class="space-y-3">
                <NField label="Найти элемент" hint="Начните вводить название — нажмите на найденное, чтобы добавить.">
                    <NInput v-model="search" placeholder="Название или код" />
                </NField>

                <div v-if="search" class="max-h-56 overflow-y-auto rounded-lg border border-[var(--surface-border)]">
                    <p v-if="searching" class="p-3 text-sm text-[var(--text-muted)]">Ищем…</p>
                    <p v-else-if="!found.length" class="p-3 text-sm text-[var(--text-muted)]">Ничего не нашлось.</p>
                    <button v-for="element in found" v-else :key="element.id" type="button"
                            :disabled="data.source.manualItems.some((item) => item.id === element.id)"
                            class="block w-full border-t border-[var(--surface-border)] px-3 py-2 text-left text-sm text-[var(--text-strong)] first:border-t-0 hover:bg-[var(--surface-muted)] disabled:opacity-50"
                            @click="pick(element)">
                        {{ element.name }}
                        <span class="text-xs text-[var(--text-faint)]">#{{ element.id }}</span>
                    </button>
                </div>

                <p v-if="!data.source.manualItems.length" class="text-sm text-[var(--text-muted)]">Элементы ещё не выбраны.</p>

                <Draggable v-else v-model="data.source.manualItems" item-key="id" :animation="150" ghost-class="opacity-40" class="space-y-1.5">
                    <template #item="{ element: item, index }">
                        <div class="flex cursor-grab items-center gap-2 rounded-lg border border-[var(--surface-border)] px-3 py-2 text-sm active:cursor-grabbing">
                            <span class="w-5 text-xs text-[var(--text-faint)]">{{ index + 1 }}</span>
                            <span class="flex-1 text-[var(--text-strong)]">{{ item.name }}</span>
                            <button type="button" title="Убрать" class="rounded p-1 text-[var(--text-muted)] hover:text-red-600"
                                    @click="data.source.manualItems.splice(index, 1)">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </template>
                </Draggable>
            </div>
        </template>

        <div class="flex flex-wrap items-end gap-4 rounded-lg bg-[var(--surface-muted)] p-3">
            <NField label="Карточек видно">
                <NInput v-model.number="data.view.slidesPerView" type="number" min="1" max="6" class="w-24" />
            </NField>
            <NField label="Отступ, px">
                <NInput v-model.number="data.view.gap" type="number" min="0" max="100" class="w-24" />
            </NField>
            <NToggle v-model="data.view.arrows" label="Стрелки" />
            <NToggle v-model="data.view.dots" label="Точки" />
        </div>

        <p class="text-xs text-[var(--text-muted)]">
            Стандартная карточка — как в списке каталога, с ценой и кнопкой «В корзину», если стоит модуль «Магазин».
            Карусель — Swiper сайта; без него карточки выводятся сеткой.
        </p>
    </div>
</template>
