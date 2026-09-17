<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Draggable from 'vuedraggable';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NTabs from '../../components/ui/NTabs.vue';
import NToggle from '../../components/ui/NToggle.vue';
import FormSubmissions from './FormSubmissions.vue';
import { api } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * Форма обратной связи: основное, поля, соглашение, Telegram, защита и записи.
 */
const props = defineProps({ form: { type: [String, Number], default: null } });

const route = useRoute();
const router = useRouter();
const session = useSession();
const ui = useUi();

const ready = ref(false);
const codeTouched = ref(false);
const meta = ref({ mail_templates: [], agreements: [], field_types: [], file_extensions: '', base_placeholders: [], telegram_message: '', captcha_gd: true });
const telegramTesting = ref(false);

const defaultTelegram = () => ({
    enabled: false,
    token: '',
    chat_ids: '',
    thread_id: '',
    message: '',
    send_files: true,
    silent: false,
});

const defaultProtection = () => ({
    captcha: 'none',
    yandex: { client_key: '', server_key: '', webview: false, invisible: false, hide_shield: false },
    google: { site_key: '', secret_key: '', version: 'v2', min_score: 0.5 },
    nexor: { length: 5, chars: 'mixed' },
});

const unread = ref(0);
const tag = ref('');
const activeTab = ref(route.query.tab ?? 'main');

const form = useForm({
    code: '',
    name: '',
    title: '',
    button_text: 'Отправить',
    success_text: 'Спасибо! Мы получили ваше сообщение.',
    mail_template_id: null,
    to: '',
    agreement_id: null,
    agreement_popup: true,
    ajax: true,
    store_submissions: true,
    telegram: defaultTelegram(),
    protection: defaultProtection(),
    is_active: true,
    sort: 500,
    fields: [],
});

const isEdit = computed(() => Boolean(props.form));
const canSave = computed(() => session.can(isEdit.value ? 'forms.update' : 'forms.create'));
const canSubmissions = computed(() => isEdit.value && session.can('forms.submissions.view'));

let uid = 0;
const nextKey = () => `new-${++uid}`;

const hasError = (prefix) => Object.keys(form.errors.value).some((key) => key.startsWith(prefix));

const tabs = computed(() => [
    { key: 'main', label: 'Основное', mark: ['code', 'name', 'title', 'button_text', 'success_text', 'mail_template_id', 'to'].some((key) => form.error(key)) },
    { key: 'fields', label: `Поля (${form.fields.fields.length})`, mark: hasError('fields') },
    { key: 'agreement', label: 'Соглашение', mark: Boolean(form.error('agreement_id')) },
    { key: 'telegram', label: 'Telegram', mark: hasError('telegram') },
    { key: 'protection', label: 'Защита', mark: hasError('protection') },
    ...(canSubmissions.value ? [{ key: 'submissions', label: unread.value ? `Записи · ${unread.value} новых` : 'Записи' }] : []),
]);

watch(activeTab, (tab) => {
    router.replace({ query: { ...route.query, tab: tab === 'main' ? undefined : tab } });
});

// Ошибка валидации на скрытой вкладке — показываем её.
watch(() => form.errors.value, (errors) => {
    const current = tabs.value.find((tab) => tab.key === activeTab.value);

    if (current?.mark || Object.keys(errors).length === 0) {
        return;
    }

    const failed = tabs.value.find((tab) => tab.mark);

    if (failed) {
        activeTab.value = failed.key;
    }
});

const selectedTemplate = computed(() => meta.value.mail_templates
    .find((template) => String(template.value) === String(form.fields.mail_template_id)) ?? null);

const placeholders = computed(() => [
    ...form.fields.fields.filter((field) => field.code).map((field) => field.code.toUpperCase()),
    ...meta.value.base_placeholders,
].map((token) => `#${token}#`));

const typeLabels = computed(() => Object.fromEntries(meta.value.field_types.map((type) => [type.value, type.label])));

const captchas = [
    { key: 'yandex', label: 'Yandex SmartCaptcha', hint: 'Капча Яндекса. Ключи — в консоли Yandex Cloud, раздел SmartCaptcha.' },
    { key: 'google', label: 'Google reCAPTCHA', hint: 'Капча Google. Ключи — в консоли reCAPTCHA (google.com/recaptcha/admin).' },
    { key: 'nexor', label: 'Nexor Captcha', hint: 'Своя капча: код с картинки. Без внешних сервисов, работает и без JavaScript.' },
];

