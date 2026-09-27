<script setup>
import { computed, onMounted, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NModal from '../../components/ui/NModal.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NPagination from '../../components/ui/NPagination.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NTabs from '../../components/ui/NTabs.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { useForm } from '../../composables/useForm';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * Раздел «Cookie»: баннер согласия, счётчики и журнал.
 *
 * Счётчик — это код, который попадёт на все страницы сайта, поэтому вкладка с
 * ними закрыта тем же правом, что и настройки, а журнал — отдельным.
 */
const session = useSession();
const ui = useUi();

const ready = ref(false);
const tab = ref('main');

const counters = ref([]);
const categories = ref({});
const placements = ref({});
const consents = ref([]);
const consentsMeta = ref(null);
const consentsCount = ref(0);

const canUpdate = computed(() => session.can('cookies.update'));
const canSeeConsents = computed(() => session.can('cookies.consents.view'));

const form = useForm({
    enabled: false,
    js_mode: false,
    delay: 1000,
    cookie_name: 'nexor_cookies',
    lifetime: 365,
    keep_months: 24,
    policy_url: '',
    cookie_policy_url: '',
    accept_color: '#2563eb',
    accept_text_color: '#ffffff',
    link_color: '#2563eb',
    metrika: '',
    banner_title: '',
    banner_text: '',
    banner_accept: '',
    banner_decline: '',
    banner_settings: '',
    modal_title: '',
    modal_text: '',
    modal_subtitle: '',
    modal_accept_all: '',
    modal_accept_chosen: '',
    modal_decline: '',
    technical_title: '',
    technical_text: '',
    analytics_enabled: true,
    analytics_checked: false,
    analytics_title: '',
    analytics_text: '',
    marketing_enabled: true,
    marketing_checked: false,
    marketing_title: '',
    marketing_text: '',
});

const tabs = computed(() => {
    const list = [
        { key: 'main', label: 'Основное', mark: Boolean(form.error('cookie_name') || form.error('lifetime')) },
        { key: 'texts', label: 'Тексты', mark: Boolean(form.error('banner_title') || form.error('banner_text') || form.error('modal_title')) },
        { key: 'categories', label: 'Категории' },
        { key: 'counters', label: 'Счётчики' },
    ];

    if (canSeeConsents.value) {
        list.push({ key: 'consents', label: 'Согласия' });
    }

    return list;
});

const categoryOptions = computed(() => Object.entries(categories.value).map(([value, label]) => ({ value, label })));
const placementOptions = computed(() => Object.entries(placements.value).map(([value, label]) => ({ value, label })));

function apply(data) {
    form.fill(data.settings);
    counters.value = data.counters ?? [];
    categories.value = data.categories ?? {};
    placements.value = data.placements ?? {};
    consentsCount.value = data.consents_count ?? 0;
}

async function load() {
    try {
        apply(await api.get('cookies'));
    } catch (error) {
        ui.notifyError(error);
    } finally {
        ready.value = true;
    }
}

async function save() {
    const data = await form.submit('put', 'cookies');

    if (data) {
        apply(data);
    }
}

// ------------------------------------------------------------- счётчики

const counterModal = ref(false);
const editingCounter = ref(null);

const counterForm = useForm({ name: '', category: 'analytics', placement: 'head', code: '', is_active: true, sort: 500 });

function openCounter(counter = null) {
    editingCounter.value = counter;
    counterModal.value = true;

    counterForm.reset({
        name: counter?.name ?? '',
        category: counter?.category ?? 'analytics',
        placement: counter?.placement ?? 'head',
        code: counter?.code ?? '',
        is_active: counter?.is_active ?? true,
        sort: counter?.sort ?? 500,
    });
}

async function saveCounter() {
    const data = await counterForm.submit(
        editingCounter.value ? 'put' : 'post',
        editingCounter.value ? `cookies/counters/${editingCounter.value.id}` : 'cookies/counters',
    );

    if (data) {
        counterModal.value = false;
        load();
    }
}

async function removeCounter(counter) {
    const confirmed = await ui.confirm({
        title: 'Удалить счётчик?',
        message: `«${counter.name}» перестанет подключаться на сайте.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        ui.notify((await api.delete(`cookies/counters/${counter.id}`)).message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

// --------------------------------------------------------------- журнал

async function loadConsents(page = 1) {
    try {
        const data = await api.get('cookies/consents', { page });

        consents.value = data.data;
        consentsMeta.value = data.meta;
    } catch (error) {
        ui.notifyError(error);
    }
}

async function prune() {
    const confirmed = await ui.confirm({
        title: 'Почистить журнал?',
        message: `Записи старше ${form.fields.keep_months} мес. будут удалены без возможности вернуть.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        ui.notify((await api.delete('cookies/consents')).message);
        loadConsents();
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

function openTab(key) {
    tab.value = key;

    if (key === 'consents' && consentsMeta.value === null) {
        loadConsents();
    }
}

function date(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '';
}

onMounted(load);
</script>

<template>
    <div v-if="ready" class="space-y-6">
        <NPageHeader title="Cookie"
                     description="Баннер согласия, счётчики и журнал ответов посетителей.">
            <template #actions>
                <NBadge :color="form.fields.enabled ? 'green' : 'gray'">
                    {{ form.fields.enabled ? 'Баннер включён' : 'Баннер выключен' }}
                </NBadge>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <div class="px-5 pt-1">
                <NTabs :model-value="tab" :tabs="tabs" @update:model-value="openTab" />
            </div>

            <!-- Основное -->
            <div v-show="tab === 'main'" class="space-y-5 p-5 sm:max-w-2xl">
                <NToggle v-model="form.fields.enabled" label="Показывать баннер"
                         hint="Выключено — на сайте нет ни баннера, ни счётчиков из этого раздела."
                         :disabled="!canUpdate" />

                <NToggle v-model="form.fields.js_mode" label="Решать в браузере"
                         hint="Нужно, когда страницы отдаются кешем целиком: коды уходят в страницу, а подключает разрешённые скрипт. Иначе решает сервер и неразрешённый код в страницу не попадает вовсе."
                         :disabled="!canUpdate" />

                <div class="grid gap-5 sm:grid-cols-2">
                    <NField label="Задержка показа, мс" :error="form.error('delay')">
                        <NInput v-model="form.fields.delay" type="number" min="0" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Срок согласия, дней" :error="form.error('lifetime')">
                        <NInput v-model="form.fields.lifetime" type="number" min="1" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Имя cookie" hint="Латиница, цифры, дефис и подчёркивание." :error="form.error('cookie_name')">
                        <NInput v-model="form.fields.cookie_name" class="font-mono" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Хранить журнал, мес." hint="0 — не чистить. Команда: nexor:cookies:prune"
                            :error="form.error('keep_months')">
                        <NInput v-model="form.fields.keep_months" type="number" min="0" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Ссылка на политику" :error="form.error('policy_url')">
                        <NInput v-model="form.fields.policy_url" placeholder="/policy" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Ссылка на политику cookie" hint="Пусто — возьмём ссылку на политику."
                            :error="form.error('cookie_policy_url')">
                        <NInput v-model="form.fields.cookie_policy_url" placeholder="/cookies" :disabled="!canUpdate" />
                    </NField>
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <NField label="Цвет кнопки" :error="form.error('accept_color')">
                        <NInput v-model="form.fields.accept_color" type="color" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Цвет текста кнопки" :error="form.error('accept_text_color')">
                        <NInput v-model="form.fields.accept_text_color" type="color" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Цвет ссылок" :error="form.error('link_color')">
                        <NInput v-model="form.fields.link_color" type="color" :disabled="!canUpdate" />
                    </NField>
                </div>

                <p class="text-sm text-[var(--text-muted)]">
                    Баннер выводится компонентом <code class="font-mono">&lt;x-nexor::cookies /&gt;</code> —
                    поставьте его в макете сайта перед <code class="font-mono">&lt;/body&gt;</code>.
                </p>
            </div>

            <!-- Тексты -->
            <div v-show="tab === 'texts'" class="space-y-5 p-5 sm:max-w-2xl">
                <p class="text-sm text-[var(--text-muted)]">
                    Подстановки: <code class="font-mono">#POLICY#</code> — ссылка на политику,
                    <code class="font-mono">#COOKIES#</code> — на политику cookie,
                    <code class="font-mono">#SETTINGS#</code> — ссылка, открывающая окно категорий.
                </p>

                <NField label="Заголовок баннера" required :error="form.error('banner_title')">
                    <NInput v-model="form.fields.banner_title" :disabled="!canUpdate" />
                </NField>

                <NField label="Текст баннера" required :error="form.error('banner_text')">
                    <textarea v-model="form.fields.banner_text" rows="3" class="field-input resize-y" :disabled="!canUpdate"></textarea>
                </NField>

                <div class="grid gap-5 sm:grid-cols-3">
                    <NField label="Кнопка «принять»" required :error="form.error('banner_accept')">
                        <NInput v-model="form.fields.banner_accept" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Кнопка «отклонить»" :error="form.error('banner_decline')">
                        <NInput v-model="form.fields.banner_decline" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Ссылка «настроить»" :error="form.error('banner_settings')">
                        <NInput v-model="form.fields.banner_settings" :disabled="!canUpdate" />
                    </NField>
                </div>

                <div class="space-y-5 border-t border-[var(--surface-border)] pt-5">
                    <NField label="Заголовок окна" required :error="form.error('modal_title')">
                        <NInput v-model="form.fields.modal_title" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Текст окна" :error="form.error('modal_text')">
                        <textarea v-model="form.fields.modal_text" rows="3" class="field-input resize-y" :disabled="!canUpdate"></textarea>
                    </NField>

                    <NField label="Подзаголовок списка категорий" :error="form.error('modal_subtitle')">
                        <NInput v-model="form.fields.modal_subtitle" :disabled="!canUpdate" />
                    </NField>

                    <div class="grid gap-5 sm:grid-cols-3">
                        <NField label="«Принять все»" required :error="form.error('modal_accept_all')">
                            <NInput v-model="form.fields.modal_accept_all" :disabled="!canUpdate" />
                        </NField>

                        <NField label="«Принять выбранные»" required :error="form.error('modal_accept_chosen')">
                            <NInput v-model="form.fields.modal_accept_chosen" :disabled="!canUpdate" />
                        </NField>

                        <NField label="«Отклонить»" required :error="form.error('modal_decline')">
                            <NInput v-model="form.fields.modal_decline" :disabled="!canUpdate" />
                        </NField>
                    </div>
                </div>
            </div>

            <!-- Категории -->
            <div v-show="tab === 'categories'" class="space-y-6 p-5 sm:max-w-2xl">
                <div class="space-y-4">
                    <p class="font-medium text-[var(--text-strong)]">Технические</p>

                    <NField label="Заголовок" required :error="form.error('technical_title')">
                        <NInput v-model="form.fields.technical_title" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Описание" hint="Отключить эту категорию посетитель не может — на ней держится сам сайт."
                            :error="form.error('technical_text')">
                        <textarea v-model="form.fields.technical_text" rows="3" class="field-input resize-y" :disabled="!canUpdate"></textarea>
                    </NField>
                </div>

                <div v-for="code in ['analytics', 'marketing']" :key="code"
                     class="space-y-4 border-t border-[var(--surface-border)] pt-5">
                    <p class="font-medium text-[var(--text-strong)]">{{ categories[code] }}</p>

                    <NToggle v-model="form.fields[`${code}_enabled`]" label="Спрашивать про эту категорию"
                             :disabled="!canUpdate" />

                    <NToggle v-model="form.fields[`${code}_checked`]" label="Галочка отмечена сразу"
                             hint="По умолчанию снята: предотмеченное согласие согласием не считается."
                             :disabled="!canUpdate" />

                    <NField label="Заголовок" required :error="form.error(`${code}_title`)">
                        <NInput v-model="form.fields[`${code}_title`]" :disabled="!canUpdate" />
                    </NField>

                    <NField label="Описание" :error="form.error(`${code}_text`)">
                        <textarea v-model="form.fields[`${code}_text`]" rows="3" class="field-input resize-y" :disabled="!canUpdate"></textarea>
                    </NField>
                </div>
            </div>

            <!-- Счётчики -->
            <div v-show="tab === 'counters'" class="space-y-5 p-5">
                <div class="sm:max-w-2xl">
                    <NField label="Счётчик Яндекс.Метрики"
                            hint="Только номер: код соберётся сам и подключится с согласия на аналитику."
                            :error="form.error('metrika')">
                        <NInput v-model="form.fields.metrika" class="font-mono" placeholder="12345678" :disabled="!canUpdate" />
                    </NField>
                </div>

                <div class="border-t border-[var(--surface-border)] pt-5">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="font-medium text-[var(--text-strong)]">Свои коды</p>

                        <NButton v-if="canUpdate" size="sm" icon="plus" variant="secondary" @click="openCounter()">
                            Добавить
                        </NButton>
                    </div>

                    <ul class="divide-y divide-[var(--surface-border)] rounded-xl border border-[var(--surface-border)]">
                        <li v-for="counter in counters" :key="counter.id" class="flex items-center gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-[var(--text-strong)]">{{ counter.name }}</p>
                                <p class="text-xs text-[var(--text-muted)]">
                                    {{ categories[counter.category] }} · {{ placements[counter.placement] }}
                                </p>
                            </div>

                            <NBadge v-if="!counter.is_active" color="gray">выключен</NBadge>

                            <div v-if="canUpdate" class="flex gap-0.5">
                                <button type="button" title="Изменить"
                                        class="rounded-lg p-2 text-[var(--text-muted)] hover:bg-[var(--surface-muted)]"
                                        @click="openCounter(counter)">
                                    <NIcon name="pencil" size="size-4" />
                                </button>
                                <button type="button" title="Удалить"
                                        class="rounded-lg p-2 text-[var(--text-muted)] hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                        @click="removeCounter(counter)">
                                    <NIcon name="trash" size="size-4" />
                                </button>
                            </div>
                        </li>

                        <li v-if="!counters.length" class="px-4 py-6 text-center text-sm text-[var(--text-muted)]">
                            Кодов нет. Добавьте пиксель или счётчик — он подключится только с согласия.
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Согласия -->
            <div v-show="tab === 'consents'" class="space-y-4 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-[var(--text-muted)]">
                        Всего записей: {{ consentsCount }}. Время ставит сервер, адрес хранится без последней части.
                    </p>

                    <NButton v-if="canUpdate" size="sm" variant="secondary" @click="prune">
                        Удалить старше {{ form.fields.keep_months }} мес.
                    </NButton>
                </div>

                <div class="overflow-x-auto rounded-xl border border-[var(--surface-border)]">
                    <table class="w-full text-sm">
                        <thead class="table-head text-left text-xs uppercase">
                            <tr>
                                <th class="px-4 py-3 font-medium">Когда</th>
                                <th class="px-4 py-3 font-medium">Что разрешено</th>
                                <th class="px-4 py-3 font-medium">Адрес</th>
                                <th class="px-4 py-3 font-medium">Браузер</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="consent in consents" :key="consent.id" class="table-row">
                                <td class="px-4 py-3 whitespace-nowrap">{{ date(consent.created_at) }}</td>
                                <td class="px-4 py-3">{{ consent.summary }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ consent.ip ?? '—' }}</td>
                                <td class="max-w-xs truncate px-4 py-3 text-xs text-[var(--text-muted)]">
                                    {{ consent.user_agent ?? '—' }}
                                </td>
                            </tr>

                            <tr v-if="!consents.length">
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-[var(--text-muted)]">
                                    Записей пока нет.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <NPagination :meta="consentsMeta" @change="loadConsents" />
            </div>

            <template v-if="canUpdate && tab !== 'consents'" #footer>
                <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
            </template>
        </NCard>

        <NModal v-model="counterModal" :title="editingCounter ? 'Счётчик' : 'Новый счётчик'" max-width="max-w-2xl">
            <div class="space-y-5">
                <NField label="Название" required hint="Видно только в панели." :error="counterForm.error('name')">
                    <NInput v-model="counterForm.fields.name" placeholder="VK Пиксель"
                            :invalid="Boolean(counterForm.error('name'))" />
                </NField>

                <div class="grid gap-5 sm:grid-cols-2">
                    <NField label="Категория" hint="Без согласия на неё код не подключится."
                            :error="counterForm.error('category')">
                        <NSelect v-model="counterForm.fields.category" :options="categoryOptions" />
                    </NField>

                    <NField label="Куда вставлять" :error="counterForm.error('placement')">
                        <NSelect v-model="counterForm.fields.placement" :options="placementOptions" />
                    </NField>
                </div>

                <NField label="Код" required hint="Вставляется в страницу как есть, вместе с тегами script."
                        :error="counterForm.error('code')">
                    <textarea v-model="counterForm.fields.code" rows="8"
                              class="field-input resize-y font-mono text-xs"></textarea>
                </NField>

                <div class="grid gap-5 sm:grid-cols-2">
                    <NField label="Сортировка" :error="counterForm.error('sort')">
                        <NInput v-model="counterForm.fields.sort" type="number" min="0" />
                    </NField>

                    <NField label="Активность">
                        <NToggle v-model="counterForm.fields.is_active" label="Подключать на сайте" />
                    </NField>
                </div>
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="counterModal = false">Отмена</NButton>
                <NButton size="sm" :loading="counterForm.busy.value" @click="saveCounter">Сохранить</NButton>
            </template>
        </NModal>
    </div>
</template>
