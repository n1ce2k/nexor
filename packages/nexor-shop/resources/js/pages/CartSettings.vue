<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    api, NBadge, NButton, NCard, NField, NIcon, NInput, NModal, NPageHeader, NSelect, NTabs, NToggle, useForm, useSession, useUi,
} from '../core.js';

/**
 * Страница «Корзина»: уровень и вид, валюта с курсами, поля заказа, уведомления.
 */
const session = useSession();
const ui = useUi();

const ready = ref(false);
const tab = ref('main');

const editions = ref([]);
const displays = ref([]);
const feedbacks = ref([]);
const currencies = ref([]);
const effectiveEdition = ref('basic');
const urls = ref({});

const form = useForm({
    edition: 'basic',
    display: 'offcanvas',
    open_on_add: true,
    added_feedback: 'button',
    currency: 'RUB',
    rates: {},
    notify_admin: true,
    admin_email: '',
    notify_customer: true,
    telegram_enabled: false,
    telegram_token: '',
    telegram_chat_id: '',
});

const canUpdate = computed(() => session.can('shop.cart.update'));

const telegramTesting = ref(false);

/** Проверочное сообщение — теми токеном и chat id, что сейчас в форме, даже несохранёнными. */
async function testTelegram() {
    telegramTesting.value = true;

    try {
        const data = await api.post('shop/settings/telegram-test', {
            telegram_token: form.fields.telegram_token,
            telegram_chat_id: form.fields.telegram_chat_id,
        });

        ui.notify(data.message);
    } catch (error) {
        ui.notifyError(error);
    } finally {
        telegramTesting.value = false;
    }
}

const tabs = computed(() => [
    { key: 'main', label: 'Основное', mark: Boolean(form.error('edition') || form.error('display')) },
    { key: 'currency', label: 'Валюта', mark: Object.keys(form.errors.value).some((key) => key.startsWith('rates') || key === 'currency') },
    { key: 'fields', label: 'Поля заказа' },
    { key: 'notify', label: 'Уведомления', mark: Boolean(form.error('admin_email') || form.error('telegram_token') || form.error('telegram_chat_id')) },
]);

const currencyOptions = computed(() => currencies.value.map((item) => ({ value: item.value, label: `${item.label} (${item.symbol})` })));

const shopCurrency = computed(() => currencies.value.find((item) => item.value === form.fields.currency));

/** Курсы нужны ко всем валютам, кроме валюты самого магазина. */
const otherCurrencies = computed(() => currencies.value.filter((item) => item.value !== form.fields.currency));

function apply(data) {
    editions.value = data.editions;
    displays.value = data.displays;
    feedbacks.value = data.feedbacks ?? [];
    currencies.value = data.currencies;
    effectiveEdition.value = data.effective_edition;
    urls.value = data.urls ?? {};

    form.fill({ ...data.settings, rates: { ...(data.settings.rates ?? {}) } });
}

async function load() {
    try {
        apply(await api.get('shop/settings'));
    } catch (error) {
        ui.notifyError(error);
    } finally {
        ready.value = true;
    }
}

async function save() {
    const data = await form.submit('put', 'shop/settings');

    if (data) {
        apply(data);
    }
}

/** Сменили валюту магазина — прежние курсы были к другой валюте. */
function onCurrency(value) {
    if (value === form.fields.currency) {
        return;
    }

    form.fields.currency = value;
    form.fields.rates = {};
}

// ------------------------------------------------------------- поля заказа

const fields = ref([]);
const fieldTypes = ref([]);
const fieldModal = ref(false);
const editingField = ref(null);

const fieldForm = useForm({ code: '', name: '', type: 'text', is_required: false, is_active: true });

async function loadFields() {
    try {
        const data = await api.get('shop/order-fields');

        fields.value = data.data;
        fieldTypes.value = data.types;
    } catch (error) {
        ui.notifyError(error);
    }
}

function openField(field = null) {
    editingField.value = field;
    fieldForm.reset(field
        ? { code: field.code, name: field.name, type: field.type, is_required: field.is_required, is_active: field.is_active }
        : { code: '', name: '', type: 'text', is_required: false, is_active: true });
    fieldModal.value = true;
}

