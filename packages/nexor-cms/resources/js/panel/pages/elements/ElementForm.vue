<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import FormLayoutEditor from '../../components/FormLayoutEditor.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NHtmlInput from '../../components/ui/NHtmlInput.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NTabs from '../../components/ui/NTabs.vue';
import NToggle from '../../components/ui/NToggle.vue';
import PropertyField from '../../components/fields/PropertyField.vue';
import { api, toFormData } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { emit as emitHook } from '../../registry';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * The element editor is generated from the infoblock's schema: base fields plus
 * one PropertyField per property, laid out across the tabs the infoblock
 * defines. Nothing about a particular infoblock is hardcoded here.
 */
const props = defineProps({
    iblock: { type: [String, Number], required: true },
    element: { type: [String, Number], default: null },
});

const router = useRouter();
const session = useSession();
const ui = useUi();

const schema = ref(null);
const values = ref({});
const codeTouched = ref(false);
const ready = ref(false);

const tabs = ref([]);
const activeTab = ref(null);
const layoutOpen = ref(false);

const form = useForm({
    name: '',
    code: '',
    section_id: null,
    sections: [],
    preview_text: '',
    preview_text_type: 'text',
    detail_text: '',
    detail_text_type: 'html',
    is_active: true,
    sort: 500,
    active_from: '',
    active_to: '',
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
});

const isEdit = computed(() => Boolean(props.element));

const info = computed(() => schema.value?.iblock);

const properties = computed(() => schema.value?.properties ?? []);

const propertyByCode = computed(() => Object.fromEntries(properties.value.map((property) => [property.code, property])));

const fieldCatalogue = computed(() => schema.value?.form_fields ?? []);

const canEditLayout = computed(() => session.can('iblocks.update'));

const sectionOptions = computed(() => (schema.value?.sections ?? []).map((section) => ({
    value: section.id,
    label: section.indented_name,
})));

const textTypeOptions = [
    { value: 'text', label: 'Обычный текст' },
    { value: 'html', label: 'HTML' },
];

/** A property key on a tab looks like `prop:ARTICLE`. */
function propertyOf(key) {
    return key.startsWith('prop:') ? propertyByCode.value[key.slice(5)] : null;
}

/** Fields the infoblock does not use are dropped rather than rendered empty. */
function isVisible(key) {
    if (key === 'section_id') {
        return Boolean(info.value?.has_sections) && sectionOptions.value.length > 0;
    }

    if (key === 'sections') {
        return sectionOptions.value.length > 0;
    }

    return key.startsWith('prop:') ? Boolean(propertyOf(key)) : true;
}

function visibleFields(tab) {
    return tab.fields.filter(isVisible);
}

/** Validation errors are keyed differently for base fields and properties. */
function errorOf(key) {
    const property = propertyOf(key);

    return property ? form.error(`properties.${property.code}`) : form.error(key);
}

/** Text-ish and multiple editors get the full width of the grid. */
function wide(key) {
    const property = propertyOf(key);

    if (property) {
        return property.is_multiple || ['text', 'html', 'json', 'file', 'image'].includes(property.type);
    }

    return ['preview_text', 'detail_text', 'meta_description', 'sections'].includes(key);
}

const tabList = computed(() => tabs.value.map((tab) => ({
    key: tab.key,
    label: tab.label,
    mark: visibleFields(tab).some((field) => Boolean(errorOf(field))),
})));

const currentTab = computed(() => tabs.value.find((tab) => tab.key === activeTab.value) ?? tabs.value[0] ?? null);

// A failed save may put the error on a tab the operator is not looking at.
watch(() => form.errors.value, () => {
    const failed = tabs.value.find((tab) => visibleFields(tab).some((field) => Boolean(errorOf(field))));

    if (failed) {
        activeTab.value = failed.key;
    }
});

function isFile(property) {
    return property.type === 'file' || property.type === 'image';
}

function blankValue(property) {
    if (isFile(property)) {
        return { stored: [], remove: [], added: [] };
    }

    if (property.is_multiple) {
        return [];
    }

    if (property.type === 'boolean') {
        return property.default_value === '1' || property.default_value === 'true';
    }

    return property.default_value ?? null;
}

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value && !isEdit.value) {
        form.fields.code = slugify(value);
    }
}

/**
 * Property values travel as multipart, because file properties carry real File
 * objects alongside the scalars.
 */
