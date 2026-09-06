<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import NBadge from './ui/NBadge.vue';
import NButton from './ui/NButton.vue';
import NCard from './ui/NCard.vue';
import NField from './ui/NField.vue';
import NIcon from './ui/NIcon.vue';
import NInput from './ui/NInput.vue';
import NModal from './ui/NModal.vue';
import NSelect from './ui/NSelect.vue';
import NToggle from './ui/NToggle.vue';
import { api, toFormData } from '../api';
import { useForm } from '../composables/useForm';
import { useSession } from '../stores/session';
import { useUi } from '../stores/ui';

/**
 * Renders one or more setting groups and lets the operator grow them.
 *
 * Used both by «Настройки сайта» and by the SMTP screen — the only difference
 * is which groups it is pointed at.
 */
const props = defineProps({
    // null = every group the API returns
    groups: { type: Array, default: null },
    // Allow adding, editing and deleting definitions in these groups
    manageable: { type: Boolean, default: true },
    defaultGroup: { type: String, default: 'contacts' },
});

const session = useSession();
const ui = useUi();

const settings = ref([]);
const groupList = ref([]);
const types = ref([]);
const values = ref({});
const files = ref({});
const busy = ref(false);
const loading = ref(true);

const editorOpen = ref(false);
const editing = ref(null);
const definition = useForm({ key: '', name: '', hint: '', type: 'string', group: props.defaultGroup, sort: 500 });
const options = ref([]);

const canEdit = computed(() => session.can('settings.update'));

const visibleGroups = computed(() => (props.groups
    ? groupList.value.filter((group) => props.groups.includes(group.key))
    : groupList.value));

function inGroup(key) {
    return settings.value.filter((setting) => setting.group === key);
}

