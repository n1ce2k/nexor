<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import {
    api, formatMoney, NBadge, NButton, NCard, NField, NIcon, NInput, NModal, NPageHeader, NSelect, NTabs, NToggle, useForm, useSession, useUi,
} from '../core.js';

/**
 * Способы доставки и оплаты для оформления заказа.
 *
 * У доставки со службой (СДЭК) настроек много, поэтому форма разложена по
 * вкладкам: ключи, отправитель, тарифы, габариты, цена и карта. Способ со
 * своей ценой остаётся одной короткой формой.
 */
const session = useSession();
const ui = useUi();

const canUpdate = computed(() => session.can('shop.checkout.update'));

const deliveries = ref([]);
const payments = ref([]);
const paymentProviders = ref([]);
const deliveryProviders = ref([]);
const symbol = ref('₽');
const checking = ref(false);

const modal = ref(null); // 'delivery' | 'payment'
const editing = ref(null);
const tab = ref('main');

/** Подсказки СДЭК по городу отправления. */
const cities = ref([]);
const citiesBusy = ref(false);

/** Тарифы, которые чаще всего включают. Свой код можно вписать руками. */
const tariffPresets = [
    { code: 136, name: 'Посылка склад-склад', to_door: false },
    { code: 137, name: 'Посылка склад-дверь', to_door: true },
    { code: 138, name: 'Посылка дверь-склад', to_door: false },
    { code: 139, name: 'Посылка дверь-дверь', to_door: true },
    { code: 233, name: 'Экономичная посылка склад-склад', to_door: false },
    { code: 234, name: 'Экономичная посылка склад-дверь', to_door: true },
];

const roundingOptions = [
    { value: 'none', label: 'без округления' },
    { value: 'ruble', label: 'до рубля' },
    { value: 'ten', label: 'до 10' },
    { value: 'hundred', label: 'до 100' },
];

const errorOptions = [
    { value: 'manager', label: 'написать «рассчитает менеджер»' },
    { value: 'block', label: 'не давать выбрать этот способ' },
];

const weightUnits = [
    { value: 'kg', label: 'килограммы' },
    { value: 'g', label: 'граммы' },
];

/** Пустые настройки СДЭК — ими же форма заполняется для нового способа. */
function blankSettings(stored = {}) {
    return {
        account: stored.account ?? '',
        // Сохранённый пароль приходит маской — её и отправим обратно.
        secret: stored.secret ?? '',
        test: stored.test ?? false,
        sender: {
            name: stored.sender?.name ?? '',
            phone: stored.sender?.phone ?? '',
        },
        article: stored.article ?? 'ARTICLE',
        from_city: stored.from_city ?? '',
        from_code: stored.from_code ?? '',
        from_address: stored.from_address ?? '',
        shipment_point: stored.shipment_point ?? '',
        tariffs: (stored.tariffs ?? []).map((row) => ({ ...row })),
        address: {
            own: stored.address?.own ?? true,
            field: stored.address?.field ?? 'ADDRESS',
        },
        weight: {
            property: stored.weight?.property ?? 'WEIGHT',
            unit: stored.weight?.unit ?? 'kg',
            default: stored.weight?.default ?? 1,
        },
        dimensions: {
            length: { property: stored.dimensions?.length?.property ?? 'LENGTH', default: stored.dimensions?.length?.default ?? 20 },
            width: { property: stored.dimensions?.width?.property ?? 'WIDTH', default: stored.dimensions?.width?.default ?? 20 },
            height: { property: stored.dimensions?.height?.property ?? 'HEIGHT', default: stored.dimensions?.height?.default ?? 20 },
        },
        price: {
            markup_percent: stored.price?.markup_percent ?? 0,
            markup_fixed: stored.price?.markup_fixed ?? 0,
            round: stored.price?.round ?? 'none',
            show_period: stored.price?.show_period ?? true,
            on_error: stored.price?.on_error ?? 'manager',
        },
        map: {
            enabled: stored.map?.enabled ?? false,
            yandex_key: stored.map?.yandex_key ?? '',
        },
    };
}

const form = useForm({
    name: '', description: '', price: 0, free_from: '', is_active: true, sort: 500,
    provider: 'none',
    settings: blankSettings(),
    payment: { shop_id: '', secret_key: '', description: 'Заказ №{number}', auto_redirect: false },
});

