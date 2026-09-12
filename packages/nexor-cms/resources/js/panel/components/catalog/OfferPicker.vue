<script setup>
import { computed, ref, watch } from 'vue';
import FieldPicker from './FieldPicker.vue';
import NButton from '../ui/NButton.vue';
import NField from '../ui/NField.vue';
import NInput from '../ui/NInput.vue';
import NModal from '../ui/NModal.vue';
import NSelect from '../ui/NSelect.vue';
import { api } from '../../api';
import { propertyText, useFieldPrefs } from '../../composables/useFieldPrefs';
import { useUi } from '../../stores/ui';

/**
 * Попап вкладки «Предложения»: завести новое предложение или привязать к
 * товару уже существующее.
 *
 * Уходить на отдельную форму ради размера или цвета незачем — у предложения
 * всего и есть, что название, цена и остаток. Всё остальное правится потом в
 * инфоблоке предложений.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** Инфоблок товара и сам товар, к которому цепляем предложения. */
    iblock: { type: [String, Number], required: true },
    element: { type: [String, Number], required: true },
    /** Инфоблок предложений — из ответа вкладки. */
    offersIblock: { type: Object, default: null },
    measures: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    currency: { type: String, default: 'RUB' },
    /** Свойства инфоблока предложений — из них выбирают, что показать в списке. */
    properties: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const ui = useUi();

const mode = ref('create');
const busy = ref(false);
const errors = ref({});

const created = ref({ name: '', price: '', quantity: 0, measure: 'шт', currency: props.currency });

const search = ref('');
const rows = ref([]);
const selected = ref([]);
const listLoading = ref(false);

const fieldsOpen = ref(false);

/** Какие свойства предложения видно в списке выбора. */
const shownFields = useFieldPrefs(() => (props.offersIblock ? `nexor.offer-picker.${props.offersIblock.id}` : null));

const fieldOptions = computed(() => props.properties.map((property) => ({
    key: property.code,
    label: property.name,
    hint: property.type_label,
})));

function valueOf(row, code) {
    return propertyText(props.properties, row, code);
}

function labelOf(code) {
    return props.properties.find((property) => property.code === code)?.name ?? code;
}

const canCreate = computed(() => Boolean(props.offersIblock?.abilities?.create));
const canAttach = computed(() => Boolean(props.offersIblock?.abilities?.update));

const measureOptions = computed(() => props.measures.map((measure) => ({ value: measure, label: measure })));

const currencyOptions = computed(() => props.currencies.map((item) => ({
    value: item.value,
    label: `${item.label} (${item.symbol})`,
})));

/** Свободные предложения: ничьи или уже принадлежащие этому товару. */
const available = computed(() => rows.value.filter((row) => {
    const parent = row.catalog?.parent_element_id ?? null;

    return parent === null || String(parent) === String(props.element);
}));

function attachedTo(row) {
    return String(row.catalog?.parent_element_id ?? '') === String(props.element);
}

function close() {
    emit('update:modelValue', false);
}

function reset() {
    mode.value = canCreate.value ? 'create' : 'pick';
    errors.value = {};
    created.value = { name: '', price: '', quantity: 0, measure: 'шт', currency: props.currency };
    selected.value = [];
    search.value = '';
}

async function loadCandidates() {
    if (!props.offersIblock) {
        return;
    }

    listLoading.value = true;

    try {
        const data = await api.get(`iblocks/${props.offersIblock.id}/elements`, {
            search: search.value,
            per_page: 100,
        });

        rows.value = data.data ?? [];
    } catch (error) {
        ui.notifyError(error);
    } finally {
        listLoading.value = false;
    }
}

async function createOffer() {
    busy.value = true;
    errors.value = {};

    try {
        const data = await api.post(`iblocks/${props.offersIblock.id}/elements`, {
            name: created.value.name,
            is_active: true,
            parent_element_id: props.element,
            catalog: {
                price: created.value.price,
                currency: created.value.currency,
                quantity: created.value.quantity,
                measure: created.value.measure,
            },
        });

        ui.notify(data.message);
        emit('saved');
        close();
    } catch (error) {
        // 422 показываем прямо в попапе, остальное — обычным уведомлением.
        if (error.status === 422) {
            errors.value = error.errors ?? {};
        } else {
            ui.notifyError(error);
        }
    } finally {
        busy.value = false;
    }
}

async function attachSelected() {
    busy.value = true;

    try {
        const data = await api.post(`iblocks/${props.iblock}/elements/${props.element}/offers`, {
            offers: selected.value,
        });

        ui.notify(data.message);
        emit('saved');
        close();
    } catch (error) {
        ui.notifyError(error);
    } finally {
        busy.value = false;
    }
}

