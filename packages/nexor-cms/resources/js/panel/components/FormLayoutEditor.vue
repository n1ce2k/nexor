<script setup>
import { computed, ref, watch } from 'vue';
import Draggable from 'vuedraggable';
import NButton from './ui/NButton.vue';
import NIcon from './ui/NIcon.vue';
import NInput from './ui/NInput.vue';
import NModal from './ui/NModal.vue';
import NSelect from './ui/NSelect.vue';
import { api } from '../api';
import { useUi } from '../stores/ui';

/**
 * Настройка вкладок формы элемента — как «Настройка формы» в Битриксе.
 *
 * Вкладки и поля перетаскиваются мышью; поле можно перетащить прямо в другую
 * вкладку. Кнопки со стрелками оставлены рядом: с клавиатуры и на тач-экране
 * они надёжнее, а выпадающий список переносит поле точнее, когда вкладок много
 * и до нужной надо тащить далеко.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    iblock: { type: [String, Number], required: true },
    // [{ key, label, fields: [] }]
    tabs: { type: Array, required: true },
    // [{ key, label, group }]
    fields: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const ui = useUi();

const draft = ref([]);
const busy = ref(false);

/**
 * Раскладка полей: ключ вкладки → упорядоченный список ключей полей.
 *
 * Единственный источник правды для нижней половины окна. Перетаскивание требует
 * настоящего массива, который можно менять на месте, — вычисляемый список для
 * этого не годится.
 */
const groups = ref({});

const fieldByKey = computed(() => Object.fromEntries(props.fields.map((field) => [field.key, field])));

const tabOptions = computed(() => draft.value.map((tab) => ({ value: tab.key, label: tab.label || tab.key })));

function reset() {
    draft.value = props.tabs.map((tab) => ({ ...tab, fields: [...tab.fields] }));

    const next = {};
    const placed = [];

    draft.value.forEach((tab) => {
        next[tab.key] = tab.fields.filter((field) => {
            const known = Boolean(fieldByKey.value[field]) && ! placed.includes(field);

            known && placed.push(field);

            return known;
        });
    });

    // Поле, которого нет ни на одной вкладке, отправляется на первую.
    const first = draft.value[0]?.key;

    props.fields.forEach((field) => {
        if (! placed.includes(field.key) && first) {
            next[first].push(field.key);
        }
    });

    groups.value = next;
}

watch(() => props.modelValue, (open) => open && reset(), { immediate: true });

// -------------------------------------------------------------------- вкладки

function addTab() {
    const index = draft.value.length + 1;
    const key = `tab_${index}`;

    draft.value.push({ key, label: `Вкладка ${index}`, fields: [] });
    groups.value[key] = [];
}

function removeTab(index) {
    if (draft.value.length === 1) {
        ui.notify('Нужна хотя бы одна вкладка.', 'error');

        return;
    }

    const removed = draft.value[index];
    const fallback = draft.value[index === 0 ? 1 : 0].key;

    draft.value.splice(index, 1);

    // Поля удалённой вкладки не должны исчезнуть вместе с ней.
    groups.value[fallback] = [...groups.value[fallback], ...(groups.value[removed.key] ?? [])];
    delete groups.value[removed.key];
}

function moveTab(index, delta) {
    const target = index + delta;

    if (target < 0 || target >= draft.value.length) {
        return;
    }

    const [tab] = draft.value.splice(index, 1);

    draft.value.splice(target, 0, tab);
}

/** Переименование кода вкладки не должно ронять её поля. */
function renameTab(tab, key) {
    const previous = tab.key;

    if (key === previous) {
        return;
    }

    groups.value[key] = groups.value[previous] ?? [];
    delete groups.value[previous];
    tab.key = key;
}

// ---------------------------------------------------------------------- поля

function fieldsOf(key) {
    return (groups.value[key] ?? []).map((field) => fieldByKey.value[field]).filter(Boolean);
}

function tabOf(fieldKey) {
    return Object.keys(groups.value).find((key) => groups.value[key].includes(fieldKey)) ?? null;
}

function moveField(fieldKey, delta) {
    const key = tabOf(fieldKey);
    const list = groups.value[key];
    const index = list.indexOf(fieldKey);
    const target = index + delta;

    if (target < 0 || target >= list.length) {
        return;
    }

    list.splice(target, 0, ...list.splice(index, 1));
}

function placeField(fieldKey, tabKey) {
    const from = tabOf(fieldKey);

    if (! tabKey || from === tabKey) {
        return;
    }

    groups.value[from] = groups.value[from].filter((one) => one !== fieldKey);
    groups.value[tabKey] = [...(groups.value[tabKey] ?? []), fieldKey];
}

// -------------------------------------------------------------------- запись

async function save() {
    busy.value = true;

    try {
        const tabs = draft.value.map((tab) => ({
            key: tab.key,
            label: tab.label,
            fields: groups.value[tab.key] ?? [],
        }));

        const data = await api.put(`iblocks/${props.iblock}/form-layout`, { tabs });

        ui.notify(data.message);
        emit('saved', data.tabs);
        emit('update:modelValue', false);
    } catch (error) {
        ui.notifyError(error);
    } finally {
        busy.value = false;
    }
}

