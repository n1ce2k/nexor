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
import NToggle from '../../components/ui/NToggle.vue';
import NTable from '../../components/ui/NTable.vue';
import { api } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

const session = useSession();
const ui = useUi();

const rows = ref([]);
const loading = ref(true);
const open = ref(false);
const editing = ref(null);
const codeTouched = ref(false);

const form = useForm({
    code: '', name: '', sections_name: '', elements_name: '', description: '',
    has_sections: true, is_active: true, sort: 500,
});

const canEdit = computed(() => session.can('iblock_types.update'));

const columns = [
    { key: 'name', label: 'Название' },
    { key: 'code', label: 'Код', muted: true, width: '12rem' },
    { key: 'has_sections', label: 'Разделы', width: '12rem' },
    { key: 'iblocks_count', label: 'Инфоблоков', align: 'center', width: '9rem', muted: true },
    { key: 'is_active', label: 'Статус', align: 'center', width: '7rem' },
    { key: 'actions', label: 'Действия', align: 'right', width: '7rem' },
];

async function load() {
    loading.value = true;

    try {
        const data = await api.get('iblock-types', { per_page: 100 });

        rows.value = data.data;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function openEditor(type = null) {
    editing.value = type;
    codeTouched.value = Boolean(type);

    form.reset(type
        ? {
            code: type.code,
            name: type.name,
            sections_name: type.sections_name ?? '',
            elements_name: type.elements_name ?? '',
            description: type.description ?? '',
            has_sections: type.has_sections,
            is_active: type.is_active,
            sort: type.sort,
        }
        : {
            code: '', name: '', sections_name: '', elements_name: '', description: '',
            has_sections: true, is_active: true, sort: 500,
        });

    open.value = true;
}

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value) {
        form.fields.code = slugify(value);
    }
}

async function save() {
    const data = await form.submit(
        editing.value ? 'put' : 'post',
        editing.value ? `iblock-types/${editing.value.id}` : 'iblock-types',
    );

    if (data) {
        open.value = false;
        load();
    }
}

async function remove(type) {
    const confirmed = await ui.confirm({
        title: 'Удалить тип инфоблоков?',
        message: `Тип «${type.name}» будет удалён.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`iblock-types/${type.id}`);

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
        <NPageHeader title="Типы инфоблоков"
                     description="Верхний уровень структуры: группируют инфоблоки по назначению.">
            <template #actions>
                <NButton v-if="session.can('iblock_types.create')" icon="plus" @click="openEditor()">
                    Добавить тип
                </NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <NEmpty v-if="!loading && rows.length === 0" icon="database" title="Типов пока нет"
                    description="Тип инфоблоков — это, например, «Контент», «Каталог» или «Служебные».">
                <NButton v-if="session.can('iblock_types.create')" icon="plus" @click="openEditor()">
                    Создать тип
                </NButton>
            </NEmpty>

            <NTable v-else :columns="columns" :rows="rows" :loading="loading">
                <template #cell-name="{ row }">
                    <p class="font-medium text-[var(--text-strong)]">{{ row.name }}</p>
                    <p v-if="row.description" class="mt-0.5 text-xs text-[var(--text-muted)]">{{ row.description }}</p>
                </template>

                <template #cell-code="{ row }">
                    <code class="font-mono text-xs">{{ row.code }}</code>
                </template>

                <template #cell-has_sections="{ row }">
                    <NBadge :color="row.has_sections ? 'blue' : 'gray'">
                        {{ row.has_sections ? 'используются' : 'не используются' }}
                    </NBadge>
                </template>

                <template #cell-is_active="{ row }">
                    <NBadge :color="row.is_active ? 'green' : 'gray'">
                        {{ row.is_active ? 'активен' : 'выключен' }}
                    </NBadge>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-0.5">
                        <button v-if="canEdit" type="button" title="Изменить"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
                                @click="openEditor(row)">
                            <NIcon name="pencil" size="size-4" />
                        </button>

                        <button v-if="session.can('iblock_types.delete')" type="button" title="Удалить"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="remove(row)">
                            <NIcon name="trash" size="size-4" />
                        </button>
                    </div>
                </template>
            </NTable>
        </NCard>

        <NModal v-model="open" :title="editing ? 'Тип инфоблоков' : 'Новый тип инфоблоков'">
            <div class="space-y-5">
                <NField label="Название" required :error="form.error('name')">
                    <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                            @update:model-value="onName" />
                </NField>

                <NField label="Символьный код" required :error="form.error('code')">
                    <NInput v-model="form.fields.code" class="font-mono"
                            :invalid="Boolean(form.error('code'))"
                            @update:model-value="codeTouched = true" />
                </NField>

                <div class="grid gap-5 sm:grid-cols-2">
                    <NField label="Название разделов" hint="Например: «Категории»">
                        <NInput v-model="form.fields.sections_name" placeholder="Разделы" />
                    </NField>

                    <NField label="Название элементов" hint="Например: «Товары», «Цвета»">
                        <NInput v-model="form.fields.elements_name" placeholder="Элементы" />
                    </NField>
                </div>

                <NField label="Описание">
                    <textarea v-model="form.fields.description" rows="3" class="field-input resize-y"></textarea>
                </NField>

                <NToggle v-model="form.fields.has_sections" label="Использовать разделы"
                         hint="Древовидная структура внутри инфоблоков." />

                <NToggle v-model="form.fields.is_active" label="Активен" />

                <NField label="Сортировка" :error="form.error('sort')">
                    <NInput v-model="form.fields.sort" type="number" min="0" />
                </NField>
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="open = false">Отмена</NButton>
                <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
            </template>
        </NModal>
    </div>
</template>