watch(() => props.modelValue, (open) => {
    if (!open) {
        return;
    }

    reset();
    loadCandidates();
});
</script>

<template>
    <NModal :model-value="modelValue" title="Торговое предложение" max-width="max-w-2xl"
            @update:model-value="close">
        <div class="space-y-4">
            <div class="flex gap-1 rounded-lg bg-[var(--surface-muted)] p-1 text-sm">
                <button v-if="canCreate" type="button"
                        :class="['flex-1 rounded-md px-3 py-1.5 transition', mode === 'create' ? 'bg-[var(--surface)] font-medium text-[var(--text-strong)] shadow-sm' : 'text-[var(--text-muted)]']"
                        @click="mode = 'create'">
                    Новое предложение
                </button>
                <button v-if="canAttach" type="button"
                        :class="['flex-1 rounded-md px-3 py-1.5 transition', mode === 'pick' ? 'bg-[var(--surface)] font-medium text-[var(--text-strong)] shadow-sm' : 'text-[var(--text-muted)]']"
                        @click="mode = 'pick'">
                    Выбрать существующие
                </button>
            </div>

            <template v-if="mode === 'create'">
                <NField label="Название" required hint="Например: «Размер M» или «Синий, 500 мл»."
                        :error="errors['name']?.[0]">
                    <NInput v-model="created.name" :invalid="Boolean(errors['name'])" />
                </NField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <NField label="Цена" :error="errors['catalog.price']?.[0]">
                        <NInput v-model="created.price" type="number" min="0" step="0.01"
                                :invalid="Boolean(errors['catalog.price'])" />
                    </NField>

                    <NField label="Валюта">
                        <NSelect v-model="created.currency" :options="currencyOptions" />
                    </NField>

                    <NField label="Доступное количество" :error="errors['catalog.quantity']?.[0]">
                        <NInput v-model="created.quantity" type="number" min="0" step="any" />
                    </NField>

                    <NField label="Единица измерения">
                        <NSelect v-model="created.measure" :options="measureOptions" />
                    </NField>
                </div>

                <p class="text-xs text-[var(--text-muted)]">
                    Символьный код и раздел предложению не нужны — остальное можно дописать позже
                    в инфоблоке «{{ offersIblock?.name }}».
                </p>
            </template>

            <template v-else>
                <NInput v-model="search" placeholder="Поиск по названию или коду"
                        @update:model-value="loadCandidates" />

                <div class="flex justify-end">
                    <NButton size="sm" variant="ghost" @click="fieldsOpen = true">Настроить поля</NButton>
                </div>

                <div class="max-h-72 overflow-y-auto rounded-lg border border-[var(--surface-border)]">
                    <p v-if="listLoading" class="p-4 text-center text-sm text-[var(--text-muted)]">Загружаем…</p>

                    <p v-else-if="!available.length" class="p-4 text-center text-sm text-[var(--text-muted)]">
                        Свободных предложений нет — заведите новое.
                    </p>

                    <label v-for="row in available" :key="row.id"
                           class="flex cursor-pointer items-center gap-3 border-b border-[var(--surface-border)] px-3 py-2 last:border-b-0 select-none">
                        <input type="checkbox" :value="row.id" v-model="selected" :disabled="attachedTo(row)"
                               class="size-4 shrink-0 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-2 focus:ring-brand-500/40">

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-[var(--text-strong)]">{{ row.name }}</span>

                            <span v-if="shownFields.length" class="block truncate text-xs text-[var(--text-muted)]">
                                <span v-for="code in shownFields" :key="code" class="mr-2">
                                    {{ labelOf(code) }}: {{ valueOf(row, code) }}
                                </span>
                            </span>
                        </span>

                        <span v-if="attachedTo(row)" class="text-xs text-[var(--text-muted)]">уже привязано</span>

                        <span v-else-if="row.catalog?.price != null" class="text-xs whitespace-nowrap text-[var(--text-muted)]">
                            {{ row.catalog.price }} {{ row.catalog.currency_symbol }}
                        </span>
                    </label>
                </div>
            </template>
        </div>

        <template #footer>
            <NButton variant="secondary" @click="close">Отмена</NButton>

            <NButton v-if="mode === 'create'" :loading="busy" :disabled="!created.name" @click="createOffer">
                Добавить
            </NButton>

            <NButton v-else :loading="busy" :disabled="!selected.length" @click="attachSelected">
                Привязать{{ selected.length ? ` (${selected.length})` : '' }}
            </NButton>
        </template>
    </NModal>

    <FieldPicker v-model="fieldsOpen" v-model:selected="shownFields" title="Поля предложения"
                 description="Отмеченные свойства будут видны у каждого предложения в списке."
                 :fields="fieldOptions" />
</template>
