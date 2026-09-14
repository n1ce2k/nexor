<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import {
    api, formatMoney, NBadge, NButton, NCard, NEmpty, NField, NIcon, NInput, NModal, NPageHeader, NPagination, NSelect, NTable, NToggle,
    useForm, useSession, useUi,
} from '../core.js';

/**
 * Промокоды корзины Ultimate.
 */
const session = useSession();
const ui = useUi();

const rows = ref([]);
const meta = ref(null);
const loading = ref(true);
const page = ref(1);
const types = ref([]);
const scopes = ref([]);
const activeInCart = ref(true);
const symbol = ref('₽');

const open = ref(false);
const editing = ref(null);

const blank = () => ({
    code: '', description: '', type: 'percent', value: 10, min_sum: '', starts_at: '', ends_at: '',
    usage_limit: '', scope: 'all', section_ids: [], element_ids: [], is_active: true,
});

const form = useForm(blank());

const columns = [
    { key: 'code', label: 'Код' },
    { key: 'value', label: 'Скидка', width: '9rem' },
    { key: 'scope', label: 'Действует', width: '14rem' },
    { key: 'usage', label: 'Использован', align: 'center', width: '8rem', muted: true },
    { key: 'status', label: 'Статус', align: 'center', width: '8rem' },
    { key: 'actions', label: '', align: 'right', width: '6rem' },
];