/** Переключатели капч: включить можно только одну. */
function captchaOn(key) {
    return form.fields.protection.captcha === key;
}

function toggleCaptcha(key, value) {
    form.fields.protection.captcha = value ? key : 'none';
}

/** Проверочное сообщение — теми токеном и chat id, что сейчас в форме, даже несохранёнными. */
async function testTelegram() {
    telegramTesting.value = true;

    try {
        const data = await api.post('forms/telegram-test', {
            form_id: isEdit.value ? Number(props.form) : null,
            token: form.fields.telegram.token,
            chat_ids: form.fields.telegram.chat_ids,
            thread_id: form.fields.telegram.thread_id || null,
        });

        ui.notify(data.message);
    } catch (error) {
        ui.notifyError(error);
    } finally {
        telegramTesting.value = false;
    }
}

function fieldError(index, key) {
    return form.error(`fields.${index}.${key}`);
}

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value && !isEdit.value) {
        form.fields.code = slugify(value).replace(/^[0-9-]+/, '');
    }
}

function onFieldLabel(field, value) {
    field.label = value;

    if (!field.codeTouched) {
        field.code = slugify(value).replace(/-/g, '_').replace(/^[0-9_]+/, '').slice(0, 50);
    }
}

const presets = {
    string: { code: 'name', label: 'Имя' },
    phone: { code: 'phone', label: 'Телефон' },
    email: { code: 'email', label: 'E-mail' },
    textarea: { code: 'message', label: 'Сообщение' },
    file: { code: 'file', label: 'Файл' },
};

function addField(type = 'string') {
    const used = new Set(form.fields.fields.map((field) => field.code));
    const preset = presets[type] ?? presets.string;
    let code = preset.code;

    for (let index = 2; used.has(code); index++) {
        code = `${preset.code}_${index}`;
    }

    form.fields.fields.push({
        key: nextKey(),
        id: null,
        code,
        label: preset.label,
        type,
        is_required: type !== 'file',
        placeholder: '',
        settings: {},
        codeTouched: false,
        open: true,
    });
}

async function removeField(index) {
    const field = form.fields.fields[index];

    if (field.id) {
        const confirmed = await ui.confirm({
            title: 'Удалить поле?',
            message: `Поле «${field.label}» пропадёт из формы после сохранения. Уже полученные записи его сохранят.`,
        });

        if (!confirmed) {
            return;
        }
    }

    form.fields.fields.splice(index, 1);
}

function move(index, delta) {
    const list = form.fields.fields;
    const target = index + delta;

    if (target < 0 || target >= list.length) {
        return;
    }

    [list[index], list[target]] = [list[target], list[index]];
}

function toRow(field) {
    return {
        key: `id-${field.id}`,
        id: field.id,
        code: field.code,
        label: field.label,
        type: field.type,
        is_required: field.is_required,
        placeholder: field.placeholder ?? '',
        settings: Array.isArray(field.settings) ? {} : { ...(field.settings ?? {}) },
        codeTouched: true,
        open: false,
    };
}

function fill(data) {
    form.fill({
        code: data.code,
        name: data.name,
        title: data.title ?? '',
        button_text: data.button_text,
        success_text: data.success_text,
        mail_template_id: data.mail_template_id,
        to: data.to ?? '',
        agreement_id: data.agreement_id,
        agreement_popup: data.agreement_popup,
        ajax: data.ajax,
        store_submissions: data.store_submissions,
        telegram: {
            ...defaultTelegram(),
            ...data.telegram,
            thread_id: data.telegram.thread_id ?? '',
        },
        protection: {
            captcha: data.protection.captcha,
            yandex: { ...defaultProtection().yandex, ...data.protection.yandex },
            google: { ...defaultProtection().google, ...data.protection.google },
            nexor: { ...defaultProtection().nexor, ...data.protection.nexor },
        },
        is_active: data.is_active,
        sort: data.sort,
        fields: data.fields.map(toRow),
    });

    unread.value = data.unread_count ?? 0;
    tag.value = data.tag;
}

