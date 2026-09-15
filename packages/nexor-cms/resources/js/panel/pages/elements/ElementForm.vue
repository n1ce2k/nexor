<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import FormLayoutEditor from '../../components/FormLayoutEditor.vue';
import FieldPicker from '../../components/catalog/FieldPicker.vue';
import OfferPicker from '../../components/catalog/OfferPicker.vue';
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
import { propertyText, useFieldPrefs } from '../../composables/useFieldPrefs';
import { slugify, useForm } from '../../composables/useForm';
import { emit as emitHook, resolveFormField } from '../../registry';
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

const route = useRoute();
const router = useRouter();
const session = useSession();
const ui = useUi();

const schema = ref(null);
const values = ref({});

/** Значения полей модулей: { 'pagebuilder.content': … }. */
const moduleValues = ref({});
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
    // Торговые данные; уходят на сервер, только если инфоблок — каталог.
    catalog: {
        type: 'simple',
        price: '',
        currency: 'RUB',
        discount_percent: 0,
        quantity: 0,
        measure: 'шт',
        ratio: 1,
        quantity_trace: false,
        can_buy_zero: false,
    },
});

/** Товар, к которому относится это предложение. */
const parentId = ref(null);

const offers = ref([]);
const offersIblock = ref(null);
const offersLoading = ref(false);
const pickerOpen = ref(false);

const offerProperties = ref([]);
const columnsOpen = ref(false);

/** Какие свойства предложений показывать колонками таблицы. */
const offerColumns = useFieldPrefs(() => (offersIblock.value ? `nexor.offer-columns.${offersIblock.value.id}` : null));

const offerColumnFields = computed(() => offerProperties.value.map((property) => ({
    key: property.code,
    label: property.name,
    hint: property.type_label,
})));

function offerColumnLabel(code) {
    return offerProperties.value.find((property) => property.code === code)?.name ?? code;
}

function offerColumnValue(offer, code) {
    return propertyText(offerProperties.value, offer, code);
}

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

/**
 * Как примерно будет выглядеть адрес элемента на сайте — из кода, основного
 * раздела и режима адреса инфоблока. Сервер строит его так же (IblockElement::url).
 */
const urlPreview = computed(() => {
    const iblock = info.value;

    if (!iblock?.has_page) {
        return null;
    }

    const code = form.fields.code || (isEdit.value ? String(props.element) : 'kod-elementa');

    // Элементы инфоблока «Страницы» живут в корне сайта.
    if (iblock.code === 'pages') {
        return `/${code}`;
    }

    const section = (schema.value?.sections ?? []).find((item) => String(item.id) === String(form.fields.section_id));
    const path = iblock.element_url !== 'flat' && section?.url_path ? `${section.url_path}/` : '';

    return `/${iblock.code}/${path}${code}`;
});

const measures = computed(() => schema.value?.measures ?? []);

const measureOptions = computed(() => measures.value.map((measure) => ({ value: measure, label: measure })));

const currencies = computed(() => schema.value?.currencies ?? []);

const currencyOptions = computed(() => currencies.value.map((item) => ({
    value: item.value,
    label: `${item.label} (${item.symbol})`,
})));

/** Знак валюты — приписывается к ценам прямо в форме. */
const currencySymbol = computed(() => currencies.value
    .find((item) => item.value === form.fields.catalog.currency)?.symbol ?? '');

const productTypes = computed(() => (schema.value?.product_types ?? []).map((item) => ({
    value: item.value,
    label: item.label,
})));

const productTypeHint = computed(() => (schema.value?.product_types ?? [])
    .find((item) => item.value === form.fields.catalog.type)?.hint ?? '');

/**
 * У товара с предложениями своей цены и своего остатка нет: их задают
 * предложения, поэтому форма прячет эти поля.
 */
const usesOffers = computed(() => Boolean(info.value?.has_offers) && form.fields.catalog.type === 'with_offers');

const money = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 });

function formatMoney(value) {
    return money.format(Number(value));
}

/** Цена со скидкой считается на лету, пока редактор печатает. */
const finalPrice = computed(() => {
    const price = parseFloat(form.fields.catalog.price);

    if (Number.isNaN(price)) {
        return null;
    }

    const discount = Math.min(Math.max(parseFloat(form.fields.catalog.discount_percent) || 0, 0), 100);

    return Math.round(price * (100 - discount)) / 100;
});

