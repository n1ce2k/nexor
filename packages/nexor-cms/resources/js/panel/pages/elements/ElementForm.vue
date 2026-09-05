<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NToggle from '../../components/ui/NToggle.vue';
import PropertyField from '../../components/fields/PropertyField.vue';
import { api, toFormData } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { emit as emitHook } from '../../registry';
import { useUi } from '../../stores/ui';

/**
 * The element editor is generated from the infoblock's schema: base fields plus
 * one PropertyField per property. Nothing about a particular infoblock is
 * hardcoded here.
 */
const props = defineProps({
    iblock: { type: [String, Number], required: true },
    element: { type: [String, Number], default: null },
});

const router = useRouter();
const ui = useUi();

const schema = ref(null);
const values = ref({});
const codeTouched = ref(false);
const ready = ref(false);

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

const sectionOptions = computed(() => (schema.value?.sections ?? []).map((section) => ({
    value: section.id,
    label: section.indented_name,
})));

/** Text-ish and multiple editors get the full width of the grid. */
function wide(property) {
    return property.is_multiple || ['text', 'html', 'json', 'file', 'image'].includes(property.type);
}

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

onMounted(async () => {
    try {
        schema.value = await api.get(`iblocks/${props.iblock}/schema`);

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

        <form v-if="ready" class="grid gap-6 lg:grid-cols-3" @submit.prevent="save">
            <div class="space-y-6 lg:col-span-2">
                <NCard title="Основное">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <NField label="Название" required :error="form.error('name')">
                                <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                                        @update:model-value="onName" />
                            </NField>
                        </div>

                        <NField label="Символьный код" hint="Используется в URL элемента." :error="form.error('code')">
                            <NInput v-model="form.fields.code" class="font-mono"
                                    :invalid="Boolean(form.error('code'))"
                                    @update:model-value="codeTouched = true" />
                        </NField>

                        <NField v-if="info?.has_sections" label="Основной раздел" :error="form.error('section_id')">
                            <NSelect v-model="form.fields.section_id" :options="sectionOptions"
                                     placeholder="— без раздела —" />
                        </NField>
                    </div>
                </NCard>

                <NCard v-if="properties.length" title="Свойства"
                       :description="`Поля, настроенные для этого инфоблока (${properties.length} шт.)`">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div v-for="property in properties" :key="property.id"
                             :class="wide(property) && 'sm:col-span-2'">
                            <PropertyField :property="property"
                                           :options="schema.options?.[property.code] ?? []"
                                           :error="form.error(`properties.${property.code}`)"
                                           v-model="values[property.code]" />
                        </div>
                    </div>
                </NCard>

                <NCard v-else title="Свойства">
                    <p class="text-sm text-[var(--text-muted)]">
                        У инфоблока пока нет собственных свойств.
                        <router-link :to="{ name: 'properties.create', params: { iblock } }"
                                     class="text-brand-600 hover:underline dark:text-brand-400">
                            Добавить свойство
                        </router-link>.
                    </p>
                </NCard>

                <NCard title="Анонс">
                    <div class="space-y-5">
                        <NField label="Текст анонса" :error="form.error('preview_text')">
                            <textarea v-model="form.fields.preview_text" rows="4" class="field-input resize-y"></textarea>
                        </NField>

                        <NField label="Формат анонса">
                            <NSelect v-model="form.fields.preview_text_type"
                                     :options="[{ value: 'text', label: 'Обычный текст' }, { value: 'html', label: 'HTML' }]" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="Подробное описание">
                    <div class="space-y-5">
                        <NField label="Текст" :error="form.error('detail_text')">
                            <textarea v-model="form.fields.detail_text" rows="12"
                                      class="field-input resize-y font-mono text-xs"></textarea>
                        </NField>

                        <NField label="Формат текста">
                            <NSelect v-model="form.fields.detail_text_type"
                                     :options="[{ value: 'html', label: 'HTML' }, { value: 'text', label: 'Обычный текст' }]" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="SEO">
                    <div class="space-y-5">
                        <NField label="Заголовок страницы (title)" :error="form.error('meta_title')">
                            <NInput v-model="form.fields.meta_title" />
                        </NField>

                        <NField label="Описание (description)" :error="form.error('meta_description')">
                            <textarea v-model="form.fields.meta_description" rows="2" class="field-input resize-y"></textarea>
                        </NField>

                        <NField label="Ключевые слова" :error="form.error('meta_keywords')">
                            <NInput v-model="form.fields.meta_keywords" />
                        </NField>
                    </div>
                </NCard>
            </div>

            <div class="space-y-6">
                <NCard title="Публикация">
                    <div class="space-y-5">
                        <NToggle v-model="form.fields.is_active" label="Активен" />

                        <NField label="Начало активности" :error="form.error('active_from')">
                            <NInput v-model="form.fields.active_from" type="datetime-local" />
                        </NField>

                        <NField label="Окончание активности" :error="form.error('active_to')">
                            <NInput v-model="form.fields.active_to" type="datetime-local" />
                        </NField>

                        <NField label="Сортировка" :error="form.error('sort')">
                            <NInput v-model="form.fields.sort" type="number" min="0" />
                        </NField>
                    </div>
                </NCard>

                <NCard v-if="sectionOptions.length" title="Дополнительные разделы"
                       description="Элемент будет показан и в этих разделах тоже.">
                    <div class="max-h-64 space-y-2.5 overflow-y-auto">
                        <label v-for="section in sectionOptions" :key="section.value"
                               class="flex cursor-pointer items-start gap-2.5 select-none">
                            <input type="checkbox" :value="section.value" v-model="form.fields.sections"
                                   class="mt-0.5 size-4 shrink-0 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-2 focus:ring-brand-500/40">
                            <span class="text-sm text-[var(--text-strong)]">{{ section.label }}</span>
                        </label>
                    </div>
                </NCard>

                <div class="flex items-center gap-2">
                    <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                    <NButton variant="secondary" size="lg"
                             :to="{ name: 'elements.index', params: { iblock } }">Отмена</NButton>
                </div>
            </div>
        </form>
    </div>
</template>
