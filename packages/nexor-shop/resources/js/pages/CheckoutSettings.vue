<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    api, formatMoney, NBadge, NButton, NCard, NField, NIcon, NInput, NModal, NPageHeader, NSelect, NToggle, useForm, useSession, useUi,
} from '../core.js';

/**
 * Способы доставки и оплаты для оформления заказа.
 */
const session = useSession();
const ui = useUi();

const canUpdate = computed(() => session.can('shop.checkout.update'));

const deliveries = ref([]);
const payments = ref([]);
const providers = ref([]);
const symbol = ref('₽');
const checking = ref(false);

const modal = ref(null); // 'delivery' | 'payment'
const editing = ref(null);

const form = useForm({
    name: '', description: '', price: 0, free_from: '', is_active: true, sort: 500,
    provider: 'none',
    settings: { shop_id: '', secret_key: '', description: 'Заказ №{number}', auto_redirect: false },
});

/** Поля ЮKassa показываются, только когда способ принимает деньги онлайн. */
const isOnline = computed(() => form.fields.provider !== 'none');

const endpoint = computed(() => (modal.value === 'delivery' ? 'shop/delivery-methods' : 'shop/payment-methods'));

async function load() {
    try {
        const [delivery, payment] = await Promise.all([
            api.get('shop/delivery-methods'),
            api.get('shop/payment-methods'),
        ]);

        deliveries.value = delivery.data;
        symbol.value = delivery.currency_symbol;
        payments.value = payment.data;
        providers.value = payment.providers ?? [];
    } catch (error) {
        ui.notifyError(error);
    }
}

function openEditor(kind, row = null) {
    modal.value = kind;
    editing.value = row;

    form.reset({
        name: row?.name ?? '',
        description: row?.description ?? '',
        price: row?.price ?? 0,
        free_from: row?.free_from ?? '',
        is_active: row?.is_active ?? true,
        sort: row?.sort ?? 500,
        provider: row?.provider ?? 'none',
        settings: {
            shop_id: row?.settings?.shop_id ?? '',
            // Сохранённый ключ приходит маской — её и отправим обратно.
            secret_key: row?.settings?.secret_key ?? '',
            description: row?.settings?.description ?? 'Заказ №{number}',
            auto_redirect: row?.settings?.auto_redirect ?? false,
        },
    });
}

async function save() {
    const body = { ...form.fields };

    if (modal.value === 'payment') {
        delete body.price;
        delete body.free_from;
    } else {
        delete body.provider;
        delete body.settings;
    }

    const data = await form.submit(
        editing.value ? 'put' : 'post',
        editing.value ? `${endpoint.value}/${editing.value.id}` : endpoint.value,
        { body },
    );

    if (data) {
        modal.value = null;
        load();
    }
}

/** Проверка ключей: запрос к ЮKassa теми данными, что сейчас в форме. */
async function check() {
    checking.value = true;

    try {
        const data = await api.post(`shop/payment-methods/${editing.value.id}/check`, {
            shop_id: form.fields.settings.shop_id,
            secret_key: form.fields.settings.secret_key,
        });

        ui.notify(data.message);
    } catch (error) {
        ui.notifyError(error);
    } finally {
        checking.value = false;
    }
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
                                {{ Number(row.price) > 0 ? `${formatMoney(row.price)} ${symbol}` : 'бесплатно' }}
                                <template v-if="row.free_from !== null"> · бесплатно от {{ formatMoney(row.free_from) }} {{ symbol }}</template>
                            </p>
                        </div>
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

        <NModal :model-value="modal !== null" :title="modal === 'delivery' ? 'Способ доставки' : 'Способ оплаты'"
                @update:model-value="modal = null">
            <div class="space-y-5">
                <NField label="Название" required :error="form.error('name')">
                    <NInput v-model="form.fields.name" :invalid="Boolean(form.error('name'))" />
                </NField>

                <NField label="Описание" :error="form.error('description')">
                    <textarea v-model="form.fields.description" rows="2" class="field-input resize-y"></textarea>
                </NField>

                <div v-if="modal === 'delivery'" class="grid gap-5 sm:grid-cols-2">
                    <NField :label="`Стоимость, ${symbol}`" required :error="form.error('price')">
                        <NInput v-model="form.fields.price" type="number" min="0" step="0.01" />
                    </NField>

                    <NField :label="`Бесплатно от, ${symbol}`" hint="Пусто — всегда платно." :error="form.error('free_from')">
                        <NInput v-model="form.fields.free_from" type="number" min="0" step="0.01" />
                    </NField>
                </div>

                <template v-if="modal === 'payment'">
                    <NField label="Приём оплаты" :error="form.error('provider')"
                            hint="«Без приёма онлайн» — способ только подписывается в заказе: наличными курьеру, счёт юрлицу.">
                        <NSelect v-model="form.fields.provider" :options="providers" />
                    </NField>

                    <template v-if="isOnline">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <NField label="shopId" required :error="form.error('settings.shop_id')"
                                    hint="Идентификатор магазина из личного кабинета ЮKassa.">
                                <NInput v-model="form.fields.settings.shop_id" class="font-mono" placeholder="123456"
                                        :invalid="Boolean(form.error('settings.shop_id'))" />
                            </NField>

                            <NField label="Секретный ключ" :error="form.error('settings.secret_key')"
                                    hint="Хранится зашифрованным; сохранённый показан точками — оставьте их, чтобы не менять.">
                                <NInput v-model="form.fields.settings.secret_key" type="password" autocomplete="off"
                                        class="font-mono" placeholder="live_… или test_…"
                                        :invalid="Boolean(form.error('settings.secret_key'))" />
                            </NField>
                        </div>

                        <NField label="Назначение платежа" :error="form.error('settings.description')"
                                hint="Его видит покупатель в банке. {number} — номер заказа, {shop} — название сайта.">
                            <NInput v-model="form.fields.settings.description" placeholder="Заказ №{number}" />
                        </NField>

                        <NToggle v-model="form.fields.settings.auto_redirect" label="Сразу открывать страницу оплаты"
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
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="modal = null">Отмена</NButton>
                <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
            </template>
        </NModal>
    </div>
</template>
