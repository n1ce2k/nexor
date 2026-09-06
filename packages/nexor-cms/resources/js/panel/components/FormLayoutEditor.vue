<script setup>
import { computed, ref, watch } from 'vue';
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
 * Вкладки переименовываются, добавляются, удаляются и меняются местами; каждое
 * поле выбирает свою вкладку из выпадающего списка. Раскладка хранится в
 * настройках инфоблока, поэтому у каждого инфоблока она своя.
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

/** Поля, разложенные по вкладкам, в виде «ключ поля → ключ вкладки». */
const placement = ref({});

const fieldLabel = computed(() => Object.fromEntries(props.fields.map((field) => [field.key, field.label])));

const tabOptions = computed(() => draft.value.map((tab) => ({ value: tab.key, label: tab.label || tab.key })));

function reset() {
    draft.value = props.tabs.map((tab) => ({ ...tab, fields: [...tab.fields] }));

    const next = {};
    draft.value.forEach((tab) => tab.fields.forEach((field) => {
        next[field] = tab.key;
    }));

    // Поле, которого нет ни на одной вкладке, отправляется на первую.
    props.fields.forEach((field) => {
        next[field.key] ??= draft.value[0]?.key;
    });

    placement.value = next;
}

watch(() => props.modelValue, (open) => open && reset(), { immediate: true });

function addTab() {
    const index = draft.value.length + 1;

    draft.value.push({ key: `tab_${index}`, label: `Вкладка ${index}`, fields: [] });
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
    Object.keys(placement.value).forEach((field) => {
        if (placement.value[field] === removed.key) {
            placement.value[field] = fallback;
        }
    });
}

function moveTab(index, delta) {
    const target = index + delta;

    if (target < 0 || target >= draft.value.length) {
        return;
    }

    const [tab] = draft.value.splice(index, 1);

    draft.value.splice(target, 0, tab);
}

/**
 * Поля вкладки в том порядке, в котором их показывает форма.
 *
 * Порядок задаёт сама вкладка; поле, только что переехавшее сюда из другой,
 * в её списке ещё не значится и встаёт в конец.
 */
function fieldsOf(key) {
    const order = draft.value.find((tab) => tab.key === key)?.fields ?? [];
    const position = (field) => {
        const index = order.indexOf(field.key);

        return index === -1 ? Number.MAX_SAFE_INTEGER : index;
    };

    return props.fields
        .filter((field) => placement.value[field.key] === key)
        .sort((a, b) => position(a) - position(b));
}

function moveField(fieldKey, delta) {
    const tabKey = placement.value[fieldKey];
    const tab = draft.value.find((item) => item.key === tabKey);
    const order = fieldsOf(tabKey).map((field) => field.key);
    const index = order.indexOf(fieldKey);
    const target = index + delta;

    if (!tab || target < 0 || target >= order.length) {
        return;
    }

    order.splice(target, 0, ...order.splice(index, 1));

    tab.fields = order;
}

async function save() {
    busy.value = true;

    try {
        const tabs = draft.value.map((tab) => {
            const explicit = tab.fields.filter((field) => placement.value[field] === tab.key);
            const rest = props.fields
                .map((field) => field.key)
                .filter((field) => placement.value[field] === tab.key && !explicit.includes(field));

            return { key: tab.key, label: tab.label, fields: [...explicit, ...rest] };
        });

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

    if (!confirmed) {
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

                <div v-for="(tab, index) in draft" :key="index" class="flex items-center gap-2">
                    <NInput v-model="tab.label" class="flex-1" placeholder="Название вкладки" />

                    <NInput v-model="tab.key" class="w-40 font-mono text-xs" placeholder="код" />

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

                <button type="button"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 transition hover:underline dark:text-brand-400"
                        @click="addTab">
                    <NIcon name="plus" size="size-3.5" />
                    Добавить вкладку
                </button>
            </section>

            <section class="space-y-3">
                <p class="text-sm font-medium text-[var(--text-strong)]">Поля</p>

                <div v-for="tab in draft" :key="`fields-${tab.key}`"
                     class="rounded-xl border border-[var(--surface-border)] p-3">
                    <p class="mb-2 text-xs font-semibold tracking-wide text-[var(--text-muted)] uppercase">
                        {{ tab.label || tab.key }}
                    </p>

                    <div class="space-y-1.5">
                        <div v-for="field in fieldsOf(tab.key)" :key="field.key"
                             class="flex items-center gap-2">
                            <span class="min-w-0 flex-1 truncate text-sm text-[var(--text-strong)]">
                                {{ fieldLabel[field.key] }}
                                <code v-if="field.group === 'property'"
                                      class="ml-1 rounded bg-[var(--surface-muted)] px-1 py-0.5 font-mono text-[10px] text-[var(--text-muted)]">
                                    свойство
                                </code>
                            </span>

                            <button type="button" title="Выше"
                                    class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]"
                                    @click="moveField(field.key, -1)">
                                <NIcon name="chevron-up" size="size-3.5" />
                            </button>

                            <button type="button" title="Ниже"
                                    class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]"
                                    @click="moveField(field.key, 1)">
                                <NIcon name="chevron-down" size="size-3.5" />
                            </button>

                            <NSelect v-model="placement[field.key]" :options="tabOptions" class="w-44 shrink-0" />
                        </div>

                        <p v-if="!fieldsOf(tab.key).length" class="text-xs text-[var(--text-faint)]">
                            Пусто — перенесите сюда поле из другой вкладки.
                        </p>
                    </div>
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