/** Способ доставки считает цену службой. */
const isCdek = computed(() => modal.value === 'delivery' && form.fields.provider === 'cdek');

/** Поля ЮKassa показываются, только когда способ принимает деньги онлайн. */
const isOnline = computed(() => modal.value === 'payment' && form.fields.provider !== 'none');

const endpoint = computed(() => (modal.value === 'delivery' ? 'shop/delivery-methods' : 'shop/payment-methods'));

/** Широкий попап нужен только доставке со службой: там вкладки и таблицы. */
const modalWidth = computed(() => (isCdek.value ? 'max-w-4xl' : 'max-w-lg'));

const tabs = computed(() => {
    if (!isCdek.value) {
        return [];
    }

    return [
        { key: 'main', label: 'Основное', mark: Boolean(form.error('name')) },
        { key: 'access', label: 'Доступ', mark: Boolean(form.error('settings.account') || form.error('settings.secret')) },
        { key: 'sender', label: 'Отправитель', mark: Boolean(form.error('settings.from_code')) },
        { key: 'tariffs', label: 'Тарифы', mark: Boolean(form.error('settings.tariffs')) },
        { key: 'address', label: 'Адрес', mark: Boolean(form.error('settings.address.field')) },
        { key: 'sizes', label: 'Габариты', mark: false },
        { key: 'price', label: 'Цена', mark: false },
        { key: 'map', label: 'Карта', mark: false },
    ];
});

// Переключили способ на свою цену — вкладок больше нет, возвращаемся к форме.
watch(isCdek, (on) => {
    if (!on) {
        tab.value = 'main';
    }
});

async function load() {
    try {
        const [delivery, payment] = await Promise.all([
            api.get('shop/delivery-methods'),
            api.get('shop/payment-methods'),
        ]);

        deliveries.value = delivery.data;
        deliveryProviders.value = delivery.providers ?? [];
        symbol.value = delivery.currency_symbol;
        payments.value = payment.data;
        paymentProviders.value = payment.providers ?? [];
    } catch (error) {
        ui.notifyError(error);
    }
}

function openEditor(kind, row = null) {
    modal.value = kind;
    editing.value = row;
    tab.value = 'main';
    cities.value = [];

    form.reset({
        name: row?.name ?? '',
        description: row?.description ?? '',
        price: row?.price ?? 0,
        free_from: row?.free_from ?? '',
        is_active: row?.is_active ?? true,
        sort: row?.sort ?? 500,
        provider: row?.provider ?? 'none',
        settings: blankSettings(kind === 'delivery' ? (row?.settings ?? {}) : {}),
        payment: {
            shop_id: row?.settings?.shop_id ?? '',
            secret_key: row?.settings?.secret_key ?? '',
            description: row?.settings?.description ?? 'Заказ №{number}',
            auto_redirect: row?.settings?.auto_redirect ?? false,
        },
    });
}

async function save() {
    const body = { ...form.fields };

    if (modal.value === 'payment') {
        // У оплаты свой набор настроек, и цены доставки ей не нужны.
        body.settings = body.payment;
        delete body.price;
        delete body.free_from;
    } else if (!isCdek.value) {
        body.settings = {};
    }

    delete body.payment;

    const data = await form.submit(
        editing.value ? 'put' : 'post',
        editing.value ? `${endpoint.value}/${editing.value.id}` : endpoint.value,
        { body },
    );

    if (data) {
        modal.value = null;
        load();

        return;
    }

    // Ошибка могла прийти по полю с другой вкладки — открываем её.
    const failed = tabs.value.find((item) => item.mark);

    if (failed) {
        tab.value = failed.key;
    }
}

/** Проверка ключей: запрос к провайдеру теми данными, что сейчас в форме. */
async function check() {
    checking.value = true;

    try {
        const data = modal.value === 'delivery'
            ? await api.post('shop/delivery-methods/check', {
                account: form.fields.settings.account,
                secret: form.fields.settings.secret,
                test: form.fields.settings.test,
                method: editing.value?.id ?? null,
            })
            : await api.post(`shop/payment-methods/${editing.value.id}/check`, {
                shop_id: form.fields.payment.shop_id,
                secret_key: form.fields.payment.secret_key,
            });

        ui.notify(data.message);
    } catch (error) {
        ui.notifyError(error);
    } finally {
        checking.value = false;
    }
}

