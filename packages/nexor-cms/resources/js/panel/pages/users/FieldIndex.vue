<script setup>
import { computed, onMounted, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NField from '../../components/ui/NField.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NModal from '../../components/ui/NModal.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NTable from '../../components/ui/NTable.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useUi } from '../../stores/ui';

/**
 * Свои поля пользователей: их набор и настройки.
 *
 * Поле правится в окне — их немного, и ради каждого уходить со страницы незачем.
 */
const ui = useUi();

const rows = ref([]);
const types = ref([]);
const loading = ref(true);
const open = ref(false);
const editing = ref(null);
const codeTouched = ref(false);

const form = useForm({
    code: '',
    name: '',
    hint: '',
    type: 'string',
    is_required: false,
    is_multiple: false,
    is_shown_in_list: false,
    is_filterable: false,
    is_active: true,
    sort: 500,
    settings: { max_length: '', max_size: '', options: [] },
});

const columns = [
    { key: 'name', label: 'Поле' },
    { key: 'type_label', label: 'Тип', width: '14rem' },
    { key: 'flags', label: 'Свойства' },
    { key: 'values_count', label: 'Заполнено', align: 'center', width: '8rem' },
    { key: 'actions', label: 'Действия', align: 'right', width: '7rem' },
];

const typeOptions = computed(() => {
    const groups = {};

    types.value.forEach((type) => {
        (groups[type.group] ??= []).push({ value: type.value, label: type.label });
    });

    return Object.entries(groups).map(([label, options]) => ({ label, options }));
});

const isSelect = computed(() => form.fields.type === 'select');
const isFile = computed(() => form.fields.type === 'file' || form.fields.type === 'image');
const isText = computed(() => ['string', 'text', 'html'].includes(form.fields.type));

async function load() {
    loading.value = true;

    try {
        const [list, meta] = await Promise.all([api.get('user-fields'), api.get('user-field-types')]);

        rows.value = list.data;
        types.value = meta.types;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value && !editing.value) {
        form.fields.code = slugify(value).replace(/-/g, '_').replace(/^[0-9_]+/, '').slice(0, 100);
    }
}

function create() {
    editing.value = null;
    codeTouched.value = false;
    form.reset();
    form.fields.settings = { max_length: '', max_size: '', options: [] };
    open.value = true;
}

function edit(row) {
    editing.value = row;
    codeTouched.value = true;

    form.reset();
    form.fill({
        code: row.code,
        name: row.name,
        hint: row.hint ?? '',
        type: row.type,
        is_required: row.is_required,
        is_multiple: row.is_multiple,
        is_shown_in_list: row.is_shown_in_list,
        is_filterable: row.is_filterable,
        is_active: row.is_active,
        sort: row.sort,
        settings: {
            max_length: row.settings?.max_length ?? '',
            max_size: row.settings?.max_size ?? '',
            options: (row.settings?.options ?? []).map((option) => ({ ...option })),
        },
    });

    open.value = true;
}

function addOption() {
    form.fields.settings.options.push({ value: '', label: '' });
}

function removeOption(index) {
    form.fields.settings.options.splice(index, 1);
}

async function save() {
    const body = {
        ...form.fields,
        settings: {
            max_length: form.fields.settings.max_length || null,
            max_size: form.fields.settings.max_size || null,
            options: isSelect.value ? form.fields.settings.options.filter((option) => option.value) : [],
        },
    };

    const data = await form.submit(
        editing.value ? 'put' : 'post',
        editing.value ? `user-fields/${editing.value.id}` : 'user-fields',
        { body },
    );

    if (data) {
        open.value = false;
        load();
    }
}

async function remove(row) {
    const confirmed = await ui.confirm({
        title: 'Удалить поле?',
        message: row.values_count
            ? `Поле «${row.name}» и его значения у ${row.values_count} пользователей будут удалены.`
            : `Поле «${row.name}» будет удалено.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`user-fields/${row.id}`);

        ui.notify(data.message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(load);
</script>

<template>
    <div>
        <NPageHeader title="Поля пользователей"
                     description="Свои поля в карточке пользователя: те же типы, что у свойств инфоблоков."
                     :back="{ name: 'users.index' }"
                     :breadcrumbs="[{ label: 'Пользователи', to: { name: 'users.index' } }, { label: 'Поля' }]">
            <template #actions>
                <NButton icon="plus" @click="create">Добавить поле</NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <NEmpty v-if="!loading && rows.length === 0" icon="list-tree" title="Полей нет"
                    description="Например «Город», «Отдел» или «Скан паспорта».">
                <NButton icon="plus" @click="create">Создать поле</NButton>
            </NEmpty>

            <NTable v-else :columns="columns" :rows="rows" :loading="loading">
                <template #cell-name="{ row }">
                    <button type="button" class="text-left font-medium text-[var(--text-strong)] hover:underline"
                            @click="edit(row)">{{ row.name }}</button>
                    <code class="mt-0.5 block font-mono text-xs text-[var(--text-muted)]">{{ row.code }}</code>
                </template>

                <template #cell-flags="{ row }">
                    <div class="flex flex-wrap gap-1.5">
                        <NBadge v-if="row.is_required" color="amber">обязательное</NBadge>
                        <NBadge v-if="row.is_multiple" color="blue">множественное</NBadge>
                        <NBadge v-if="row.is_shown_in_list" color="gray">в списке</NBadge>
                        <NBadge v-if="row.is_filterable" color="gray">в фильтре</NBadge>
                        <NBadge v-if="!row.is_active" color="gray">выключено</NBadge>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-0.5">
                        <button type="button" title="Изменить"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
                                @click="edit(row)">
                            <NIcon name="pencil" size="size-4" />
                        </button>

                        <button type="button" title="Удалить"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="remove(row)">
                            <NIcon name="trash" size="size-4" />
                        </button>
                    </div>
                </template>
            </NTable>
        </NCard>

        <NModal v-model="open" :title="editing ? 'Поле пользователя' : 'Новое поле'" max-width="max-w-2xl">
            <div class="grid gap-5 sm:grid-cols-2">
                <NField label="Название" required :error="form.error('name')">
                    <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                            @update:model-value="onName" />
                </NField>

                <NField label="Символьный код" required hint="По нему поле читается в коде: $user->field('city')"
                        :error="form.error('code')">
                    <NInput v-model="form.fields.code" class="font-mono" :invalid="Boolean(form.error('code'))"
                            @update:model-value="codeTouched = true" />
                </NField>

                <NField label="Тип" required :error="form.error('type')"
                        :hint="editing ? 'У созданного поля тип не меняется' : null">
                    <NSelect v-model="form.fields.type" :options="typeOptions" :disabled="Boolean(editing)" />
                </NField>

                <NField label="Сортировка" :error="form.error('sort')">
                    <NInput v-model="form.fields.sort" type="number" min="0" />
                </NField>

                <div class="sm:col-span-2">
                    <NField label="Подсказка" :error="form.error('hint')">
                        <NInput v-model="form.fields.hint" placeholder="Появится под полем в карточке" />
                    </NField>
                </div>

                <NField v-if="isText" label="Максимальная длина" :error="form.error('settings.max_length')">
                    <NInput v-model="form.fields.settings.max_length" type="number" min="1" placeholder="без ограничения" />
                </NField>

                <NField v-if="isFile" label="Максимальный размер" :error="form.error('settings.max_size')">
                    <NInput v-model="form.fields.settings.max_size" type="number" min="1" max="51200"
                            placeholder="10240" suffix="КБ" />
                </NField>

                <div v-if="isSelect" class="space-y-2 sm:col-span-2">
                    <p class="text-sm font-medium text-[var(--text-strong)]">Варианты списка</p>

                    <div v-for="(option, index) in form.fields.settings.options" :key="index"
                         class="flex items-start gap-2">
                        <NInput v-model="option.value" class="font-mono" placeholder="msk"
                                :invalid="Boolean(form.error(`settings.options.${index}.value`))" />
                        <NInput v-model="option.label" placeholder="Москва" />
                        <button type="button" title="Убрать вариант"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:text-red-600"
                                @click="removeOption(index)">
                            <NIcon name="trash" size="size-4" />
                        </button>
                    </div>

                    <p v-if="form.error('settings.options')" class="text-xs text-red-600">{{ form.error('settings.options') }}</p>

                    <NButton variant="secondary" size="sm" icon="plus" @click="addOption">Вариант</NButton>
                </div>

                <div class="grid gap-3 sm:col-span-2">
                    <NToggle v-model="form.fields.is_required" label="Обязательное" />
                    <NToggle v-model="form.fields.is_multiple" label="Множественное"
                             hint="Можно ввести несколько значений" />
                    <NToggle v-model="form.fields.is_shown_in_list" label="Показывать колонкой в списке" />
                    <NToggle v-model="form.fields.is_filterable" label="Показывать в фильтре списка" />
                    <NToggle v-model="form.fields.is_active" label="Активно"
                             hint="Выключенное поле не показывается в карточке" />
                </div>
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="open = false">Отмена</NButton>
                <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
            </template>
        </NModal>
    </div>
</template>