async function save() {
    const body = {
        ...form.fields,
        telegram: { ...form.fields.telegram, thread_id: form.fields.telegram.thread_id || null },
        fields: form.fields.fields.map((field) => ({
            id: field.id,
            code: field.code,
            label: field.label,
            type: field.type,
            is_required: field.is_required,
            placeholder: field.placeholder || null,
            settings: field.settings,
        })),
    };

    const data = await form.submit(isEdit.value ? 'put' : 'post', isEdit.value ? `forms/${props.form}` : 'forms', { body });

    if (!data) {
        return;
    }

    // Новые поля получили id — без этого повторное сохранение создало бы их заново.
    // Экран после создания не пересоздаётся (тот же компонент), поэтому заполняем здесь.
    const open = new Set(form.fields.fields.filter((field) => field.open).map((field) => field.code));

    fill(data.data);
    form.fields.fields.forEach((field) => { field.open = open.has(field.code); });

    if (!isEdit.value) {
        codeTouched.value = true;
        router.replace({ name: 'forms.edit', params: { form: data.data.id }, query: { tab: activeTab.value === 'main' ? undefined : activeTab.value } });
    }
}

async function copyTag() {
    try {
        await navigator.clipboard.writeText(tag.value);
        ui.notify('Тег скопирован');
    } catch {
        ui.notify(tag.value);
    }
}

onMounted(async () => {
    try {
        const [metaData, formData] = await Promise.all([
            api.get('form-meta'),
            isEdit.value ? api.get(`forms/${props.form}`) : Promise.resolve(null),
        ]);

        meta.value = metaData;

        if (formData) {
            fill(formData.data);
            codeTouched.value = true;
        } else {
            form.fields.telegram.message = metaData.telegram_message;
            addField('string');
            addField('phone');
            form.fields.fields.forEach((field) => { field.open = false; });
        }
    } catch (error) {
        ui.notifyError(error);
    }

    if (!tabs.value.some((tab) => tab.key === activeTab.value)) {
        activeTab.value = 'main';
    }

    ready.value = true;
});
</script>