async function load() {
    loading.value = true;

    try {
        const data = await api.get('settings');

        settings.value = data.data;
        groupList.value = data.groups;
        types.value = data.types;

        const next = {};
        data.data.forEach((setting) => {
            next[setting.input] = setting.type === 'boolean' ? Boolean(setting.value) : (setting.value ?? '');
        });
        values.value = next;
        files.value = {};
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

async function save() {
    busy.value = true;

    try {
        const payload = toFormData({ settings: values.value });

        Object.entries(files.value).forEach(([input, file]) => {
            if (file === null) {
                payload.append(`file_${input}_remove`, '1');
            } else if (file) {
                payload.append(`file_${input}`, file);
            }
        });

        payload.append('_method', 'PUT');

        const data = await api.post('settings', payload, true);

        ui.notify(data.message);
        await load();
        await session.load();
    } catch (error) {
        ui.notifyError(error);
    } finally {
        busy.value = false;
    }
}

function pickFile(setting, event) {
    files.value[setting.input] = event.target.files?.[0] ?? null;
}

function clearFile(setting) {
    files.value[setting.input] = null;
}

// ---------------------------------------------------------------- definitions

const isNewGroup = ref(false);

/** Existing groups plus an explicit "new group" entry. */
const groupOptions = computed(() => [
    ...groupList.value.map((group) => ({ value: group.key, label: `${group.label} — ${group.key}` })),
    { value: '__new', label: '+ Новая группа' },
]);

function chooseGroup(value) {
    isNewGroup.value = value === '__new';
    definition.fields.group = isNewGroup.value ? '' : value;
}

function openEditor(setting = null, group = null) {
    editing.value = setting;

    definition.reset(setting
        ? {
            key: setting.key,
            name: setting.name,
            hint: setting.hint ?? '',
            type: setting.type,
            group: setting.group,
            sort: setting.sort,
        }
        : { key: '', name: '', hint: '', type: 'string', group: group ?? props.defaultGroup, sort: 500 });

    options.value = (setting?.options ?? []).map((option) => ({ ...option }));
    isNewGroup.value = false;
    editorOpen.value = true;
}

// A fresh setting gets its key suggested from the group and the name.
watch(() => definition.fields.name, (name) => {
    if (editing.value || !name) {
        return;
    }

    const slug = window.Nexor?.slugify?.(name) ?? name.toLowerCase().replace(/[^a-z0-9]+/g, '_');

    definition.fields.key = `${definition.fields.group}.${slug.replace(/-/g, '_')}`;
});

const usesOptions = computed(() => definition.fields.type === 'select');

function addOption() {
    options.value.push({ value: '', label: '' });
}

async function saveDefinition() {
    const payload = { ...definition.fields, options: usesOptions.value ? options.value : [] };

    const data = await definition.submit(
        editing.value ? 'put' : 'post',
        editing.value ? `settings/definitions/${editing.value.id}` : 'settings/definitions',
        { body: payload },
    );

    if (data) {
        editorOpen.value = false;
        load();
    }
}

async function removeDefinition(setting) {
    const confirmed = await ui.confirm({
        title: 'Удалить настройку?',
        message: `Настройка «${setting.name}» и её значение будут удалены.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`settings/definitions/${setting.id}`);

        ui.notify(data.message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(load);

defineExpose({ save, busy, load });
</script>

<template>
    <div class="space-y-6">
        <NCard v-for="group in visibleGroups" :key="group.key" :title="group.label" :code="group.key">
            <template v-if="manageable && canEdit" #actions>
                <NButton type="button" size="sm" variant="secondary" icon="plus" @click="openEditor(null, group.key)">
                    Добавить свойство
                </NButton>
            </template>

            <div class="grid gap-5 sm:grid-cols-2">
                <div v-for="setting in inGroup(group.key)" :key="setting.id"
                     :class="['relative', ['text', 'html', 'image'].includes(setting.type) && 'sm:col-span-2']">
                    <NField :label="setting.name" :hint="setting.hint">
                        <NToggle v-if="setting.type === 'boolean'" v-model="values[setting.input]" label="Включено" />

                        <textarea v-else-if="setting.type === 'text' || setting.type === 'html'"
                                  v-model="values[setting.input]" rows="5"
                                  :class="['field-input resize-y', setting.type === 'html' && 'font-mono text-xs']"></textarea>

                        <NSelect v-else-if="setting.type === 'select'" v-model="values[setting.input]"
                                 :options="setting.options ?? []" placeholder="— не выбрано —" />

                        <div v-else-if="setting.type === 'image'" class="flex items-center gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--surface-border-strong)] px-3 py-2 text-sm font-medium text-[var(--text-base)] transition hover:bg-[var(--surface-muted)]">
                                <NIcon name="upload" size="size-4" />
                                Выбрать файл
                                <input type="file" accept="image/*" class="sr-only"
                                       @change="pickFile(setting, $event)">
                            </label>

                            <span class="truncate text-xs text-[var(--text-muted)]">
                                {{ files[setting.input]?.name ?? (setting.value || 'файл не выбран') }}
                            </span>

                            <button v-if="setting.value || files[setting.input]" type="button"
                                    class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"
                                    @click="clearFile(setting)">
                                убрать
                            </button>
                        </div>

                        <NInput v-else v-model="values[setting.input]"
                                :type="setting.type === 'integer' ? 'number' : (setting.is_encrypted ? 'password' : 'text')" />
                    </NField>

                    <div v-if="manageable && canEdit"
                         class="absolute top-0 right-0 flex items-center gap-0.5">
                        <NBadge v-if="setting.is_system" color="gray">системная</NBadge>

                        <button type="button" title="Изменить свойство"
                                class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]"
                                @click="openEditor(setting)">
                            <NIcon name="pencil" size="size-3.5" />
                        </button>

                        <button v-if="!setting.is_system" type="button" title="Удалить свойство"
                                class="rounded p-1 text-[var(--text-faint)] transition hover:text-red-600"
                                @click="removeDefinition(setting)">
                            <NIcon name="trash" size="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </NCard>

        <div v-if="canEdit" class="flex items-center gap-2">
            <NButton size="lg" :loading="busy" @click="save">Сохранить</NButton>
        </div>

        <NModal v-model="editorOpen" :title="editing ? 'Свойство настроек' : 'Новое свойство настроек'">
            <div class="space-y-5">
                <NField label="Название" required :error="definition.error('name')">
                    <NInput v-model="definition.fields.name" :invalid="Boolean(definition.error('name'))" />
                </NField>

                <NField label="Ключ" required hint="Формат: группа.имя — например contacts.telegram"
                        :error="definition.error('key')">
                    <NInput v-model="definition.fields.key" class="font-mono"
                            :disabled="editing?.is_system"
                            :invalid="Boolean(definition.error('key'))" />
                </NField>

                <div class="grid gap-5 sm:grid-cols-2">
                    <NField label="Тип" required :error="definition.error('type')">
                        <NSelect v-model="definition.fields.type" :options="types" />
                    </NField>

                    <NField label="Группа" required
                            hint="Вкладка, в которой окажется свойство."
                            :error="definition.error('group')">
                        <NSelect :model-value="isNewGroup ? '__new' : definition.fields.group"
                                 :options="groupOptions"
                                 @update:model-value="chooseGroup" />
                    </NField>
                </div>

                <NField v-if="isNewGroup" label="Код новой группы" required
                        hint="Латиница в нижнем регистре, цифры и подчёркивание — например delivery."
                        :error="definition.error('group')">
                    <NInput v-model="definition.fields.group" class="font-mono" placeholder="delivery" />
                </NField>

                <NField label="Подсказка" :error="definition.error('hint')">
                    <NInput v-model="definition.fields.hint" />
                </NField>

                <NField label="Сортировка" :error="definition.error('sort')">
                    <NInput v-model="definition.fields.sort" type="number" min="0" />
                </NField>

                <div v-if="usesOptions" class="space-y-2">
                    <p class="text-sm font-medium text-[var(--text-strong)]">Варианты списка</p>

                    <div v-for="(option, index) in options" :key="index" class="flex items-center gap-2">
                        <NInput v-model="option.value" placeholder="код" class="font-mono" />
                        <NInput v-model="option.label" placeholder="Подпись" />
                        <button type="button"
                                class="shrink-0 rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="options.splice(index, 1)">
                            <NIcon name="trash" size="size-4" />
                        </button>
                    </div>

                    <button type="button"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 transition hover:underline dark:text-brand-400"
                            @click="addOption">
                        <NIcon name="plus" size="size-3.5" />
                        Добавить вариант
                    </button>
                </div>
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="editorOpen = false">Отмена</NButton>
                <NButton size="sm" :loading="definition.busy.value" @click="saveDefinition">Сохранить</NButton>
            </template>
        </NModal>
    </div>
</template>
