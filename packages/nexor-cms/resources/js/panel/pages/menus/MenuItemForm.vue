<script setup>
import { computed, ref, watch } from 'vue';
import MenuItemFields from './MenuItemFields.vue';
import NButton from '../../components/ui/NButton.vue';
import NModal from '../../components/ui/NModal.vue';
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
 *
 * Открывается окном, а с `inline` — прямо в списке ссылок подменю, как поле в
 * редакторе формы обратной связи.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    menu: { type: Object, default: null },
    item: { type: Object, default: null },
    // Новый пункт кладётся внутрь этого пункта
    parentId: { type: Number, default: null },
    inline: { type: Boolean, default: false },
    types: { type: Array, default: () => [] },
    visibility: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'saved', 'cancel']);

const session = useSession();
const ui = useUi();

/** Пустой пункт: тип по умолчанию — ссылка. */
function blank() {
    return {
        type: 'link', title: '', url: '', iblock_id: null, element_id: null, section_id: null,
        max_depth: 2, with_elements: false, with_title: false, target: '', css_class: '', icon: '',
        visibility: 'all', highlight_children: true, is_active: true,
    };
}

const form = useForm(blank());

const elements = ref([]);
const sections = ref([]);
const loadingSource = ref(false);

const isEdit = computed(() => Boolean(props.item?.id));

const typeHint = computed(() => props.types.find((one) => one.value === form.fields.type)?.hint);

/** Поля, которые показывает форма этого типа. */
const shows = computed(() => {
    const type = form.fields.type;
    const submenu = type === 'submenu';

    return {
        url: ['link', 'submenu'].includes(type),
        urlRequired: type === 'link',
        urlLabel: submenu ? 'Ссылка (необязательно)' : 'Адрес',
        urlHint: submenu
            ? 'Куда ведёт сам пункт: /uslugi, https://…, #contacts. Пусто — пункт только раскрывает список ссылок.'
            : 'Внешняя ссылка, якорь или свой путь: /katalog, https://…, #contacts',
        // У подменю название и ссылка — сразу под типом, глубина и вид — ниже.
        linkFirst: submenu,
        iblock: ['page', 'section', 'sections'].includes(type),
        element: type === 'page',
        section: type === 'section',
        root: type === 'sections',
        depth: ['sections', 'submenu'].includes(type),
        depthHint: submenu
            ? 'Сколько уровней ссылок можно вложить: 1 — только ссылки, 2 — у ссылок свои ссылки.'
            : 'Сколько уровней разделов разворачивать.',
        // У подменю переключателя нет: его ссылки всегда внутри своего пункта.
        withTitle: type === 'sections',
        withTitleHint: 'Выключено — разделы встают на место пункта. Включено — пункт рисуется сам, разделы уходят внутрь него.',
        title: type !== 'divider',
        // У динамического пункта своего названия нет — он раскрывается в разделы.
        titleRequired: ['link', 'heading', 'submenu'].includes(type),
        link: ['link', 'page', 'section', 'submenu'].includes(type),
    };
});

/** Откуда возьмётся название, если своё не задали. */
const titleHint = computed(() => {
    if (shows.value.titleRequired) {
        return null;
    }

    if (shows.value.root) {
        return form.fields.with_title
            ? 'Пусто — имя выбранного раздела, а без раздела — имя инфоблока.'
            : 'При выключенном «Выводить название» в меню не попадёт.';
    }

    return 'Пусто — возьмём имя выбранной сущности.';
});

const iblockOptions = computed(() => session.iblocks.map((one) => ({ value: one.id, label: one.name })));

const sectionedOnly = computed(() => session.iblocks
    .filter((one) => one.has_sections)
    .map((one) => ({ value: one.id, label: one.name })));

/** Заполняет форму пунктом или пустыми значениями. */
function fill() {
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
            with_title: props.item.with_title ?? false,
            target: props.item.target ?? '',
            css_class: props.item.css_class ?? '',
            icon: props.item.icon ?? '',
            visibility: props.item.visibility ?? 'all',
            highlight_children: props.item.highlight_children ?? true,
            is_active: props.item.is_active ?? true,
        }
        : blank());

    loadSource();
}

// Окно заполняется при открытии, встроенная форма — сразу.
watch(() => props.modelValue || props.inline, (open) => {
    if (open) {
        fill();
    }
}, { immediate: true });

// Подменю — один уровень ссылок, разделам инфоблока — прежние два уровня на
// месте пункта. Срабатывает и при правке, когда пункту меняют тип: иначе
// бывшая ссылка становилась подменю с её глубиной.
watch(() => form.fields.type, (type, previous) => {
    if (previous === undefined || type === props.item?.type) {
        return;
    }

    if (type === 'submenu') {
        form.fields.max_depth = 1;
    } else if (type === 'sections') {
        Object.assign(form.fields, { max_depth: 2, with_title: false });
    }
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

function close() {
    props.inline ? emit('cancel') : emit('update:modelValue', false);
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
        { body: { ...payload, parent_id: props.item?.parent_id ?? props.parentId ?? null } },
    );

    if (data) {
        emit('saved', data.data);

        if (!props.inline) {
            emit('update:modelValue', false);
        }
    }
}

const fieldProps = computed(() => ({
    form,
    shows: shows.value,
    types: props.types,
    visibility: props.visibility,
    typeHint: typeHint.value,
    titleHint: titleHint.value,
    iblockOptions: iblockOptions.value,
    sectionedOnly: sectionedOnly.value,
    elements: elements.value,
    sections: sections.value,
    loadingSource: loadingSource.value,
}));
</script>

<template>
    <div v-if="inline" class="surface space-y-4 rounded-xl border p-4">
        <p class="text-sm font-medium text-[var(--text-strong)]">
            {{ isEdit ? 'Ссылка подменю' : 'Новая ссылка подменю' }}
        </p>

        <MenuItemFields v-bind="fieldProps" />

        <div class="flex justify-end gap-2">
            <NButton variant="secondary" size="sm" @click="close">Отмена</NButton>
            <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
        </div>
    </div>

    <NModal v-else :model-value="modelValue" :title="isEdit ? 'Пункт меню' : 'Новый пункт меню'" max-width="max-w-2xl"
            @update:model-value="emit('update:modelValue', $event)">
        <MenuItemFields v-bind="fieldProps" />

        <template #footer>
            <NButton variant="secondary" size="sm" @click="close">Отмена</NButton>
            <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
        </template>
    </NModal>
</template>