const discountAmount = computed(() => (finalPrice.value === null
    ? 0
    : Math.round((parseFloat(form.fields.catalog.price) - finalPrice.value) * 100) / 100));

/** Предложение после сохранения возвращается к своему товару. */
const backTo = computed(() => (parentId.value && info.value?.product_iblock_id
    ? {
        name: 'elements.edit',
        params: { iblock: info.value.product_iblock_id, element: parentId.value },
        query: { tab: 'offers' },
    }
    : { name: 'elements.index', params: { iblock: props.iblock } }));

async function loadOffers() {
    if (!isEdit.value || !info.value?.has_offers) {
        return;
    }

    offersLoading.value = true;

    try {
        const data = await api.get(`iblocks/${props.iblock}/elements/${props.element}/offers`);

        offers.value = data.data;
        offersIblock.value = data.offers_iblock;
        offerProperties.value = data.properties ?? [];
    } catch (error) {
        ui.notifyError(error);
    } finally {
        offersLoading.value = false;
    }
}

async function removeOffer(offer) {
    const confirmed = await ui.confirm({
        title: 'Удалить предложение?',
        message: `Предложение «${offer.name}» будет удалено.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`iblocks/${offersIblock.value.id}/elements/${offer.id}`);

        ui.notify(data.message);
        loadOffers();
    } catch (error) {
        ui.notifyError(error);
    }
}

const textTypeOptions = [
    { value: 'text', label: 'Обычный текст' },
    { value: 'html', label: 'HTML' },
];

/** A property key on a tab looks like `prop:ARTICLE`. */
function propertyOf(key) {
    return key.startsWith('prop:') ? propertyByCode.value[key.slice(5)] : null;
}

const MODULE_PREFIX = 'module:';

/** Поле модуля на вкладке: `module:pagebuilder.content` → `pagebuilder.content`. */
function moduleFieldOf(key) {
    return key.startsWith(MODULE_PREFIX) ? key.slice(MODULE_PREFIX.length) : null;
}

function moduleFieldLabel(key) {
    return fieldCatalogue.value.find((field) => field.key === key)?.label ?? '';
}

/** Fields the infoblock does not use are dropped rather than rendered empty. */
function isVisible(key) {
    if (key === 'section_id') {
        return Boolean(info.value?.has_sections) && sectionOptions.value.length > 0;
    }

    if (key === 'sections') {
        return sectionOptions.value.length > 0;
    }

    if (key === 'offers') {
        return usesOffers.value;
    }

    if (key === 'catalog.type') {
        return Boolean(info.value?.has_offers);
    }

    if (key.startsWith('catalog.')) {
        return Boolean(info.value?.has_commerce) && !usesOffers.value;
    }

    // Поле модуля видно, только если модуль принёс в панель свой компонент.
    if (moduleFieldOf(key)) {
        return Boolean(resolveFormField(moduleFieldOf(key)));
    }

    return key.startsWith('prop:') ? Boolean(propertyOf(key)) : true;
}

function visibleFields(tab) {
    return tab.fields.filter(isVisible);
}

/** Validation errors are keyed differently for base fields and properties. */
function errorOf(key) {
    const property = propertyOf(key);

    if (moduleFieldOf(key)) {
        return form.error(`modules.${moduleFieldOf(key)}`);
    }

    return property ? form.error(`properties.${property.code}`) : form.error(key);
}

/** Text-ish and multiple editors get the full width of the grid. */
function wide(key) {
    const property = propertyOf(key);

    if (moduleFieldOf(key)) {
        return true;
    }

    if (property) {
        return property.is_multiple || ['text', 'html', 'json', 'file', 'image'].includes(property.type);
    }

    return ['preview_text', 'detail_text', 'meta_description', 'sections', 'offers'].includes(key);
}

// У простого товара вкладки «Предложения» нет вовсе — не пустая, а скрытая.
const tabList = computed(() => tabs.value
    .filter((tab) => tab.key !== 'offers' || usesOffers.value)
    .map((tab) => ({
        key: tab.key,
        label: tab.label,
        mark: visibleFields(tab).some((field) => Boolean(errorOf(field))),
    })));

// Тип товара сменили, стоя на вкладке, которой больше нет.
watch(tabList, (list) => {
    // Пока форма грузится, вкладку выбирает load().
    if (!ready.value) {
        return;
    }

    if (!list.some((tab) => tab.key === activeTab.value)) {
        activeTab.value = list[0]?.key ?? null;
    }
});

const currentTab = computed(() => tabs.value.find((tab) => tab.key === activeTab.value) ?? tabs.value[0] ?? null);

/** Пустая вкладка «Остатки» у товара с предложениями — не ошибка, а следствие. */
const emptyNote = computed(() => (usesOffers.value && ['price', 'stock', 'discount'].includes(currentTab.value?.key)
    ? 'Цена и остаток задаются в торговых предложениях — вкладка «Предложения».'
    : 'На этой вкладке пока нет полей.'));

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
    const fields = { ...form.fields };

    // Не каталогу торговые поля не нужны — и сервер их не примет.
    if (!info.value?.has_commerce) {
        delete fields.catalog;
    } else if (usesOffers.value) {
        // Своя цена товару с предложениями не принадлежит — снимаем её,
        // чтобы на витрине не осталась цена от прошлой жизни товара.
        fields.catalog = {
            type: fields.catalog.type,
            price: '',
            offers_by_properties: fields.catalog.offers_by_properties,
        };
    }

    const body = toFormData(fields);

    if (parentId.value && info.value?.product_iblock_id) {
        body.append('parent_element_id', parentId.value);
    }

    // Поля модулей: объект уходит JSON-строкой — многоуровневые данные вроде
    // блоков конструктора FormData иначе разложил бы с потерей типов.
    Object.entries(moduleValues.value).forEach(([path, value]) => {
        const [module, field] = path.split('.');

        if (value === undefined) {
            return;
        }

        body.append(`modules[${module}][${field}]`, value !== null && typeof value === 'object'
            ? JSON.stringify(value)
            : (value ?? ''));
    });

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

/**
 * @param {boolean} stay Остаться в форме вместо возврата к списку
 */
async function save(stay = false) {
    const data = await form.submit(
        isEdit.value ? 'put' : 'post',
        isEdit.value
            ? `iblocks/${props.iblock}/elements/${props.element}`
            : `iblocks/${props.iblock}/elements`,
        { body: buildPayload(), files: true },
    );

    if (!data) {
        return;
    }

    emitHook('element.saved', { iblock: props.iblock, element: data.data });

    if (!stay) {
        router.push(backTo.value);

        return;
    }

    // Созданный элемент дальше правится, а не создаётся заново — иначе
    // следующее «Сохранить» сделало бы дубль.
    if (!isEdit.value) {
        router.replace({
            name: 'elements.edit',
            params: { iblock: props.iblock, element: data.data.id },
            query: route.query,
        });

        return;
    }

    await loadOffers();
}

function applyLayout(next) {
    tabs.value = next;

    if (!next.some((tab) => tab.key === activeTab.value)) {
        activeTab.value = next[0]?.key ?? null;
    }
}

/** Всё, что осталось от предыдущего элемента, пока форма не пересоздана. */
function resetState() {
    ready.value = false;
    form.reset();
    form.fill({
        sections: [],
        catalog: {
            type: 'simple',
            price: '',
            currency: 'RUB',
            discount_percent: 0,
            quantity: 0,
            measure: 'шт',
            ratio: 1,
            quantity_trace: false,
            can_buy_zero: false,
            offers_by_properties: true,
        },
    });
    codeTouched.value = false;
    moduleValues.value = {};
    parentId.value = null;
    offers.value = [];
    offersIblock.value = null;
    offerProperties.value = [];
    pickerOpen.value = false;
}

async function load() {
    resetState();

    try {
        schema.value = await api.get(`iblocks/${props.iblock}/schema`);

        tabs.value = schema.value.form_tabs ?? [];

        if (route.query.parent) {
            parentId.value = Number(route.query.parent);
        }

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

            if (element.catalog) {
                form.fields.catalog = {
                    type: element.catalog.type ?? 'simple',
                    price: element.catalog.price ?? '',
                    currency: element.catalog.currency ?? 'RUB',
                    discount_percent: Number(element.catalog.discount_percent ?? 0),
                    quantity: Number(element.catalog.quantity ?? 0),
                    measure: element.catalog.measure ?? 'шт',
                    ratio: Number(element.catalog.ratio ?? 1),
                    quantity_trace: Boolean(element.catalog.quantity_trace),
                    can_buy_zero: Boolean(element.catalog.can_buy_zero),
                    offers_by_properties: element.catalog.offers_by_properties ?? true,
                };

                parentId.value = element.catalog.parent_element_id ?? parentId.value;
            }

            properties.value.forEach((property) => {
                const stored = element.properties?.[property.code];

                blanks[property.code] = isFile(property)
                    ? { stored: Array.isArray(stored) ? stored : [], remove: [], added: [] }
                    : (stored ?? blanks[property.code]);
            });

            const modules = {};

            Object.entries(data.modules ?? {}).forEach(([module, fields]) => {
                Object.entries(fields ?? {}).forEach(([field, value]) => {
                    modules[`${module}.${field}`] = value;
                });
            });

            moduleValues.value = modules;
            codeTouched.value = true;
        }

        values.value = blanks;

        // `?tab=offers` — вернуться на ту вкладку, с которой уходили. Только после
        // загрузки элемента: до неё тип товара неизвестен и «Предложения» скрыты.
        activeTab.value = tabList.value.some((tab) => tab.key === route.query.tab)
            ? route.query.tab
            : (tabList.value[0]?.key ?? null);

        await loadOffers();
    } catch (error) {
        ui.notifyError(error);
    } finally {
        ready.value = true;
    }
}

onMounted(load);

// Товар и его предложение открываются одним маршрутом `elements.edit`:
// vue-router не пересоздаёт компонент при смене параметров, и без этого
// адрес менялся, а на экране оставался прежний элемент.
watch(() => [props.iblock, props.element], (next, previous) => {
    if (String(next) !== String(previous)) {
        load();
    }
});
</script>

<template>
    <div>
        <NPageHeader :title="isEdit ? form.fields.name || 'Элемент' : 'Новый элемент'"
                     :back="{ name: 'elements.index', params: { iblock } }"

                     :breadcrumbs="[
                         { label: info?.name ?? '', to: { name: 'elements.index', params: { iblock } } },
                         { label: isEdit ? form.fields.name : 'Новый элемент' },
                     ]" >
            <template v-if="isEdit" #actions>
                <NButton variant="secondary" icon="grip" :to="{ name: 'properties.index', params: { iblock } }">
                    Свойства
                </NButton>

            </template>
        </NPageHeader>

        <form v-if="ready" class="space-y-6" @submit.prevent="save()">
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
                            <component :is="resolveFormField(moduleFieldOf(key))" v-if="moduleFieldOf(key)"
                                       v-model="moduleValues[moduleFieldOf(key)]" :iblock="info"
                                       :element="element" :label="moduleFieldLabel(key)" :error="errorOf(key)" />

                            <PropertyField v-else-if="propertyOf(key)" :property="propertyOf(key)"
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

                                <p v-if="urlPreview" class="mt-1.5 text-xs text-[var(--text-muted)]">
                                    Адрес на сайте:
                                    <code class="font-mono break-all text-[var(--text-base)]">{{ urlPreview }}</code>
                                    <template v-if="info?.element_url === 'flat'"> — раздел в адрес не входит, так настроен инфоблок</template>
                                </p>
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

                            <NField v-else-if="key === 'catalog.type'" label="Тип товара"
                                    :hint="productTypeHint" :error="form.error('catalog.type')">
                                <NSelect v-model="form.fields.catalog.type" :options="productTypes" />
                            </NField>

                            <NField v-else-if="key === 'catalog.price'" label="Цена"
                                    hint="Базовая цена за единицу, без скидки." :error="form.error('catalog.price')">
                                <div class="flex items-center gap-2">
                                    <NInput v-model="form.fields.catalog.price" type="number" min="0" step="0.01"
                                            class="sm:max-w-48" :invalid="Boolean(form.error('catalog.price'))" />
                                    <span class="text-sm text-[var(--text-muted)]">{{ currencySymbol }}</span>
                                </div>
                            </NField>

                            <NField v-else-if="key === 'catalog.currency'" label="Валюта"
                                    hint="Цена показывается в этой валюте; пересчёта между валютами нет."
                                    :error="form.error('catalog.currency')">
                                <NSelect v-model="form.fields.catalog.currency" :options="currencyOptions" />
                            </NField>

                            <NField v-else-if="key === 'catalog.quantity'" label="Доступное количество"
                                    :error="form.error('catalog.quantity')">
                                <NInput v-model="form.fields.catalog.quantity" type="number" min="0" step="any"
                                        :invalid="Boolean(form.error('catalog.quantity'))" />
                            </NField>

                            <NField v-else-if="key === 'catalog.measure'" label="Единица измерения"
                                    :error="form.error('catalog.measure')">
                                <NSelect v-model="form.fields.catalog.measure" :options="measureOptions" />
                            </NField>

                            <NField v-else-if="key === 'catalog.ratio'" label="Коэффициент"
                                    hint="Сколько единиц продаётся за раз: 1 — поштучно, 0.5 — по половине единицы."
                                    :error="form.error('catalog.ratio')">
                                <NInput v-model="form.fields.catalog.ratio" type="number" min="0.001" step="any"
                                        :invalid="Boolean(form.error('catalog.ratio'))" />
                            </NField>

                            <NField v-else-if="key === 'catalog.quantity_trace'" label="Количественный учёт">
                                <NToggle v-model="form.fields.catalog.quantity_trace"
                                         label="Проверять остаток и не продавать больше, чем есть" />
                            </NField>

                            <NField v-else-if="key === 'catalog.can_buy_zero'" label="Покупка при отсутствии">
                                <NToggle v-model="form.fields.catalog.can_buy_zero"
                                         label="Разрешить покупать, когда остаток закончился"
                                         :disabled="!form.fields.catalog.quantity_trace" />
                                <p v-if="!form.fields.catalog.quantity_trace"
                                   class="mt-1.5 text-xs text-[var(--text-muted)]">
                                    Имеет смысл только при включённом количественном учёте: без него остаток не проверяется вовсе.
                                </p>
                            </NField>

                            <NField v-else-if="key === 'catalog.discount_percent'" label="Скидка"
                                    :error="form.error('catalog.discount_percent')">
                                <div class="flex items-center gap-2">
                                    <NInput v-model="form.fields.catalog.discount_percent" type="number"
                                            min="0" max="100" step="0.01" class="sm:max-w-32"
                                            :invalid="Boolean(form.error('catalog.discount_percent'))" />
                                    <span class="text-sm text-[var(--text-muted)]">%</span>
                                </div>

                                <div class="mt-3 rounded-lg bg-[var(--surface-muted)] p-3 text-sm">
                                    <template v-if="finalPrice !== null">
                                        <span class="text-[var(--text-muted)]">Цена со скидкой:</span>
                                        <span class="font-semibold text-[var(--text-strong)]">{{ formatMoney(finalPrice) }} {{ currencySymbol }}</span>
                                        <span v-if="discountAmount > 0" class="text-[var(--text-muted)]">
                                            — дешевле на {{ formatMoney(discountAmount) }} {{ currencySymbol }}
                                        </span>
                                    </template>
                                    <span v-else class="text-[var(--text-muted)]">
                                        Заполните цену на вкладке «Цена» — цена со скидкой посчитается здесь.
                                    </span>
                                </div>
                            </NField>

                            <NField v-else-if="key === 'offers'" label="Торговые предложения"
                                    hint="Варианты товара — размер, цвет, фасовка. У каждого своя цена и остаток.">
                                <p v-if="!isEdit"
                                   class="rounded-lg bg-[var(--surface-muted)] p-3 text-sm text-[var(--text-muted)]">
                                    Сохраните товар — после этого к нему можно будет добавлять предложения.
                                </p>

                                <div v-else class="space-y-3">
                                    <div class="rounded-lg border border-[var(--surface-border)] p-3">
                                        <NToggle v-model="form.fields.catalog.offers_by_properties"
                                                 label="Выводить через свойства" />
                                        <p class="mt-1.5 text-xs text-[var(--text-muted)]">
                                            <template v-if="form.fields.catalog.offers_by_properties">
                                                Варианты переключаются кнопками по свойствам, у каждого предложения свой адрес на сайте.
                                            </template>
                                            <template v-else>
                                                Предложения выводятся списком под товаром, у каждого своя кнопка «В корзину».
                                                Адрес предложения открывает страницу товара.
                                            </template>
                                        </p>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="button" title="Настроить колонки"
                                                class="rounded-lg p-1.5 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
                                                @click="columnsOpen = true">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="3" />
                                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="overflow-x-auto rounded-lg border border-[var(--surface-border)]">
                                        <table class="w-full text-sm">
                                            <thead class="bg-[var(--surface-muted)] text-left text-xs text-[var(--text-muted)]">
                                                <tr>
                                                    <th class="px-3 py-2 font-medium">Название</th>
                                                    <th class="px-3 py-2 font-medium">Цена</th>
                                                    <th class="px-3 py-2 font-medium">Остаток</th>
                                                    <th class="px-3 py-2 font-medium">Статус</th>
                                                    <th v-for="code in offerColumns" :key="code" class="px-3 py-2 font-medium">
                                                        {{ offerColumnLabel(code) }}
                                                    </th>
                                                    <th class="px-3 py-2"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-if="offersLoading">
                                                    <td :colspan="5 + offerColumns.length" class="px-3 py-4 text-center text-[var(--text-muted)]">Загружаем…</td>
                                                </tr>
                                                <tr v-else-if="!offers.length">
                                                    <td :colspan="5 + offerColumns.length" class="px-3 py-4 text-center text-[var(--text-muted)]">Предложений пока нет.</td>
                                                </tr>
                                                <template v-else>
                                                    <tr v-for="offer in offers" :key="offer.id"
                                                        class="border-t border-[var(--surface-border)]">
                                                        <td class="px-3 py-2">
                                                            <router-link :to="{ name: 'elements.edit', params: { iblock: offersIblock.id, element: offer.id }, query: { parent: element } }"
                                                                         class="font-medium text-[var(--text-strong)] hover:text-brand-600 hover:underline">
                                                                {{ offer.name }}
                                                            </router-link>
                                                        </td>
                                                        <td class="px-3 py-2 whitespace-nowrap">
                                                            <template v-if="offer.catalog?.final_price != null">
                                                                {{ formatMoney(offer.catalog.final_price) }} {{ offer.catalog.currency_symbol }}
                                                                <span v-if="Number(offer.catalog.discount_percent) > 0"
                                                                      class="ml-1 text-xs text-[var(--text-faint)] line-through">
                                                                    {{ formatMoney(offer.catalog.price) }} {{ offer.catalog.currency_symbol }}
                                                                </span>
                                                            </template>
                                                            <span v-else class="text-[var(--text-faint)]">—</span>
                                                        </td>
                                                        <td class="px-3 py-2 whitespace-nowrap">
                                                            {{ offer.catalog ? `${Number(offer.catalog.quantity)} ${offer.catalog.measure}` : '—' }}
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <span :class="['rounded-full px-2 py-0.5 text-xs', offer.is_active ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400' : 'bg-[var(--surface-muted)] text-[var(--text-muted)]']">
                                                                {{ offer.is_active ? 'активно' : 'скрыто' }}
                                                            </span>
                                                        </td>
                                                        <td v-for="code in offerColumns" :key="code"
                                                            class="px-3 py-2 text-[var(--text-muted)]">
                                                            {{ offerColumnValue(offer, code) }}
                                                        </td>

                                                        <td class="px-3 py-2 text-right">
                                                            <button v-if="offersIblock?.abilities?.delete" type="button"
                                                                    class="text-xs text-red-600 hover:underline dark:text-red-400"
                                                                    @click="removeOffer(offer)">
                                                                удалить
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <NButton v-if="offersIblock?.abilities?.create || offersIblock?.abilities?.update"
                                                 size="sm" variant="secondary" icon="plus" @click="pickerOpen = true">
                                            Добавить предложение
                                        </NButton>

                                        <NButton v-if="offersIblock?.abilities?.view" size="sm" variant="ghost"
                                                 :to="{ name: 'elements.index', params: { iblock: offersIblock.id } }">
                                            Все предложения
                                        </NButton>
                                    </div>
                                </div>
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
                        {{ emptyNote }}
                    </p>
                </div>
            </NCard>

            <div class="flex flex-wrap items-center gap-2">
                <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>

                <NButton type="button" variant="secondary" size="lg" :loading="form.busy.value"
                         @click="save(true)">
                    Сохранить и продолжить
                </NButton>

                <NButton variant="ghost" size="lg" :to="backTo">Отмена</NButton>
            </div>
        </form>

        <FormLayoutEditor v-if="ready && canEditLayout" v-model="layoutOpen" :iblock="iblock"
                          :tabs="tabs" :fields="fieldCatalogue" @saved="applyLayout" />

        <OfferPicker v-if="isEdit && offersIblock" v-model="pickerOpen" :iblock="iblock" :element="element"
                     :offers-iblock="offersIblock" :measures="measures" :currencies="currencies"
                     :currency="form.fields.catalog.currency" :properties="offerProperties"
                     @saved="loadOffers" />

        <FieldPicker v-model="columnsOpen" v-model:selected="offerColumns" title="Колонки предложений"
                     description="Отмеченные свойства станут колонками таблицы."
                     :fields="offerColumnFields" />
    </div>
</template>
