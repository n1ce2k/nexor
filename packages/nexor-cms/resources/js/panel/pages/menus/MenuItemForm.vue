<script setup>
import { computed, ref, watch } from 'vue';
import NButton from '../../components/ui/NButton.vue';
import NField from '../../components/ui/NField.vue';
import NInput from '../../components/ui/NInput.vue';
import NModal from '../../components/ui/NModal.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { useForm } from '../../composables/useForm';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * Форма пункта меню.
 *
 * Набор полей зависит от типа: у ссылки адрес, у страницы выбор элемента, у
 * динамического пункта — инфоблок и глубина. Показывать всё сразу нельзя, иначе
 * форма требует лишнего и путает.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    menu: { type: Object, default: null },
    item: { type: Object, default: null },
    types: { type: Array, default: () => [] },
    visibility: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const session = useSession();
const ui = useUi();

const form = useForm({
    type: 'link',
    title: '',
    url: '',
    iblock_id: null,
    element_id: null,
    section_id: null,
    max_depth: 2,
    with_elements: false,
    target: '',
    css_class: '',
    icon: '',
    visibility: 'all',
    highlight_children: true,
    is_active: true,
});

const elements = ref([]);
const sections = ref([]);
const loadingSource = ref(false);

const isEdit = computed(() => Boolean(props.item?.id));

const typeHint = computed(() => props.types.find((one) => one.value === form.fields.type)?.hint);

/** Поля, которые показывает форма этого типа. */
const shows = computed(() => ({
    url: form.fields.type === 'link',
    iblock: ['page', 'section', 'sections'].includes(form.fields.type),
    element: form.fields.type === 'page',
    section: form.fields.type === 'section',
    root: form.fields.type === 'sections',
    depth: form.fields.type === 'sections',
    title: form.fields.type !== 'divider',
    // У динамического пункта своего названия нет — он раскрывается в разделы.
    titleRequired: ['link', 'heading'].includes(form.fields.type),
    link: ['link', 'page', 'section'].includes(form.fields.type),
}));

const iblockOptions = computed(() => session.iblocks.map((one) => ({ value: one.id, label: one.name })));

const sectionedOnly = computed(() => session.iblocks
    .filter((one) => one.has_sections)
    .map((one) => ({ value: one.id, label: one.name })));

watch(() => props.modelValue, (open) => {
    if (!open) {
        return;
    }

    form.reset(props.item
        ? {
            type: props.item.type,
            title: props.item.title ?? '',
            url: props.item.url ?? '',
            iblock_id: props.item.iblock_id,
            element_id: props.item.element_id,
            section_id: props.item.section_id,
            max_depth: props.item.max_depth ?? 2,
            with_elements: props.item.with_elements ?? false,
            target: props.item.target ?? '',
            css_class: props.item.css_class ?? '',
            icon: props.item.icon ?? '',
            visibility: props.item.visibility ?? 'all',
            highlight_children: props.item.highlight_children ?? true,
            is_active: props.item.is_active ?? true,
        }
        : {
            type: 'link', title: '', url: '', iblock_id: null, element_id: null, section_id: null,
            max_depth: 2, with_elements: false, target: '', css_class: '', icon: '',
            visibility: 'all', highlight_children: true, is_active: true,
        });

    loadSource();
});

// Сменили инфоблок — прежние страница и раздел к нему уже не относятся.
watch(() => form.fields.iblock_id, (value, previous) => {
    if (previous !== undefined && value !== previous) {
        form.fields.element_id = null;
        form.fields.section_id = null;
    }

    loadSource();
});

async function loadSource() {
    const id = form.fields.iblock_id;

    if (!id || !shows.value.iblock) {
        elements.value = [];
        sections.value = [];

        return;
    }

    loadingSource.value = true;

    try {
        const schema = await api.get(`iblocks/${id}/schema`);

        sections.value = (schema.sections ?? []).map((one) => ({
            value: one.id,
            label: one.indented_name,
        }));

        if (shows.value.element) {
            const data = await api.get(`iblocks/${id}/elements`, { per_page: 200 });

            elements.value = data.data.map((one) => ({ value: one.id, label: one.name }));
        }
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loadingSource.value = false;
    }
}