<template>
    <div v-if="ready">
        <NPageHeader :title="isEdit ? form.fields.name || 'Форма' : 'Новая форма'"
                     :back="{ name: 'forms.index' }"
                     :breadcrumbs="[
                         { label: 'Формы ОС', to: { name: 'forms.index' } },
                         { label: isEdit ? form.fields.name : 'Новая' },
                     ]">
            <template v-if="isEdit" #actions>
                <button type="button" title="Скопировать тег"
                        class="surface inline-flex items-center gap-2 rounded-lg border px-3 py-2 font-mono text-xs text-[var(--text-muted)] transition hover:text-[var(--text-strong)]"
                        @click="copyTag">
                    <NIcon name="code" size="size-4" />
                    {{ tag }}
                </button>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <div class="px-4 pt-2">
                <NTabs v-model="activeTab" :tabs="tabs" />
            </div>

            <FormSubmissions v-if="activeTab === 'submissions'" :form="props.form"
                             @unread="unread = $event" />

            <form v-else class="p-5" @submit.prevent="save">
                <!-- Основное -->
                <div v-show="activeTab === 'main'" class="grid gap-6 lg:grid-cols-3">
                    <div class="grid content-start gap-5 sm:grid-cols-2 lg:col-span-2">
                        <NField label="Название" required hint="Для админки и темы письма" :error="form.error('name')">
                            <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                                    @update:model-value="onName" />
                        </NField>

                        <NField label="Символьный код" required hint="Можно звать форму по нему: form=&quot;callback&quot;"
                                :error="form.error('code')">
                            <NInput v-model="form.fields.code" class="font-mono" :invalid="Boolean(form.error('code'))"
                                    @update:model-value="codeTouched = true" />
                        </NField>

                        <div class="sm:col-span-2">
                            <NField label="Заголовок на сайте" hint="Пусто — без заголовка" :error="form.error('title')">
                                <NInput v-model="form.fields.title" placeholder="Обратный звонок" />
                            </NField>
                        </div>

                        <NField label="Текст кнопки" required :error="form.error('button_text')">
                            <NInput v-model="form.fields.button_text" />
                        </NField>

                        <NField label="Сортировка" :error="form.error('sort')">
                            <NInput v-model="form.fields.sort" type="number" min="0" />
                        </NField>

                        <div class="sm:col-span-2">
                            <NField label="Сообщение после отправки" required :error="form.error('success_text')">
                                <textarea v-model="form.fields.success_text" rows="2" class="field-input resize-y"></textarea>
                            </NField>
                        </div>

                        <div class="border-t border-[var(--surface-border)] pt-5 sm:col-span-2">
                            <h3 class="text-sm font-semibold text-[var(--text-strong)]">Письмо</h3>
                            <p class="mt-1 text-xs text-[var(--text-muted)]">
                                Почтовый шаблон с подстановками из списка справа. Без шаблона уходит стандартное письмо со всеми полями.
                            </p>
                        </div>

                        <NField label="Почтовый шаблон" :error="form.error('mail_template_id')">
                            <NSelect v-model="form.fields.mail_template_id" :options="meta.mail_templates"
                                     placeholder="— стандартное письмо —" />
                        </NField>

                        <NField label="Кому" :error="form.error('to')"
                                :hint="selectedTemplate?.to ? `Берётся из шаблона: ${selectedTemplate.to}` : 'Пусто — e-mail из настроек сайта'">
                            <NInput v-model="form.fields.to" placeholder="manager@site.ru, sales@site.ru"
                                    :disabled="Boolean(selectedTemplate?.to)" />
                        </NField>

                        <div v-if="session.can('mail.update')" class="sm:col-span-2">
                            <router-link :to="{ name: 'mail.templates.create' }" target="_blank"
                                         class="text-xs text-brand-600 hover:underline dark:text-brand-400">
                                + Создать почтовый шаблон
                            </router-link>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="surface space-y-4 rounded-xl border p-4">
                            <NToggle v-model="form.fields.is_active" label="Активна"
                                     hint="Выключенная форма на сайте не выводится" />
                            <NToggle v-model="form.fields.ajax" label="Сделать отправку без перезагрузки"
                                     hint="Форма отправляется через Livewire, страница не перезагружается" />
                            <NToggle v-model="form.fields.store_submissions" label="Сохранять записи"
                                     hint="Копия каждого письма во вкладке «Записи»" />
                        </div>

                        <div class="surface rounded-xl border p-4">
                            <p class="text-sm font-semibold text-[var(--text-strong)]">Подстановки для шаблона</p>
                            <p class="mt-1 text-xs text-[var(--text-muted)]">Поля формы — по своему коду.</p>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <NBadge v-for="token in placeholders" :key="token" color="violet">{{ token }}</NBadge>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Поля -->
                <div v-show="activeTab === 'fields'" class="space-y-4">
                    <p v-if="form.error('fields')" class="text-sm text-red-600">{{ form.error('fields') }}</p>

                    <Draggable :list="form.fields.fields" item-key="key" handle=".field-handle"
                               ghost-class="opacity-40" :animation="150" class="space-y-2">
                        <template #item="{ element: field, index }">
                            <div :class="['surface rounded-xl border', hasError(`fields.${index}.`) && 'border-red-400']">
                                <div class="flex items-center gap-2 px-3 py-2">
                                    <span class="field-handle cursor-grab text-[var(--text-faint)] active:cursor-grabbing"
                                          title="Перетащите, чтобы переставить">
                                        <NIcon name="grip" size="size-4" />
                                    </span>

                                    <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left"
                                            @click="field.open = !field.open">
                                        <span class="truncate text-sm font-medium text-[var(--text-strong)]">
                                            {{ field.label || 'Без названия' }}<span v-if="field.is_required" class="text-red-500"> *</span>
                                        </span>
                                        <code class="font-mono text-xs text-[var(--text-muted)]">#{{ (field.code || '').toUpperCase() }}#</code>
                                        <NBadge color="gray">{{ typeLabels[field.type] ?? field.type }}</NBadge>
                                    </button>

                                    <div class="flex shrink-0 items-center gap-0.5">
                                        <button type="button" title="Выше" :disabled="index === 0"
                                                class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)] disabled:opacity-30"
                                                @click="move(index, -1)">
                                            <NIcon name="chevron-up" size="size-3.5" />
                                        </button>
                                        <button type="button" title="Ниже" :disabled="index === form.fields.fields.length - 1"
                                                class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)] disabled:opacity-30"
                                                @click="move(index, 1)">
                                            <NIcon name="chevron-down" size="size-3.5" />
                                        </button>
                                        <button type="button" :title="field.open ? 'Свернуть' : 'Настроить'"
                                                class="rounded p-1 text-[var(--text-muted)] transition hover:text-[var(--text-strong)]"
                                                @click="field.open = !field.open">
                                            <NIcon name="pencil" size="size-4" />
                                        </button>
                                        <button type="button" title="Удалить поле"
                                                class="rounded p-1 text-[var(--text-muted)] transition hover:text-red-600"
                                                @click="removeField(index)">
                                            <NIcon name="trash" size="size-4" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="field.open || hasError(`fields.${index}.`)"
                                     class="grid gap-4 border-t border-[var(--surface-border)] p-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <NField label="Название" required :error="fieldError(index, 'label')">
                                        <NInput :model-value="field.label" @update:model-value="onFieldLabel(field, $event)" />
                                    </NField>

                                    <NField label="Код" required hint="Подстановка #КОД# в письме" :error="fieldError(index, 'code')">
                                        <NInput v-model="field.code" class="font-mono"
                                                @update:model-value="field.codeTouched = true" />
                                    </NField>

                                    <NField label="Тип" required :error="fieldError(index, 'type')">
                                        <NSelect v-model="field.type" :options="meta.field_types" />
                                    </NField>

                                    <NField v-if="field.type !== 'file'" label="Подсказка в поле" :error="fieldError(index, 'placeholder')">
                                        <NInput v-model="field.placeholder" />
                                    </NField>

                                    <div class="flex items-end pb-2 sm:col-span-2 lg:col-span-4">
                                        <NToggle v-model="field.is_required" label="Обязательное" />
                                    </div>

                                    <template v-if="field.type === 'file'">
                                        <div class="sm:col-span-2">
                                            <NField label="Расширения" :error="fieldError(index, 'settings.extensions')"
                                                    hint="Через запятую. html, svg, php и исполняемые файлы не принимаются никогда">
                                                <NInput v-model="field.settings.extensions" class="font-mono"
                                                        :placeholder="meta.file_extensions" />
                                            </NField>
                                        </div>

                                        <NField label="Максимальный размер" :error="fieldError(index, 'settings.max_kb')">
                                            <NInput v-model="field.settings.max_kb" type="number" min="1" max="51200"
                                                    placeholder="10240" suffix="КБ" />
                                        </NField>
                                    </template>

                                    <NField v-if="field.type === 'textarea'" label="Высота, строк"
                                            :error="fieldError(index, 'settings.rows')">
                                        <NInput v-model="field.settings.rows" type="number" min="2" max="30" placeholder="4" />
                                    </NField>
                                </div>
                            </div>
                        </template>
                    </Draggable>

                    <p v-if="form.fields.fields.length === 0" class="rounded-xl border border-dashed border-[var(--surface-border)] p-6 text-center text-sm text-[var(--text-muted)]">
                        Полей пока нет — добавьте первое.
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm text-[var(--text-muted)]">Добавить поле:</span>
                        <NButton v-for="type in meta.field_types" :key="type.value" variant="secondary" size="sm"
                                 icon="plus" @click="addField(type.value)">
                            {{ type.label }}
                        </NButton>
                    </div>
                </div>

                <!-- Соглашение -->
                <div v-show="activeTab === 'agreement'" class="grid max-w-2xl gap-5">
                    <NField label="Соглашение" :error="form.error('agreement_id')"
                            hint="Под формой появится галочка — без неё форма не отправится">
                        <NSelect v-model="form.fields.agreement_id" :options="meta.agreements" placeholder="— без соглашения —" />
                    </NField>

                    <NToggle v-model="form.fields.agreement_popup" :disabled="!form.fields.agreement_id"
                             label="Показывать попап при клике на соглашение"
                             hint="Выключено — ссылка откроет страницу соглашения в новой вкладке" />

                    <div class="flex flex-wrap gap-3 text-xs">
                        <router-link v-if="session.can('agreements.view')" :to="{ name: 'agreements.index' }"
                                     class="text-brand-600 hover:underline dark:text-brand-400">Все соглашения</router-link>
                        <router-link v-if="form.fields.agreement_id && session.can('agreements.update')"
                                     :to="{ name: 'agreements.edit', params: { agreement: form.fields.agreement_id } }"
                                     class="text-brand-600 hover:underline dark:text-brand-400">Изменить выбранное</router-link>
                    </div>
                </div>

                <!-- Telegram -->
                <div v-show="activeTab === 'telegram'" class="grid max-w-3xl gap-5">
                    <NToggle v-model="form.fields.telegram.enabled" label="Отправлять заявки в Telegram"
                             hint="Каждая заявка приходит сообщением от вашего бота — вместе с письмом, а не вместо него." />

                    <template v-if="form.fields.telegram.enabled">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <NField label="Токен бота" required :error="form.error('telegram.token')"
                                    hint="Выдаёт @BotFather. Хранится зашифрованным; сохранённый показан точками — оставьте их, чтобы не менять.">
                                <NInput v-model="form.fields.telegram.token" type="password" autocomplete="new-password"
                                        class="font-mono" placeholder="123456789:AA…"
                                        :invalid="Boolean(form.error('telegram.token'))" />
                            </NField>

                            <NField label="Chat ID" required :error="form.error('telegram.chat_ids')"
                                    hint="Ваш id (узнать у @userinfobot), id группы с минусом или @имя_канала. Несколько — через запятую.">
                                <NInput v-model="form.fields.telegram.chat_ids" class="font-mono" placeholder="123456789, -1001234567890"
                                        :invalid="Boolean(form.error('telegram.chat_ids'))" />
                            </NField>

                            <NField label="ID темы" :error="form.error('telegram.thread_id')"
                                    hint="Только для группы с темами: заявки придут в эту тему.">
                                <NInput v-model="form.fields.telegram.thread_id" type="number" min="1" placeholder="—" />
                            </NField>

                            <div class="flex flex-col justify-end gap-3 pb-1">
                                <NToggle v-model="form.fields.telegram.send_files" label="Прикладывать файлы"
                                         hint="Файлы из полей формы — отдельными сообщениями" />
                                <NToggle v-model="form.fields.telegram.silent" label="Без звука" />
                            </div>
                        </div>

                        <NField label="Текст сообщения" :error="form.error('telegram.message')"
                                hint="Обычный текст. Подстановки — те же, что у письма: поля формы по коду и служебные.">
                            <textarea v-model="form.fields.telegram.message" rows="7" class="field-input resize-y font-mono text-sm"></textarea>
                        </NField>

                        <div class="flex flex-wrap gap-1.5">
                            <NBadge v-for="token in placeholders" :key="token" color="violet">{{ token }}</NBadge>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <NButton v-if="canSave" variant="secondary" size="sm" :loading="telegramTesting" @click="testTelegram">
                                Отправить проверочное сообщение
                            </NButton>
                            <NButton v-if="form.fields.telegram.message !== meta.telegram_message" variant="secondary" size="sm"
                                     @click="form.fields.telegram.message = meta.telegram_message">
                                Вернуть текст по умолчанию
                            </NButton>
                        </div>

                        <p class="text-xs text-[var(--text-muted)]">
                            Сначала напишите боту /start (или добавьте его в группу) — иначе Telegram не даст ему писать.
                            Если Telegram недоступен, заявка всё равно сохранится и уйдёт на почту, а ошибка попадёт в лог.
                        </p>
                    </template>
                </div>

                <!-- Защита -->
                <div v-show="activeTab === 'protection'" class="grid max-w-3xl gap-4">
                    <p class="text-sm text-[var(--text-muted)]">
                        Всегда включены: скрытое поле-ловушка для ботов и не больше 10 отправок в минуту с одного адреса.
                        Дополнительно можно включить одну капчу.
                    </p>

                    <p v-if="form.error('protection.captcha')" class="text-sm text-red-600">{{ form.error('protection.captcha') }}</p>

                    <div v-for="captcha in captchas" :key="captcha.key"
                         :class="['surface rounded-xl border p-4', captchaOn(captcha.key) && 'border-brand-500']">
                        <NToggle :model-value="captchaOn(captcha.key)" :label="captcha.label" :hint="captcha.hint"
                                 @update:model-value="toggleCaptcha(captcha.key, $event)" />

                        <!-- Yandex SmartCaptcha -->
                        <div v-if="captcha.key === 'yandex' && captchaOn('yandex')"
                             class="mt-4 grid gap-4 border-t border-[var(--surface-border)] pt-4 sm:grid-cols-2">
                            <NField label="Ключ клиента" required :error="form.error('protection.yandex.client_key')">
                                <NInput v-model="form.fields.protection.yandex.client_key" class="font-mono"
                                        :invalid="Boolean(form.error('protection.yandex.client_key'))" />
                            </NField>

                            <NField label="Ключ сервера" required :error="form.error('protection.yandex.server_key')"
                                    hint="Хранится зашифрованным, в браузер не уходит.">
                                <NInput v-model="form.fields.protection.yandex.server_key" type="password" autocomplete="new-password"
                                        class="font-mono" :invalid="Boolean(form.error('protection.yandex.server_key'))" />
                            </NField>

                            <div class="grid gap-3 sm:col-span-2">
                                <NToggle v-model="form.fields.protection.yandex.webview" label="Запустить в WebView"
                                         hint="Для сайта, открытого внутри мобильного приложения." />
                                <NToggle v-model="form.fields.protection.yandex.invisible" label="Невидимая капча"
                                         hint="Без блока «Я не робот»: проверка запускается при отправке, задание видят только подозрительные посетители." />
                                <NToggle v-model="form.fields.protection.yandex.hide_shield"
                                         :disabled="!form.fields.protection.yandex.invisible"
                                         label="Скрыть блок с уведомлением об обработке данных"
                                         hint="Только для невидимой капчи. По правилам Яндекса уведомление тогда нужно разместить на странице самим." />
                            </div>
                        </div>

                        <!-- Google reCAPTCHA -->
                        <div v-if="captcha.key === 'google' && captchaOn('google')"
                             class="mt-4 grid gap-4 border-t border-[var(--surface-border)] pt-4 sm:grid-cols-2">
                            <NField label="Ключ сайта" required :error="form.error('protection.google.site_key')">
                                <NInput v-model="form.fields.protection.google.site_key" class="font-mono"
                                        :invalid="Boolean(form.error('protection.google.site_key'))" />
                            </NField>

                            <NField label="Секретный ключ" required :error="form.error('protection.google.secret_key')"
                                    hint="Хранится зашифрованным, в браузер не уходит.">
                                <NInput v-model="form.fields.protection.google.secret_key" type="password" autocomplete="new-password"
                                        class="font-mono" :invalid="Boolean(form.error('protection.google.secret_key'))" />
                            </NField>

                            <NField label="Версия" hint="Ключи v2 и v3 разные — выберите ту, под которую их создавали.">
                                <NSelect v-model="form.fields.protection.google.version"
                                         :options="[{ value: 'v2', label: 'v2 — галочка «Я не робот»' }, { value: 'v3', label: 'v3 — невидимая, по баллу' }]" />
                            </NField>

                            <NField v-if="form.fields.protection.google.version === 'v3'" label="Минимальный балл"
                                    :error="form.error('protection.google.min_score')"
                                    hint="От 0 до 1: ниже — отправка отклоняется. Google советует 0.5.">
                                <NInput v-model="form.fields.protection.google.min_score" type="number" min="0" max="1" step="0.1" />
                            </NField>
                        </div>

                        <!-- Nexor Captcha -->
                        <div v-if="captcha.key === 'nexor' && captchaOn('nexor')"
                             class="mt-4 grid gap-4 border-t border-[var(--surface-border)] pt-4 sm:grid-cols-2">
                            <NField label="Длина кода" :error="form.error('protection.nexor.length')">
                                <NInput v-model="form.fields.protection.nexor.length" type="number" min="4" max="8" />
                            </NField>

                            <NField label="Символы" :error="form.error('protection.nexor.chars')">
                                <NSelect v-model="form.fields.protection.nexor.chars"
                                         :options="[{ value: 'mixed', label: 'Буквы и цифры' }, { value: 'digits', label: 'Только цифры' }]" />
                            </NField>

                            <p class="text-xs text-[var(--text-muted)] sm:col-span-2">
                                Код одноразовый и живёт 10 минут; регистр не важен, похожие символы (0, O и Q, 1 и I) не используются.
                                <span v-if="!meta.captcha_gd" class="text-amber-600">
                                    На сервере нет PHP-расширения GD — картинка будет упрощённой (SVG), её легче распознать ботам.
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-2 border-t border-[var(--surface-border)] pt-5">
                    <NButton v-if="canSave" type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                    <NButton variant="secondary" size="lg" :to="{ name: 'forms.index' }">К списку</NButton>
                </div>
            </form>
        </NCard>
    </div>
</template>