function buildPayload() {
    const body = toFormData({ ...form.fields });

    properties.value.forEach((property) => {
        const value = values.value[property.code];

        if (isFile(property)) {
            (value?.remove ?? []).forEach((id) => body.append(`property_remove[${property.code}][]`, id));
            (value?.added ?? []).forEach((file) => {
                body.append(`property_files[${property.code}]${property.is_multiple ? '[]' : ''}`, file);
            });

            return;
        }

        if (property.is_multiple) {
            const rows = (Array.isArray(value) ? value : []).filter((item) => item !== null && item !== '');

            if (rows.length === 0) {
                body.append(`properties[${property.code}][]`, '');
            }

            rows.forEach((item) => body.append(`properties[${property.code}][]`, item));

            return;
        }

        const scalar = property.type === 'boolean' ? (value ? '1' : '0') : value;

        body.append(`properties[${property.code}]`, scalar ?? '');
    });

    return body;
}

async function save() {
    const data = await form.submit(
        isEdit.value ? 'put' : 'post',
        isEdit.value
            ? `iblocks/${props.iblock}/elements/${props.element}`
            : `iblocks/${props.iblock}/elements`,
        { body: buildPayload(), files: true },
    );

    if (data) {
        emitHook('element.saved', { iblock: props.iblock, element: data.data });
        router.push({ name: 'elements.index', params: { iblock: props.iblock } });
    }
}

function applyLayout(next) {
    tabs.value = next;

    if (!next.some((tab) => tab.key === activeTab.value)) {
        activeTab.value = next[0]?.key ?? null;
    }
}

onMounted(async () => {
    try {
        schema.value = await api.get(`iblocks/${props.iblock}/schema`);

        tabs.value = schema.value.form_tabs ?? [];
        activeTab.value = tabs.value[0]?.key ?? null;

        const blanks = {};
        properties.value.forEach((property) => {
            blanks[property.code] = blankValue(property);
        });

        if (isEdit.value) {
            const data = await api.get(`iblocks/${props.iblock}/elements/${props.element}`);
            const element = data.data;

            form.fill({
                name: element.name,
                code: element.code ?? '',
                section_id: element.section_id,
                sections: element.section_ids ?? [],
                preview_text: element.preview_text ?? '',
                preview_text_type: element.preview_text_type ?? 'text',
                detail_text: element.detail_text ?? '',
                detail_text_type: element.detail_text_type ?? 'html',
                is_active: element.is_active,
                sort: element.sort,
                active_from: element.active_from ? element.active_from.slice(0, 16) : '',
                active_to: element.active_to ? element.active_to.slice(0, 16) : '',
                meta_title: element.meta_title ?? '',
                meta_description: element.meta_description ?? '',
                meta_keywords: element.meta_keywords ?? '',
            });

            properties.value.forEach((property) => {
                const stored = element.properties?.[property.code];

                blanks[property.code] = isFile(property)
                    ? { stored: Array.isArray(stored) ? stored : [], remove: [], added: [] }
                    : (stored ?? blanks[property.code]);
            });

            codeTouched.value = true;
        }

        values.value = blanks;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        ready.value = true;
    }
});
</script>