async function save() {
    const payload = { ...form.fields };

    // Пустые строки в необязательных полях — это отсутствие значения.
    ['target', 'css_class', 'icon', 'url', 'title'].forEach((key) => {
        payload[key] = payload[key] === '' ? null : payload[key];
    });

    const data = await form.submit(
        isEdit.value ? 'put' : 'post',
        isEdit.value
            ? `menus/${props.menu.id}/items/${props.item.id}`
            : `menus/${props.menu.id}/items`,
        { body: { ...payload, parent_id: props.item?.parent_id ?? null } },
    );

    if (data) {
        emit('saved');
        emit('update:modelValue', false);
    }
}
</script>

<template>
    <NModal :model-value="modelValue" :title="isEdit ? 'Пункт меню' : 'Новый пункт меню'" max-width="max-w-2xl"
            @update:model-value="emit('update:modelValue', $event)">
        <div class="space-y-5">
            <NField label="Тип пункта" required :hint="typeHint" :error="form.error('type')">
                <NSelect v-model="form.fields.type" :options="types" />
            </NField>

            <NField v-if="shows.iblock" label="Инфоблок" required :error="form.error('iblock_id')">
                <NSelect v-model="form.fields.iblock_id"
                         :options="shows.root ? sectionedOnly : iblockOptions"
                         placeholder="Выберите инфоблок" />
            </NField>

            <NField v-if="shows.element" label="Страница" required
                    hint="Адрес считается сам и переживёт переименование кода."
                    :error="form.error('element_id')">
                <NSelect v-model="form.fields.element_id" :options="elements"
                         :placeholder="loadingSource ? 'Загружаем…' : 'Выберите страницу'" />
            </NField>

            <NField v-if="shows.section" label="Раздел" required :error="form.error('section_id')">
                <NSelect v-model="form.fields.section_id" :options="sections"
                         :placeholder="loadingSource ? 'Загружаем…' : 'Выберите раздел'" />
            </NField>

            <template v-if="shows.root">
                <NField label="От какого раздела" hint="Пусто — от корня инфоблока."
                        :error="form.error('section_id')">
                    <NSelect v-model="form.fields.section_id" :options="sections"
                             placeholder="— весь инфоблок —" />
                </NField>

                <div class="grid gap-5 sm:grid-cols-2">
                    <NField label="Глубина" hint="Сколько уровней разделов разворачивать."
                            :error="form.error('max_depth')">
                        <NInput v-model="form.fields.max_depth" type="number" min="1" max="5" />
                    </NField>

                    <NField label="Элементы">
                        <NToggle v-model="form.fields.with_elements" label="Показывать и элементы" />
                    </NField>
                </div>
            </template>

            <NField v-if="shows.title" :label="shows.titleRequired ? 'Название' : 'Название (необязательно)'"
                    :required="shows.titleRequired"
                    :hint="shows.titleRequired ? null : 'Пусто — возьмём имя выбранной сущности.'"
                    :error="form.error('title')">
                <NInput v-model="form.fields.title" />
            </NField>

            <NField v-if="shows.url" label="Адрес" required
                    hint="Внешняя ссылка, якорь или свой путь: /katalog, https://…, #contacts"
                    :error="form.error('url')">
                <NInput v-model="form.fields.url" class="font-mono" placeholder="/katalog" />
            </NField>

            <div v-if="shows.link" class="grid gap-5 sm:grid-cols-2">
                <NField label="Открывать">
                    <NSelect v-model="form.fields.target"
                             :options="[{ value: '', label: 'В этой вкладке' }, { value: '_blank', label: 'В новой вкладке' }]" />
                </NField>

                <NField label="Подсветка">
                    <NToggle v-model="form.fields.highlight_children"
                             label="Активен и на вложенных страницах" />
                </NField>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <NField label="Кому виден" :error="form.error('visibility')">
                    <NSelect v-model="form.fields.visibility" :options="visibility" />
                </NField>

                <NField label="Активность">
                    <NToggle v-model="form.fields.is_active" label="Показывать на сайте" />
                </NField>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <NField label="CSS-класс" :error="form.error('css_class')">
                    <NInput v-model="form.fields.css_class" class="font-mono" />
                </NField>

                <NField label="Иконка" hint="Имя иконки для вашего шаблона." :error="form.error('icon')">
                    <NInput v-model="form.fields.icon" class="font-mono" />
                </NField>
            </div>
        </div>

        <template #footer>
            <NButton variant="secondary" size="sm" @click="emit('update:modelValue', false)">Отмена</NButton>
            <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
        </template>
    </NModal>
</template>