async function restoreDefaults() {
    const confirmed = await ui.confirm({
        title: 'Вернуть стандартные вкладки?',
        message: 'Своя раскладка формы будет удалена.',
    });

    if (! confirmed) {
        return;
    }

    busy.value = true;

    try {
        const data = await api.put(`iblocks/${props.iblock}/form-layout`, { tabs: [] });

        ui.notify(data.message);
        emit('saved', data.tabs);
        emit('update:modelValue', false);
    } catch (error) {
        ui.notifyError(error);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <NModal :model-value="modelValue" title="Настройка формы" max-width="max-w-3xl"
            @update:model-value="emit('update:modelValue', $event)">
        <div class="space-y-6">
            <section class="space-y-2">
                <p class="text-sm font-medium text-[var(--text-strong)]">Вкладки</p>

                <Draggable :list="draft" item-key="key" handle=".tab-handle" :animation="150"
                           ghost-class="opacity-40" class="space-y-2">
                    <template #item="{ element: tab, index }">
                        <div class="flex items-center gap-2">
                            <span class="tab-handle cursor-grab text-[var(--text-faint)] active:cursor-grabbing"
                                  title="Перетащите, чтобы переставить">
                                <NIcon name="grip" size="size-4" />
                            </span>

                            <NInput v-model="tab.label" class="flex-1" placeholder="Название вкладки" />

                            <NInput :model-value="tab.key" class="w-40 font-mono text-xs" placeholder="код"
                                    @update:model-value="renameTab(tab, $event)" />

                            <div class="flex shrink-0 items-center gap-0.5">
                                <button type="button" title="Выше" :disabled="index === 0"
                                        class="rounded p-1.5 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] disabled:opacity-30"
                                        @click="moveTab(index, -1)">
                                    <NIcon name="chevron-up" size="size-4" />
                                </button>

                                <button type="button" title="Ниже" :disabled="index === draft.length - 1"
                                        class="rounded p-1.5 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] disabled:opacity-30"
                                        @click="moveTab(index, 1)">
                                    <NIcon name="chevron-down" size="size-4" />
                                </button>

                                <button type="button" title="Удалить вкладку"
                                        class="rounded p-1.5 text-[var(--text-muted)] transition hover:text-red-600"
                                        @click="removeTab(index)">
                                    <NIcon name="trash" size="size-4" />
                                </button>
                            </div>
                        </div>
                    </template>
                </Draggable>

                <button type="button"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 transition hover:underline dark:text-brand-400"
                        @click="addTab">
                    <NIcon name="plus" size="size-3.5" />
                    Добавить вкладку
                </button>
            </section>

            <section class="space-y-3">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-medium text-[var(--text-strong)]">Поля</p>
                    <p class="text-xs text-[var(--text-muted)]">Поле можно перетащить в другую вкладку</p>
                </div>

                <div v-for="tab in draft" :key="`fields-${tab.key}`"
                     class="rounded-xl border border-[var(--surface-border)] p-3">
                    <p class="mb-2 text-xs font-semibold tracking-wide text-[var(--text-muted)] uppercase">
                        {{ tab.label || tab.key }}
                    </p>

                    <Draggable :list="groups[tab.key]" :group="{ name: 'layout-fields' }" item-key="."
                               handle=".field-handle" :animation="150" ghost-class="opacity-40"
                               class="min-h-8 space-y-1.5">
                        <template #item="{ element: key }">
                            <div v-if="fieldByKey[key]" class="flex items-center gap-2">
                                <span class="field-handle cursor-grab text-[var(--text-faint)] active:cursor-grabbing"
                                      title="Перетащите в нужную вкладку">
                                    <NIcon name="grip" size="size-3.5" />
                                </span>

                                <span class="min-w-0 flex-1 truncate text-sm text-[var(--text-strong)]">
                                    {{ fieldByKey[key].label }}
                                    <code v-if="fieldByKey[key].group === 'property'"
                                          class="ml-1 rounded bg-[var(--surface-muted)] px-1 py-0.5 font-mono text-[10px] text-[var(--text-muted)]">
                                        свойство
                                    </code>
                                </span>

                                <button type="button" title="Выше"
                                        class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]"
                                        @click="moveField(key, -1)">
                                    <NIcon name="chevron-up" size="size-3.5" />
                                </button>

                                <button type="button" title="Ниже"
                                        class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]"
                                        @click="moveField(key, 1)">
                                    <NIcon name="chevron-down" size="size-3.5" />
                                </button>

                                <NSelect :model-value="tab.key" :options="tabOptions" class="w-44 shrink-0"
                                         @update:model-value="placeField(key, $event)" />
                            </div>
                        </template>
                    </Draggable>

                    <p v-if="!fieldsOf(tab.key).length" class="text-xs text-[var(--text-faint)]">
                        Пусто — перетащите сюда поле из другой вкладки.
                    </p>
                </div>
            </section>
        </div>

        <template #footer>
            <NButton variant="secondary" size="sm" :disabled="busy" @click="restoreDefaults">
                Стандартные вкладки
            </NButton>
            <NButton variant="secondary" size="sm" @click="emit('update:modelValue', false)">Отмена</NButton>
            <NButton size="sm" :loading="busy" @click="save">Сохранить</NButton>
        </template>
    </NModal>
</template>