<template>
    <div>
        <NPageHeader :title="isEdit ? form.fields.name || 'Элемент' : 'Новый элемент'"
                     :back="{ name: 'elements.index', params: { iblock } }"
                     :description="info ? `Инфоблок «${info.name}»` : null"
                     :breadcrumbs="[
                         { label: info?.name ?? '', to: { name: 'elements.index', params: { iblock } } },
                         { label: isEdit ? form.fields.name : 'Новый элемент' },
                     ]" />

        <form v-if="ready" class="space-y-6" @submit.prevent="save">
            <NCard :padding="false">
                <div class="px-5 pt-1">
                    <NTabs v-model="activeTab" :tabs="tabList">
                        <template v-if="canEditLayout" #actions>
                            <button type="button" title="Настроить вкладки и поля"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
                                    @click="layoutOpen = true">
                                Настроить форму
                            </button>
                        </template>
                    </NTabs>
                </div>

                <div v-if="currentTab" class="grid gap-5 p-5 sm:grid-cols-2">
                    <template v-for="key in visibleFields(currentTab)" :key="key">
                        <div :class="wide(key) && 'sm:col-span-2'">
                            <PropertyField v-if="propertyOf(key)" :property="propertyOf(key)"
                                           :options="schema.options?.[propertyOf(key).code] ?? []"
                                           :error="errorOf(key)"
                                           v-model="values[propertyOf(key).code]" />

                            <NField v-else-if="key === 'name'" label="Название" required :error="form.error('name')">
                                <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                                        @update:model-value="onName" />
                            </NField>

                            <NField v-else-if="key === 'code'" label="Символьный код"
                                    hint="Используется в адресе элемента." :error="form.error('code')">
                                <NInput v-model="form.fields.code" class="font-mono"
                                        :invalid="Boolean(form.error('code'))"
                                        @update:model-value="codeTouched = true" />
                            </NField>

                            <NField v-else-if="key === 'section_id'" label="Основной раздел"
                                    :error="form.error('section_id')">
                                <NSelect v-model="form.fields.section_id" :options="sectionOptions"
                                         placeholder="— без раздела —" />
                            </NField>

                            <NField v-else-if="key === 'is_active'" label="Активность">
                                <NToggle v-model="form.fields.is_active" label="Элемент виден на сайте" />
                            </NField>

                            <NField v-else-if="key === 'sort'" label="Сортировка" :error="form.error('sort')">
                                <NInput v-model="form.fields.sort" type="number" min="0" />
                            </NField>

                            <NField v-else-if="key === 'active_from'" label="Начало активности"
                                    :error="form.error('active_from')">
                                <NInput v-model="form.fields.active_from" type="datetime-local" />
                            </NField>

                            <NField v-else-if="key === 'active_to'" label="Окончание активности"
                                    :error="form.error('active_to')">
                                <NInput v-model="form.fields.active_to" type="datetime-local" />
                            </NField>

                            <NField v-else-if="key === 'preview_text'" label="Текст анонса"
                                    :error="form.error('preview_text')">
                                <NHtmlInput v-if="form.fields.preview_text_type === 'html'"
                                            v-model="form.fields.preview_text" rows="12rem" />

                                <textarea v-else v-model="form.fields.preview_text" rows="4"
                                          class="field-input resize-y"></textarea>
                            </NField>

                            <NField v-else-if="key === 'preview_text_type'" label="Формат анонса">
                                <NSelect v-model="form.fields.preview_text_type" :options="textTypeOptions" />
                            </NField>

                            <NField v-else-if="key === 'detail_text'" label="Подробный текст"
                                    :error="form.error('detail_text')">
                                <NHtmlInput v-if="form.fields.detail_text_type === 'html'"
                                            v-model="form.fields.detail_text" rows="24rem" />

                                <textarea v-else v-model="form.fields.detail_text" rows="14"
                                          class="field-input resize-y"></textarea>
                            </NField>

                            <NField v-else-if="key === 'detail_text_type'" label="Формат текста">
                                <NSelect v-model="form.fields.detail_text_type" :options="textTypeOptions" />
                            </NField>

                            <NField v-else-if="key === 'meta_title'" label="Заголовок страницы (title)"
                                    :error="form.error('meta_title')">
                                <NInput v-model="form.fields.meta_title" />
                            </NField>

                            <NField v-else-if="key === 'meta_description'" label="Описание (description)"
                                    :error="form.error('meta_description')">
                                <textarea v-model="form.fields.meta_description" rows="2"
                                          class="field-input resize-y"></textarea>
                            </NField>

                            <NField v-else-if="key === 'meta_keywords'" label="Ключевые слова"
                                    :error="form.error('meta_keywords')">
                                <NInput v-model="form.fields.meta_keywords" />
                            </NField>

                            <NField v-else-if="key === 'sections'" label="Разделы элемента"
                                    hint="Элемент показывается во всех отмеченных разделах."
                                    :error="form.error('sections')">
                                <div class="max-h-72 space-y-2.5 overflow-y-auto rounded-lg border border-[var(--surface-border)] p-3">
                                    <label v-for="section in sectionOptions" :key="section.value"
                                           class="flex cursor-pointer items-start gap-2.5 select-none">
                                        <input type="checkbox" :value="section.value" v-model="form.fields.sections"
                                               class="mt-0.5 size-4 shrink-0 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-2 focus:ring-brand-500/40">
                                        <span class="text-sm text-[var(--text-strong)]">{{ section.label }}</span>
                                    </label>
                                </div>
                            </NField>
                        </div>
                    </template>

                    <p v-if="!visibleFields(currentTab).length"
                       class="text-sm text-[var(--text-muted)] sm:col-span-2">
                        На этой вкладке пока нет полей.
                    </p>
                </div>
            </NCard>

            <div class="flex items-center gap-2">
                <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                <NButton variant="secondary" size="lg"
                         :to="{ name: 'elements.index', params: { iblock } }">Отмена</NButton>
            </div>
        </form>

        <FormLayoutEditor v-if="ready && canEditLayout" v-model="layoutOpen" :iblock="iblock"
                          :tabs="tabs" :fields="fieldCatalogue" @saved="applyLayout" />
    </div>
</template>