async function saveField() {
    const data = await fieldForm.submit(
        editingField.value ? 'put' : 'post',
        editingField.value ? `shop/order-fields/${editingField.value.id}` : 'shop/order-fields',
    );

    if (data) {
        fieldModal.value = false;
        loadFields();
    }
}

async function removeField(field) {
    const confirmed = await ui.confirm({
        title: 'Удалить поле?',
        message: `Поле «${field.name}» пропадёт из формы заказа. В уже оформленных заказах его значения останутся.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        ui.notify((await api.delete(`shop/order-fields/${field.id}`)).message);
        loadFields();
    } catch (error) {
        ui.notifyError(error);
    }
}

async function move(index, direction) {
    const target = index + direction;

    if (target < 0 || target >= fields.value.length) {
        return;
    }

    const next = [...fields.value];
    [next[index], next[target]] = [next[target], next[index]];
    fields.value = next;

    try {
        await api.put('shop/order-fields/sort', { ids: next.map((field) => field.id) });
    } catch (error) {
        ui.notifyError(error);
        loadFields();
    }
}

onMounted(() => {
    load();
    loadFields();
});
</script>

<template>
    <div>
        <NPageHeader title="Корзина" description="Перечень параметров для работы с корзиной (Бета)">
<!--            <template #actions>-->
<!--                <NButton v-if="urls.cart" variant="secondary" size="sm" :href="urls.cart" target="_blank">Открыть корзину</NButton>-->
<!--            </template>-->
        </NPageHeader>

        <NCard v-if="ready" :padding="false">
            <div class="px-5 pt-1">
                <NTabs v-model="tab" :tabs="tabs" />
            </div>

            <!-- Основное -->
            <div v-show="tab === 'main'" class="space-y-6 p-5">
                <NField label="Уровень корзины" :error="form.error('edition')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label v-for="edition in editions" :key="edition.value"
                               :class="['relative flex cursor-pointer flex-col gap-1 rounded-xl border p-4 transition',
                                        form.fields.edition === edition.value ? 'border-brand-600 ring-2 ring-brand-500/20' : 'border-[var(--surface-border)]',
                                        !edition.available && 'cursor-not-allowed opacity-60']">
                            <input v-model="form.fields.edition" type="radio" :value="edition.value" class="sr-only"
                                   :disabled="!edition.available || !canUpdate">
                            <span class="flex items-center gap-2 font-semibold text-[var(--text-strong)]">
                                {{ edition.label }}

                                <NBadge v-if="effectiveEdition === edition.value" color="green">работает</NBadge>
                                <NBadge v-if="!edition.available" color="amber">
                                    Standart и выше
                                </NBadge>
                            </span>
                            <span class="text-xs text-[var(--text-muted)]">{{ edition.hint }}</span>
                        </label>
                    </div>
                </NField>

                <NField label="Как открывается корзина" :error="form.error('display')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label v-for="display in displays" :key="display.value"
                               :class="['flex cursor-pointer flex-col gap-1 rounded-xl border p-4 transition',
                                        form.fields.display === display.value ? 'border-brand-600 ring-2 ring-brand-500/20' : 'border-[var(--surface-border)]']">
                            <input v-model="form.fields.display" type="radio" :value="display.value" class="sr-only" :disabled="!canUpdate">
                            <span class="font-semibold text-[var(--text-strong)]">{{ display.label }}</span>
                            <span class="text-xs text-[var(--text-muted)]">{{ display.hint }}</span>
                        </label>
                    </div>
                </NField>

                <NToggle v-if="form.fields.display === 'offcanvas'" v-model="form.fields.open_on_add" :disabled="!canUpdate"
                         label="Открывать корзину при добавлении товара"
                         hint="Выключите, чтобы «В корзину» просто клало товар, не выдвигая панель." />

                <NField v-if="form.fields.display === 'page' || !form.fields.open_on_add"
                        label="Как показать, что товар добавлен" :error="form.error('added_feedback')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label v-for="feedback in feedbacks" :key="feedback.value"
                               :class="['flex cursor-pointer flex-col gap-1 rounded-xl border p-4 transition',
                                        form.fields.added_feedback === feedback.value ? 'border-brand-600 ring-2 ring-brand-500/20' : 'border-[var(--surface-border)]']">
                            <input v-model="form.fields.added_feedback" type="radio" :value="feedback.value" class="sr-only" :disabled="!canUpdate">
                            <span class="font-semibold text-[var(--text-strong)]">{{ feedback.label }}</span>
                            <span class="text-xs text-[var(--text-muted)]">{{ feedback.hint }}</span>
                        </label>
                    </div>
                </NField>

                <div class="rounded-xl bg-[var(--surface-muted)] p-4 text-sm text-[var(--text-muted)]" style="display: none;">
                    <p class="font-medium text-[var(--text-strong)]">Как вывести корзину: </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-[var(--surface-panel)] p-3 font-mono text-xs text-[var(--text-strong)]">&lt;livewire:nexor-shop::cart-button /&gt;
&lt;livewire:nexor-shop::cart-offcanvas /&gt;</pre>
                    <p class="mt-2">Кнопка «В корзину» появляется в карточках и на детальной странице каталога сама.</p>
                </div>
            </div>

            <!-- Валюта -->
            <div v-show="tab === 'currency'" class="space-y-6 p-5">
                <NField label="Валюта магазина" hint="В ней корзина считает суммы и заказы."
                        :error="form.error('currency')" class="sm:max-w-sm">
                    <NSelect :model-value="form.fields.currency" :options="currencyOptions" :disabled="!canUpdate"
                             @update:model-value="onCurrency" />
                </NField>

                <div>
                    <p class="text-sm font-medium text-[var(--text-strong)]">Курсы валют</p>
                    <p class="mt-1 text-xs text-[var(--text-muted)]">
                        Цена товара в другой валюте пересчитывается по этому курсу и помечается знаком ≈.
                        Без курса такой товар купить нельзя — корзина честно скажет, что курс не задан.
                    </p>

                    <div class="mt-4 space-y-3">
                        <div v-for="currency in otherCurrencies" :key="currency.value" class="flex flex-wrap items-center gap-3">
                            <span class="w-24 text-sm text-[var(--text-strong)]">1 {{ currency.value }} =</span>
                            <NInput v-model="form.fields.rates[currency.value]" type="number" min="0" step="0.0001"
                                    class="w-40" placeholder="не задан" :disabled="!canUpdate"
                                    :invalid="Boolean(form.error(`rates.${currency.value}`))" />
                            <span class="text-sm text-[var(--text-muted)]">{{ shopCurrency?.symbol }}</span>
                            <span v-if="form.error(`rates.${currency.value}`)" class="text-xs text-red-600">
                                {{ form.error(`rates.${currency.value}`) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Поля заказа -->
            <div v-show="tab === 'fields'" class="p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-[var(--text-muted)]">Что покупатель заполняет при заказе. Порядок — как в форме.</p>
                    <NButton v-if="canUpdate" size="sm" icon="plus" @click="openField()">Добавить поле</NButton>
                </div>

                <ul class="divide-y divide-[var(--surface-border)] rounded-xl border border-[var(--surface-border)]">
                    <li v-for="(field, index) in fields" :key="field.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <div v-if="canUpdate" class="flex flex-col">
                            <button type="button" class="rounded p-0.5 text-[var(--text-muted)] hover:text-[var(--text-strong)] disabled:opacity-30"
                                    :disabled="index === 0" title="Выше" @click="move(index, -1)">
                                <NIcon name="chevron-up" size="size-4" />
                            </button>
                            <button type="button" class="rounded p-0.5 text-[var(--text-muted)] hover:text-[var(--text-strong)] disabled:opacity-30"
                                    :disabled="index === fields.length - 1" title="Ниже" @click="move(index, 1)">
                                <NIcon name="chevron-down" size="size-4" />
                            </button>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-[var(--text-strong)]">
                                {{ field.name }}
                                <span v-if="field.is_required" class="text-red-500">*</span>
                            </p>
                            <p class="text-xs text-[var(--text-muted)]">
                                <code class="font-mono">{{ field.code }}</code> · {{ field.type_label }}
                            </p>
                        </div>

                        <NBadge v-if="!field.is_active" color="gray">скрыто</NBadge>

                        <div v-if="canUpdate" class="flex items-center gap-0.5">
                            <button type="button" title="Изменить" @click="openField(field)"
                                    class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                <NIcon name="pencil" size="size-4" />
                            </button>
                            <button type="button" title="Удалить" @click="removeField(field)"
                                    class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10">
                                <NIcon name="trash" size="size-4" />
                            </button>
                        </div>
                    </li>

                    <li v-if="!fields.length" class="px-4 py-6 text-center text-sm text-[var(--text-muted)]">
                        Полей нет — заказ придёт без контактов покупателя.
                    </li>
                </ul>
            </div>

            <!-- Уведомления -->
            <div v-show="tab === 'notify'" class="space-y-5 p-5 sm:max-w-xl">
                <NToggle v-model="form.fields.notify_admin" label="Письмо администратору о новом заказе"
                         hint="Шаблон письма — «Почтовые шаблоны», код SHOP_ORDER_ADMIN." :disabled="!canUpdate" />

                <NField label="E-mail администратора" hint="Пусто — берётся e-mail из настроек сайта."
                        :error="form.error('admin_email')">
                    <NInput v-model="form.fields.admin_email" type="email" :disabled="!canUpdate || !form.fields.notify_admin" />
                </NField>

                <NToggle v-model="form.fields.notify_customer" label="Письмо покупателю"
                         hint="Уходит, если в форме заказа заполнено поле с кодом EMAIL. Шаблон — SHOP_ORDER_CUSTOMER."
                         :disabled="!canUpdate" />

                <div class="space-y-5 border-t border-[var(--surface-border)] pt-5">
                    <NToggle v-model="form.fields.telegram_enabled" label="Уведомления в Telegram"
                             hint="Каждый новый заказ приходит сообщением от вашего бота: номер, сумма, покупатель, товары."
                             :disabled="!canUpdate" />

                    <template v-if="form.fields.telegram_enabled">
                        <NField label="Токен бота" :error="form.error('telegram_token')"
                                hint="Выдаёт @BotFather при создании бота. Хранится зашифрованным; сохранённый показан точками — оставьте их, чтобы не менять.">
                            <NInput v-model="form.fields.telegram_token" type="password" autocomplete="off" class="font-mono"
                                    placeholder="123456789:AA…" :disabled="!canUpdate"
                                    :invalid="Boolean(form.error('telegram_token'))" />
                        </NField>

                        <NField label="Chat ID" :error="form.error('telegram_chat_id')"
                                hint="Куда слать: ваш id (узнать у @userinfobot), id группы с минусом или @имя_канала. Сначала напишите боту /start или добавьте его в группу.">
                            <NInput v-model="form.fields.telegram_chat_id" class="font-mono" placeholder="123456789"
                                    :disabled="!canUpdate" :invalid="Boolean(form.error('telegram_chat_id'))" />
                        </NField>

                        <NButton v-if="canUpdate" variant="secondary" size="sm" :loading="telegramTesting" @click="testTelegram">
                            Отправить проверочное сообщение
                        </NButton>
                    </template>
                </div>
            </div>

            <template v-if="canUpdate && tab !== 'fields'" #footer>
                <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
            </template>
        </NCard>

        <NModal v-model="fieldModal" :title="editingField ? 'Поле заказа' : 'Новое поле заказа'">
            <div class="space-y-5">
                <NField label="Название" required :error="fieldForm.error('name')">
                    <NInput v-model="fieldForm.fields.name" :invalid="Boolean(fieldForm.error('name'))" />
                </NField>

                <NField label="Код" required hint="Латиница в верхнем регистре: CITY, COMPANY. Поле EMAIL получает письмо о заказе."
                        :error="fieldForm.error('code')">
                    <NInput v-model="fieldForm.fields.code" class="font-mono uppercase" :invalid="Boolean(fieldForm.error('code'))" />
                </NField>

                <NField label="Тип" :error="fieldForm.error('type')">
                    <NSelect v-model="fieldForm.fields.type" :options="fieldTypes" />
                </NField>

                <NToggle v-model="fieldForm.fields.is_required" label="Обязательное" />
                <NToggle v-model="fieldForm.fields.is_active" label="Показывать в форме" />
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="fieldModal = false">Отмена</NButton>
                <NButton size="sm" :loading="fieldForm.busy.value" @click="saveField">Сохранить</NButton>
            </template>
        </NModal>
    </div>
</template>