async function load() {
    loading.value = true;

    try {
        const data = await api.get('shop/promocodes', { page: page.value });

        rows.value = data.data;
        meta.value = data.meta;
        types.value = data.types;
        scopes.value = data.scopes;
        activeInCart.value = data.active_in_cart;
        symbol.value = data.currency_symbol;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function status(row) {
    if (!row.is_active) {
        return { label: 'выключен', color: 'gray' };
    }

    if (row.is_expired) {
        return { label: 'истёк', color: 'gray' };
    }

    return row.is_exhausted ? { label: 'исчерпан', color: 'amber' } : { label: 'действует', color: 'green' };
}

// ------------------------------------------------------- выбор разделов и товаров

/** Каталоги с ценами — только в них есть что продавать. */
const catalogs = computed(() => session.iblocks.filter((iblock) => iblock.has_commerce && !iblock.product_iblock_id)
    .map((iblock) => ({ value: iblock.id, label: iblock.name })));

const catalogId = ref(null);
const sections = ref([]);
const elementSearch = ref('');
const elementResults = ref([]);
const chosenElements = ref([]);

async function loadSections() {
    if (!catalogId.value) {
        sections.value = [];

        return;
    }

    try {
        const data = await api.get(`iblocks/${catalogId.value}/sections`, { per_page: 200 });

        sections.value = data.data;
    } catch (error) {
        ui.notifyError(error);
    }
}

let searchTimer = null;

function searchElements(value) {
    elementSearch.value = value;
    clearTimeout(searchTimer);

    searchTimer = setTimeout(async () => {
        if (!catalogId.value || value.trim().length < 2) {
            elementResults.value = [];

            return;
        }

        try {
            const data = await api.get(`iblocks/${catalogId.value}/elements`, { search: value, per_page: 20 });

            elementResults.value = data.data;
        } catch (error) {
            ui.notifyError(error);
        }
    }, 300);
}

function toggleElement(element) {
    const ids = form.fields.element_ids;

    if (ids.includes(element.id)) {
        form.fields.element_ids = ids.filter((id) => id !== element.id);
        chosenElements.value = chosenElements.value.filter((item) => item.id !== element.id);
    } else {
        form.fields.element_ids = [...ids, element.id];
        chosenElements.value = [...chosenElements.value, { id: element.id, name: element.name }];
    }
}

watch(catalogId, loadSections);

function openEditor(row = null) {
    editing.value = row;

    form.reset(row
        ? {
            code: row.code,
            description: row.description ?? '',
            type: row.type,
            value: row.value,
            min_sum: row.min_sum ?? '',
            starts_at: row.starts_at ?? '',
            ends_at: row.ends_at ?? '',
            usage_limit: row.usage_limit ?? '',
            scope: row.scope,
            section_ids: [...row.section_ids],
            element_ids: [...row.element_ids],
            is_active: row.is_active,
        }
        : blank());

    chosenElements.value = row ? row.element_ids.map((id, index) => ({ id, name: row.targets[index] ?? `#${id}` })) : [];
    elementResults.value = [];
    elementSearch.value = '';
    catalogId.value ??= catalogs.value[0]?.value ?? null;

    open.value = true;
}

async function save() {
    const data = await form.submit(
        editing.value ? 'put' : 'post',
        editing.value ? `shop/promocodes/${editing.value.id}` : 'shop/promocodes',
    );

    if (data) {
        open.value = false;
        load();
    }
}

async function remove(row) {
    const confirmed = await ui.confirm({
        title: 'Удалить промокод?',
        message: `Промокод «${row.code}» перестанет работать. В оформленных заказах он останется.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        ui.notify((await api.delete(`shop/promocodes/${row.id}`)).message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(load);
</script>

<template>
    <div>
        <NPageHeader title="Промокоды" description="Скидки по коду на странице корзины.">
            <template #actions>
                <NButton v-if="session.can('shop.promocodes.create')" icon="plus" @click="openEditor()">Добавить промокод</NButton>
            </template>
        </NPageHeader>

        <div v-if="!activeInCart"
             class="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            <NIcon name="info" size="size-4 mt-0.5 shrink-0" />
            Промокоды работают только в корзине Ultimate. Сейчас выбрана Basic — переключить можно в «Магазин → Корзина».
        </div>

        <NCard :padding="false">
            <NEmpty v-if="!loading && rows.length === 0" icon="tag" title="Промокодов пока нет"
                    description="Создайте код, например SALE10 на 10% от заказа." />

            <NTable v-else :columns="columns" :rows="rows" :loading="loading">
                <template #cell-code="{ row }">
                    <p class="font-mono font-semibold text-[var(--text-strong)]">{{ row.code }}</p>
                    <p v-if="row.description" class="mt-0.5 text-xs text-[var(--text-muted)]">{{ row.description }}</p>
                </template>

                <template #cell-value="{ row }">
                    {{ row.type === 'percent' ? `${Number(row.value)}%` : `${formatMoney(row.value)} ${symbol}` }}
                    <p v-if="row.min_sum" class="text-xs text-[var(--text-muted)]">от {{ formatMoney(row.min_sum) }} {{ symbol }}</p>
                </template>

                <template #cell-scope="{ row }">
                    {{ row.scope_label }}
                    <p v-if="row.targets.length" class="truncate text-xs text-[var(--text-muted)]" :title="row.targets.join(', ')">
                        {{ row.targets.join(', ') }}
                    </p>
                </template>

                <template #cell-usage="{ row }">
                    {{ row.used_count }}<template v-if="row.usage_limit"> из {{ row.usage_limit }}</template>
                </template>

                <template #cell-status="{ row }">
                    <NBadge :color="status(row).color">{{ status(row).label }}</NBadge>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-0.5">
                        <button v-if="session.can('shop.promocodes.update')" type="button" title="Изменить" @click="openEditor(row)"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                            <NIcon name="pencil" size="size-4" />
                        </button>
                        <button v-if="session.can('shop.promocodes.delete')" type="button" title="Удалить" @click="remove(row)"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10">
                            <NIcon name="trash" size="size-4" />
                        </button>
                    </div>
                </template>
            </NTable>

            <NPagination :meta="meta" @change="(value) => { page = value; load(); }" />
        </NCard>

        <NModal v-model="open" :title="editing ? `Промокод ${editing.code}` : 'Новый промокод'" max-width="max-w-2xl">
            <div class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <NField label="Код" required :error="form.error('code')">
                        <NInput v-model="form.fields.code" class="font-mono uppercase" :invalid="Boolean(form.error('code'))" />
                    </NField>

                    <NField label="Описание" hint="Для себя — покупатель его не видит." :error="form.error('description')">
                        <NInput v-model="form.fields.description" />
                    </NField>

                    <NField label="Тип скидки" :error="form.error('type')">
                        <NSelect v-model="form.fields.type" :options="types" />
                    </NField>

                    <NField :label="form.fields.type === 'percent' ? 'Скидка, %' : `Скидка, ${symbol}`" required :error="form.error('value')">
                        <NInput v-model="form.fields.value" type="number" min="0" step="0.01" :invalid="Boolean(form.error('value'))" />
                    </NField>

                    <NField :label="`Минимальная сумма заказа, ${symbol}`" hint="Пусто — без ограничения." :error="form.error('min_sum')">
                        <NInput v-model="form.fields.min_sum" type="number" min="0" step="0.01" />
                    </NField>

                    <NField label="Лимит использований" hint="Сколько заказов могут его применить. Пусто — без лимита." :error="form.error('usage_limit')">
                        <NInput v-model="form.fields.usage_limit" type="number" min="1" />
                    </NField>

                    <NField label="Начало действия" :error="form.error('starts_at')">
                        <NInput v-model="form.fields.starts_at" type="datetime-local" />
                    </NField>

                    <NField label="Окончание действия" :error="form.error('ends_at')">
                        <NInput v-model="form.fields.ends_at" type="datetime-local" />
                    </NField>
                </div>

                <NField label="На что действует" :error="form.error('scope')">
                    <NSelect v-model="form.fields.scope" :options="scopes" />
                </NField>

                <template v-if="form.fields.scope !== 'all'">
                    <NField label="Каталог">
                        <NSelect v-model="catalogId" :options="catalogs" placeholder="— выберите каталог —" />
                    </NField>

                    <NField v-if="form.fields.scope === 'sections'" label="Разделы"
                            hint="Товары вложенных разделов тоже получают скидку." :error="form.error('section_ids')">
                        <div class="max-h-60 space-y-2 overflow-y-auto rounded-lg border border-[var(--surface-border)] p-3">
                            <label v-for="section in sections" :key="section.id" class="flex cursor-pointer items-center gap-2.5 select-none">
                                <input v-model="form.fields.section_ids" type="checkbox" :value="section.id"
                                       class="size-4 rounded border-[var(--surface-border-strong)] text-brand-600">
                                <span class="text-sm text-[var(--text-strong)]">{{ section.indented_name ?? section.name }}</span>
                            </label>
                            <p v-if="!sections.length" class="text-sm text-[var(--text-muted)]">В этом каталоге нет разделов.</p>
                        </div>
                    </NField>

                    <NField v-else label="Товары" :error="form.error('element_ids')">
                        <div class="space-y-3">
                            <div v-if="chosenElements.length" class="flex flex-wrap gap-2">
                                <button v-for="element in chosenElements" :key="element.id" type="button"
                                        class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-2 py-1 text-xs text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"
                                        @click="toggleElement(element)">
                                    {{ element.name }} <NIcon name="x" size="size-3" />
                                </button>
                            </div>

                            <NInput :model-value="elementSearch" placeholder="Начните вводить название товара"
                                    @update:model-value="searchElements" />

                            <ul v-if="elementResults.length" class="max-h-48 divide-y divide-[var(--surface-border)] overflow-y-auto rounded-lg border border-[var(--surface-border)]">
                                <li v-for="element in elementResults" :key="element.id">
                                    <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 select-none">
                                        <input type="checkbox" :checked="form.fields.element_ids.includes(element.id)"
                                               class="size-4 rounded border-[var(--surface-border-strong)] text-brand-600"
                                               @change="toggleElement(element)">
                                        <span class="text-sm text-[var(--text-strong)]">{{ element.name }}</span>
                                    </label>
                                </li>
                            </ul>
                        </div>
                    </NField>
                </template>

                <NToggle v-model="form.fields.is_active" label="Промокод активен" />
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="open = false">Отмена</NButton>
                <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
            </template>
        </NModal>
    </div>
</template>