/** Подсказки по городу отправления — ключами из формы, ещё до сохранения. */
async function suggestCities() {
    if (form.fields.settings.from_city.trim().length < 2) {
        cities.value = [];

        return;
    }

    citiesBusy.value = true;

    try {
        const data = await api.post('shop/delivery-methods/cities', {
            q: form.fields.settings.from_city,
            account: form.fields.settings.account,
            secret: form.fields.settings.secret,
            test: form.fields.settings.test,
            method: editing.value?.id ?? null,
        });

        cities.value = data.data;
    } catch (error) {
        ui.notifyError(error);
        cities.value = [];
    } finally {
        citiesBusy.value = false;
    }
}

function pickCity(city) {
    form.fields.settings.from_city = city.full || city.city;
    form.fields.settings.from_code = city.code;
    cities.value = [];
}

function addTariff(preset = null) {
    form.fields.settings.tariffs.push(preset
        ? { ...preset }
        : { code: '', name: '', to_door: false });
}

function removeTariff(index) {
    form.fields.settings.tariffs.splice(index, 1);
}

async function remove(kind, row) {
    const confirmed = await ui.confirm({
        title: kind === 'delivery' ? 'Удалить способ доставки?' : 'Удалить способ оплаты?',
        message: `«${row.name}» пропадёт из оформления. В оформленных заказах название останется.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const url = kind === 'delivery' ? 'shop/delivery-methods' : 'shop/payment-methods';

        ui.notify((await api.delete(`${url}/${row.id}`)).message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <NPageHeader title="Доставка и оплата" description="Способы, из которых покупатель выбирает при оформлении заказа." />

        <div class="grid gap-6 lg:grid-cols-2">
            <NCard title="Доставка" :padding="false">
                <template v-if="canUpdate" #actions>
                    <NButton size="sm" icon="plus" variant="secondary" @click="openEditor('delivery')">Добавить</NButton>
                </template>

                <ul class="divide-y divide-[var(--surface-border)]">
                    <li v-for="row in deliveries" :key="row.id" class="flex items-start gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-[var(--text-strong)]">{{ row.name }}</p>
                            <p class="text-sm text-[var(--text-muted)]">
                                <template v-if="row.is_calculated">Считает {{ row.provider_label }}</template>
                                <template v-else>
                                    {{ Number(row.price) > 0 ? `${formatMoney(row.price)} ${symbol}` : 'бесплатно' }}
                                </template>
                                <template v-if="row.free_from !== null"> · бесплатно от {{ formatMoney(row.free_from) }} {{ symbol }}</template>
                            </p>
                            <p v-if="row.is_calculated && !row.is_ready" class="mt-0.5 text-xs text-amber-600">
                                не настроено: нужны ключи, город отправления и тариф
                            </p>
                        </div>
                        <NBadge v-if="row.is_calculated && row.is_ready" color="green">расчёт</NBadge>
                        <NBadge v-if="!row.is_active" color="gray">скрыт</NBadge>
                        <div v-if="canUpdate" class="flex gap-0.5">
                            <button type="button" title="Изменить" class="rounded-lg p-2 text-[var(--text-muted)] hover:bg-[var(--surface-muted)]" @click="openEditor('delivery', row)">
                                <NIcon name="pencil" size="size-4" />
                            </button>
                            <button type="button" title="Удалить" class="rounded-lg p-2 text-[var(--text-muted)] hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" @click="remove('delivery', row)">
                                <NIcon name="trash" size="size-4" />
                            </button>
                        </div>
                    </li>
                    <li v-if="!deliveries.length" class="px-5 py-6 text-center text-sm text-[var(--text-muted)]">
                        Способов нет — при оформлении выбор доставки не показывается.
                    </li>
                </ul>
            </NCard>

            <NCard title="Оплата" :padding="false">
                <template v-if="canUpdate" #actions>
                    <NButton size="sm" icon="plus" variant="secondary" @click="openEditor('payment')">Добавить</NButton>
                </template>

                <ul class="divide-y divide-[var(--surface-border)]">
                    <li v-for="row in payments" :key="row.id" class="flex items-start gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-[var(--text-strong)]">{{ row.name }}</p>
                            <p v-if="row.description" class="text-sm text-[var(--text-muted)]">{{ row.description }}</p>
                            <p v-if="row.is_online" class="mt-0.5 text-xs" :class="row.is_ready ? 'text-[var(--text-muted)]' : 'text-amber-600'">
                                {{ row.provider_label }}{{ row.is_ready ? '' : ' · ключи не заданы' }}
                            </p>
                        </div>
                        <NBadge v-if="row.is_online && row.is_ready" color="green">онлайн</NBadge>
                        <NBadge v-if="!row.is_active" color="gray">скрыт</NBadge>
                        <div v-if="canUpdate" class="flex gap-0.5">
                            <button type="button" title="Изменить" class="rounded-lg p-2 text-[var(--text-muted)] hover:bg-[var(--surface-muted)]" @click="openEditor('payment', row)">
                                <NIcon name="pencil" size="size-4" />
                            </button>
                            <button type="button" title="Удалить" class="rounded-lg p-2 text-[var(--text-muted)] hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" @click="remove('payment', row)">
                                <NIcon name="trash" size="size-4" />
                            </button>
                        </div>
                    </li>
                    <li v-if="!payments.length" class="px-5 py-6 text-center text-sm text-[var(--text-muted)]">
                        Способов нет — при оформлении выбор оплаты не показывается.
                    </li>
                </ul>
            </NCard>
        </div>

        <NModal :model-value="modal !== null" :max-width="modalWidth"
                :title="modal === 'delivery' ? 'Способ доставки' : 'Способ оплаты'"
                @update:model-value="modal = null">
            <NTabs v-if="tabs.length" v-model="tab" :tabs="tabs" class="mb-5" />

            <div class="space-y-5">
                <template v-if="tab === 'main'">
                    <NField label="Название" required :error="form.error('name')">
                        <NInput v-model="form.fields.name" :invalid="Boolean(form.error('name'))" />
                    </NField>

                    <NField label="Описание" :error="form.error('description')">
                        <textarea v-model="form.fields.description" rows="2" class="field-input resize-y"></textarea>
                    </NField>

                    <NField v-if="modal === 'delivery'" label="Расчёт стоимости" :error="form.error('provider')"
                            hint="«Своя цена» — сумма из настроек способа. «СДЭК» — цену и срок считает служба по адресу покупателя.">
                        <NSelect v-model="form.fields.provider" :options="deliveryProviders" />
                    </NField>

                    <div v-if="modal === 'delivery'" class="grid gap-5 sm:grid-cols-2">
                        <NField v-if="!isCdek" :label="`Стоимость, ${symbol}`" required :error="form.error('price')">
                            <NInput v-model="form.fields.price" type="number" min="0" step="0.01" />
                        </NField>

                        <NField :label="`Бесплатно от, ${symbol}`"
                                :hint="isCdek ? 'С этой суммы заказа доставка бесплатна, даже если СДЭК посчитал цену.' : 'Пусто — всегда платно.'"
                                :error="form.error('free_from')">
                            <NInput v-model="form.fields.free_from" type="number" min="0" step="0.01" />
                        </NField>
                    </div>

                    <template v-if="modal === 'payment'">
                        <NField label="Приём оплаты" :error="form.error('provider')"
                                hint="«Без приёма онлайн» — способ только подписывается в заказе: наличными курьеру, счёт юрлицу.">
                            <NSelect v-model="form.fields.provider" :options="paymentProviders" />
                        </NField>

                        <template v-if="isOnline">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <NField label="shopId" required :error="form.error('settings.shop_id')"
                                        hint="Идентификатор магазина из личного кабинета ЮKassa.">
                                    <NInput v-model="form.fields.payment.shop_id" class="font-mono" placeholder="123456"
                                            :invalid="Boolean(form.error('settings.shop_id'))" />
                                </NField>

                                <NField label="Секретный ключ" :error="form.error('settings.secret_key')"
                                        hint="Хранится зашифрованным; сохранённый показан точками — оставьте их, чтобы не менять.">
                                    <NInput v-model="form.fields.payment.secret_key" type="password" autocomplete="off"
                                            class="font-mono" placeholder="live_… или test_…"
                                            :invalid="Boolean(form.error('settings.secret_key'))" />
                                </NField>
                            </div>

                            <NField label="Назначение платежа" :error="form.error('settings.description')"
                                    hint="Его видит покупатель в банке. {number} — номер заказа, {shop} — название сайта.">
                                <NInput v-model="form.fields.payment.description" placeholder="Заказ №{number}" />
                            </NField>

                            <NToggle v-model="form.fields.payment.auto_redirect" label="Сразу открывать страницу оплаты"
                                     hint="Включено — после оформления покупатель сразу уходит на страницу ЮKassa. Выключено — видит «Спасибо» с кнопкой «Оплатить заказ»." />

                            <div v-if="editing" class="flex items-center gap-3">
                                <NButton variant="secondary" size="sm" :loading="checking" @click="check">Проверить ключи</NButton>
                                <span class="text-xs text-[var(--text-muted)]">Запрос к ЮKassa теми ключами, что сейчас в форме.</span>
                            </div>
                        </template>
                    </template>

                    <NField label="Сортировка" :error="form.error('sort')">
                        <NInput v-model="form.fields.sort" type="number" min="0" />
                    </NField>

                    <NToggle v-model="form.fields.is_active" label="Показывать при оформлении" />
                </template>

                <template v-if="isCdek && tab === 'access'">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField label="Аккаунт" required :error="form.error('settings.account')"
                                hint="Личный кабинет СДЭК → Интеграция. Он же client_id.">
                            <NInput v-model="form.fields.settings.account" class="font-mono" autocomplete="off"
                                    :invalid="Boolean(form.error('settings.account'))" />
                        </NField>

                        <NField label="Секретный пароль" :error="form.error('settings.secret')"
                                hint="Хранится зашифрованным; сохранённый показан точками — оставьте их, чтобы не менять.">
                            <NInput v-model="form.fields.settings.secret" type="password" autocomplete="off" class="font-mono"
                                    :invalid="Boolean(form.error('settings.secret'))" />
                        </NField>
                    </div>

                    <NToggle v-model="form.fields.settings.test" label="Тестовый контур СДЭК"
                             hint="Запросы уходят на api.edu.cdek.ru. Ключи там свои — боевые не подойдут." />

                    <div v-if="editing" class="flex items-center gap-3">
                        <NButton variant="secondary" size="sm" :loading="checking" @click="check">Проверить ключи</NButton>
                        <span class="text-xs text-[var(--text-muted)]">Запрос к СДЭК теми ключами, что сейчас в форме.</span>
                    </div>
                    <p v-else class="text-xs text-[var(--text-muted)]">Сохраните способ — после этого ключи можно проверить.</p>
                </template>

                <template v-if="isCdek && tab === 'sender'">
                    <NField label="Город отправления" required :error="form.error('settings.from_code')"
                            hint="Начните набирать название и выберите из подсказки — СДЭК считает по коду города.">
                        <div class="flex gap-2">
                            <NInput v-model="form.fields.settings.from_city" placeholder="Москва"
                                    :invalid="Boolean(form.error('settings.from_code'))" @keyup.enter="suggestCities" />
                            <NButton variant="secondary" size="sm" :loading="citiesBusy" @click="suggestCities">
                                Найти
                            </NButton>
                        </div>

                        <ul v-if="cities.length" class="mt-2 divide-y divide-[var(--surface-border)] rounded-lg border border-[var(--surface-border)]">
                            <li v-for="city in cities" :key="city.code">
                                <button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-[var(--surface-muted)]"
                                        @click="pickCity(city)">
                                    {{ city.full || city.city }}
                                    <span class="text-[var(--text-muted)]">· код {{ city.code }}</span>
                                </button>
                            </li>
                        </ul>
                    </NField>

                    <NField label="Код города СДЭК" required :error="form.error('settings.from_code')"
                            hint="Подставляется выбором из подсказки; можно вписать руками, если код известен.">
                        <NInput v-model="form.fields.settings.from_code" type="number" min="1" class="font-mono sm:max-w-40"
                                :invalid="Boolean(form.error('settings.from_code'))" />
                    </NField>

                    <NField label="Адрес склада" :error="form.error('settings.from_address')"
                            hint="Нужен тарифам «от двери»: откуда курьер СДЭК забирает груз.">
                        <NInput v-model="form.fields.settings.from_address" placeholder="ул. Складская, 4, стр. 2" />
                    </NField>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField label="Имя или компания отправителя" :error="form.error('settings.sender.name')"
                                hint="Уходит в накладную СДЭК вместе с телефоном.">
                            <NInput v-model="form.fields.settings.sender.name" placeholder="ООО «Ромашка»" />
                        </NField>

                        <NField label="Телефон отправителя" :error="form.error('settings.sender.phone')"
                                hint="По нему курьер связывается со складом.">
                            <NInput v-model="form.fields.settings.sender.phone" placeholder="+7 900 000-00-00" />
                        </NField>
                    </div>

                    <NField label="Пункт отправления" :error="form.error('settings.shipment_point')"
                            hint="Код пункта СДЭК, куда вы сами сдаёте отправления, — для тарифов «от склада». Пусто — СДЭК выберет ближайший.">
                        <NInput v-model="form.fields.settings.shipment_point" class="font-mono" placeholder="MSK123" />
                    </NField>
                </template>

                <template v-if="isCdek && tab === 'tariffs'">
                    <div>
                        <p class="text-sm text-[var(--text-muted)]">
                            Из этих тарифов покупатель выбирает при оформлении. Коды сверьте с договором: набор тарифов зависит от него,
                            а для тяжёлых грузов нужны грузовые тарифы.
                        </p>
                        <p v-if="form.error('settings.tariffs')" class="mt-2 text-sm text-red-600">{{ form.error('settings.tariffs') }}</p>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-[var(--surface-border)]">
                        <table class="w-full text-sm">
                            <thead class="bg-[var(--surface-muted)] text-left text-xs text-[var(--text-muted)]">
                                <tr>
                                    <th class="w-28 px-3 py-2 font-medium">Код</th>
                                    <th class="px-3 py-2 font-medium">Название для покупателя</th>
                                    <th class="w-44 px-3 py-2 font-medium">Куда везут</th>
                                    <th class="w-12 px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in form.fields.settings.tariffs" :key="index"
                                    class="border-t border-[var(--surface-border)]">
                                    <td class="px-3 py-2">
                                        <NInput v-model="row.code" type="number" min="1" class="font-mono"
                                                :invalid="Boolean(form.error(`settings.tariffs.${index}.code`))" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <NInput v-model="row.name" placeholder="Посылка склад-склад" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <NToggle v-model="row.to_door" label="до двери" />
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" class="rounded-lg p-2 text-[var(--text-muted)] hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                                title="Убрать тариф" @click="removeTariff(index)">
                                            <NIcon name="trash" size="size-4" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!form.fields.settings.tariffs.length">
                                    <td colspan="4" class="px-3 py-4 text-center text-[var(--text-muted)]">
                                        Тарифов нет — покупателю нечего будет выбрать.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <NButton size="sm" variant="secondary" icon="plus" @click="addTariff()">Добавить тариф</NButton>

                        <span class="text-xs text-[var(--text-muted)]">Частые:</span>
                        <button v-for="preset in tariffPresets" :key="preset.code" type="button"
                                class="rounded-lg border border-[var(--surface-border)] px-2 py-1 text-xs text-[var(--text-muted)] transition hover:text-[var(--text-strong)]"
                                @click="addTariff(preset)">
                            {{ preset.code }} · {{ preset.name }}
                        </button>
                    </div>

                    <p class="text-xs text-[var(--text-muted)]">
                        «До двери» включает адрес покупателя в расчёт и требует его на оформлении. Без галочки покупатель выбирает пункт выдачи.
                    </p>
                </template>

                <template v-if="isCdek && tab === 'address'">
                    <NToggle v-model="form.fields.settings.address.own" label="Спрашивать адрес в блоке доставки"
                             hint="Включено — поле адреса появляется рядом с тарифом «до двери». Выключено — адрес берётся из поля заказа." />

                    <NField label="Код поля заказа с адресом" :error="form.error('settings.address.field')"
                            :hint="`Заведите поле заказа с этим символьным кодом: Магазин → Корзина → Поля заказа. Покупатель заполнит его в блоке «Покупатель», и адрес уйдёт в расчёт СДЭК.`">
                        <NInput v-model="form.fields.settings.address.field" class="font-mono sm:max-w-56"
                                placeholder="ADDRESS" :disabled="form.fields.settings.address.own"
                                :invalid="Boolean(form.error('settings.address.field'))" />
                    </NField>

                    <p class="text-xs text-[var(--text-muted)]">
                        Адрес нужен только тарифам «до двери»: по нему СДЭК считает цену. Для пунктов выдачи он не запрашивается.
                    </p>
                </template>

                <template v-if="isCdek && tab === 'sizes'">
                    <p class="text-sm text-[var(--text-muted)]">
                        Вес и габариты СДЭК берёт из свойств товара по этим кодам. Заведите свойства с такими символьными кодами
                        в инфоблоке каталога (тип «число»); там, где свойство не заполнено, идёт значение по умолчанию.
                    </p>

                    <NField label="Свойство с артикулом" :error="form.error('settings.article')"
                            hint="Артикул позиции в накладной СДЭК. Не заполнено — берётся символьный код элемента.">
                        <NInput v-model="form.fields.settings.article" class="font-mono sm:max-w-56" placeholder="ARTICLE" />
                    </NField>

                    <div class="grid gap-5 sm:grid-cols-3">
                        <NField label="Свойство веса" :error="form.error('settings.weight.property')">
                            <NInput v-model="form.fields.settings.weight.property" class="font-mono" placeholder="WEIGHT" />
                        </NField>

                        <NField label="Единица веса">
                            <NSelect v-model="form.fields.settings.weight.unit" :options="weightUnits" />
                        </NField>

                        <NField label="Вес по умолчанию" :error="form.error('settings.weight.default')">
                            <NInput v-model="form.fields.settings.weight.default" type="number" min="0" step="0.001" />
                        </NField>
                    </div>

                    <div class="space-y-4">
                        <div v-for="(label, key) in { length: 'Длина', width: 'Ширина', height: 'Высота' }" :key="key"
                             class="grid gap-5 sm:grid-cols-2">
                            <NField :label="`Свойство «${label}»`" :error="form.error(`settings.dimensions.${key}.property`)">
                                <NInput v-model="form.fields.settings.dimensions[key].property" class="font-mono" />
                            </NField>

                            <NField :label="`${label} по умолчанию, см`" :error="form.error(`settings.dimensions.${key}.default`)">
                                <NInput v-model="form.fields.settings.dimensions[key].default" type="number" min="0" step="1" />
                            </NField>
                        </div>
                    </div>

                    <p class="text-xs text-[var(--text-muted)]">
                        Габариты — в сантиметрах, одно место на строку корзины: вес умножается на количество, размеры берутся как у единицы.
                    </p>
                </template>

                <template v-if="isCdek && tab === 'price'">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField label="Наценка, %" :error="form.error('settings.price.markup_percent')"
                                hint="Прибавляется к цене СДЭК: упаковка, страховка, работа склада.">
                            <NInput v-model="form.fields.settings.price.markup_percent" type="number" min="0" step="0.1" />
                        </NField>

                        <NField :label="`Наценка, ${symbol}`" :error="form.error('settings.price.markup_fixed')"
                                hint="Фиксированная надбавка поверх процента.">
                            <NInput v-model="form.fields.settings.price.markup_fixed" type="number" min="0" step="1" />
                        </NField>
                    </div>

                    <NField label="Округление" hint="Итог округляется вверх — покупатель не увидит 1 237,54 ₽.">
                        <NSelect v-model="form.fields.settings.price.round" :options="roundingOptions" />
                    </NField>

                    <NToggle v-model="form.fields.settings.price.show_period" label="Показывать срок доставки"
                             hint="«3–5 дней» рядом с ценой тарифа." />

                    <NField label="Если СДЭК не посчитал"
                            hint="Служба недоступна, город не обслуживается или габариты не подходят под тариф.">
                        <NSelect v-model="form.fields.settings.price.on_error" :options="errorOptions" />
                    </NField>
                </template>

                <template v-if="isCdek && tab === 'map'">
                    <NToggle v-model="form.fields.settings.map.enabled" label="Выбор пункта выдачи на карте"
                             hint="Выключено — пункты показываются списком с поиском, без внешних скриптов." />

                    <NField label="Ключ Яндекс.Карт" :error="form.error('settings.map.yandex_key')"
                            hint="Нужен карте пунктов выдачи. Ключ виден в браузере, поэтому ограничьте его доменом сайта в кабинете Яндекса.">
                        <NInput v-model="form.fields.settings.map.yandex_key" class="font-mono"
                                :disabled="!form.fields.settings.map.enabled" />
                    </NField>
                </template>
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="modal = null">Отмена</NButton>
                <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
            </template>
        </NModal>
    </div>
</template>
