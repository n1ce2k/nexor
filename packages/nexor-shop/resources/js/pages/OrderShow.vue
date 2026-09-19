<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import {
    api, formatDate, formatMoney, formatQuantity, NBadge, NButton, NCard, NField, NInput, NModal, NPageHeader, NSelect,
    useForm, useSession, useUi,
} from '../core.js';

/**
 * Карточка заказа: покупатель, товары, итог; со Standart — доставка и оплата.
 */
const props = defineProps({
    order: { type: [String, Number], required: true },
});

const router = useRouter();
const session = useSession();
const ui = useUi();

const data = ref(null);
const statuses = ref([]);
const checkout = ref(false);

const form = useForm({ status: 'new', manager_comment: '' });

const canUpdate = computed(() => session.can('shop.orders.update'));
const canRefund = computed(() => session.can('shop.payments.refund'));
const symbol = computed(() => data.value?.currency_symbol ?? '');

const syncing = ref(false);
const refunding = ref(null);
const refundForm = useForm({ amount: '', reason: '' });

/** Платежи есть только у заказов с онлайн-оплатой — иначе показывать нечего. */
const payments = computed(() => data.value?.payments ?? []);

const filledCustomer = computed(() => (data.value?.customer ?? []).filter((field) => field.value));

async function load() {
    try {
        const response = await api.get(`shop/orders/${props.order}`);

        data.value = response.data;
        statuses.value = response.statuses ?? [];
        checkout.value = Boolean(response.checkout);

        form.fill({ status: data.value.status, manager_comment: data.value.manager_comment ?? '' });
    } catch (error) {
        ui.notifyError(error);
    }
}

async function save() {
    const response = await form.submit('put', `shop/orders/${props.order}`);

    if (response) {
        data.value = response.data;
    }
}

async function remove() {
    const confirmed = await ui.confirm({
        title: 'Удалить заказ?',
        message: `Заказ №${data.value.number} уйдёт в корзину удалённых.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const response = await api.delete(`shop/orders/${props.order}`);

        ui.notify(response.message);
        router.push({ name: 'shop.orders' });
    } catch (error) {
        ui.notifyError(error);
    }
}

/** Спрашиваем ЮKassa о состоянии платежей: уведомление могло не дойти. */
async function syncPayments() {
    syncing.value = true;

    try {
        const response = await api.post(`shop/orders/${props.order}/payments/sync`);

        data.value = response.data;
        ui.notify(response.message);
    } catch (error) {
        ui.notifyError(error);
    } finally {
        syncing.value = false;
    }
}

function openRefund(payment) {
    refunding.value = payment;
    refundForm.reset({ amount: payment.refundable, reason: '' });
}

async function refund() {
    const response = await refundForm.submit('post', `shop/orders/${props.order}/payments/${refunding.value.id}/refund`);

    if (response) {
        data.value = response.data;
        refunding.value = null;
    }
}

function fieldHref(field) {
    if (field.type === 'email') {
        return `mailto:${field.value}`;
    }

    return field.type === 'phone' ? `tel:${String(field.value).replace(/[^0-9+]/g, '')}` : null;
}

onMounted(load);
</script>

<template>
    <div v-if="data" class="space-y-6">
        <NPageHeader :title="`Заказ №${data.number}`" :back="{ name: 'shop.orders' }"
                     :description="`Оформлен ${formatDate(data.created_at)} · корзина ${data.edition === 'ultimate' ? 'Ultimate' : 'Basic'}`">
            <template #actions>
                <NBadge :color="data.status_color">{{ data.status_label }}</NBadge>
                <NButton v-if="session.can('shop.orders.delete')" variant="ghost" size="sm" icon="trash" @click="remove">
                    Удалить
                </NButton>
            </template>
        </NPageHeader>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-6">
                <NCard title="Товары" :padding="false">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="table-head text-left text-xs uppercase">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Товар</th>
                                    <th class="px-4 py-3 text-right font-medium">Цена</th>
                                    <th class="px-4 py-3 text-center font-medium">Кол-во</th>
                                    <th class="px-4 py-3 text-right font-medium">Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in data.items" :key="item.id" class="table-row">
                                    <td class="px-4 py-3">
                                        <a v-if="item.url" :href="item.url" target="_blank"
                                           class="font-medium text-[var(--text-strong)] hover:text-brand-600 hover:underline">{{ item.name }}</a>
                                        <span v-else class="font-medium text-[var(--text-strong)]">{{ item.name }}</span>
                                        <p v-if="item.is_converted" class="mt-0.5 text-xs text-amber-600">
                                            ≈ пересчитано из {{ formatMoney(item.original_price) }} {{ item.original_currency }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        {{ formatMoney(item.price) }} {{ symbol }}
                                        <span v-if="Number(item.base_price) > Number(item.price)"
                                              class="block text-xs text-[var(--text-faint)] line-through">
                                            {{ formatMoney(item.base_price) }} {{ symbol }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">{{ formatQuantity(item.quantity) }} {{ item.measure }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <span class="font-medium text-[var(--text-strong)]">{{ formatMoney(item.sum) }} {{ symbol }}</span>
                                        <span v-if="Number(item.discount) > 0" class="block text-xs text-emerald-600">
                                            −{{ formatMoney(item.discount) }} по промокоду
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <template #footer>
                        <dl class="w-full max-w-xs space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-[var(--text-muted)]">Товары</dt>
                                <dd>{{ formatMoney(data.subtotal) }} {{ symbol }}</dd>
                            </div>
                            <div v-if="Number(data.discount) > 0" class="flex justify-between">
                                <dt class="text-[var(--text-muted)]">Промокод {{ data.promocode }}</dt>
                                <dd class="text-emerald-600">−{{ formatMoney(data.discount) }} {{ symbol }}</dd>
                            </div>
                            <div v-if="checkout && data.delivery" class="flex justify-between">
                                <dt class="text-[var(--text-muted)]">Доставка</dt>
                                <dd>{{ Number(data.delivery.price) > 0 ? `${formatMoney(data.delivery.price)} ${symbol}` : 'бесплатно' }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-[var(--surface-border)] pt-1.5 text-base font-semibold text-[var(--text-strong)]">
                                <dt>Итого</dt>
                                <dd>{{ formatMoney(data.total) }} {{ symbol }}</dd>
                            </div>
                        </dl>
                    </template>
                </NCard>

                <NCard v-if="checkout" title="Доставка и оплата">
                    <template v-if="payments.length" #actions>
                        <NButton variant="secondary" size="sm" :loading="syncing" @click="syncPayments">
                            Проверить статус
                        </NButton>
                    </template>

                    <dl class="grid gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-[var(--text-muted)]">Доставка</dt>
                            <dd class="mt-0.5 text-[var(--text-strong)]">{{ data.delivery?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[var(--text-muted)]">Оплата</dt>
                            <dd class="mt-0.5 text-[var(--text-strong)]">{{ data.payment ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[var(--text-muted)]">Состояние оплаты</dt>
                            <dd class="mt-0.5 flex flex-wrap items-center gap-2">
                                <NBadge :color="data.payment_status_color">{{ data.payment_status_label }}</NBadge>
                                <span v-if="data.paid_at" class="text-xs text-[var(--text-muted)]">{{ formatDate(data.paid_at) }}</span>
                            </dd>
                        </div>
                    </dl>

                    <ul v-if="payments.length" class="mt-5 space-y-3 border-t border-[var(--surface-border)] pt-5">
                        <li v-for="payment in payments" :key="payment.id" class="rounded-xl border border-[var(--surface-border)] p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <NBadge :color="payment.status_color">{{ payment.status_label }}</NBadge>
                                <span class="font-medium text-[var(--text-strong)]">{{ formatMoney(payment.amount) }} {{ symbol }}</span>
                                <span v-if="Number(payment.refunded) > 0" class="text-sm text-[var(--text-muted)]">
                                    возвращено {{ formatMoney(payment.refunded) }} {{ symbol }}
                                </span>
                                <span class="text-xs text-[var(--text-muted)]">
                                    {{ payment.provider_label }} · {{ formatDate(payment.created_at) }}
                                </span>

                                <div class="ml-auto flex items-center gap-2">
                                    <a v-if="payment.external_url" :href="payment.external_url" target="_blank" rel="noopener"
                                       class="text-xs text-[var(--text-muted)] hover:text-brand-600 hover:underline">в ЮKassa</a>
                                    <NButton v-if="canRefund && Number(payment.refundable) > 0" variant="ghost" size="sm"
                                             @click="openRefund(payment)">
                                        Вернуть деньги
                                    </NButton>
                                </div>
                            </div>

                            <p v-if="payment.cancellation_reason" class="mt-2 text-xs text-[var(--text-muted)]">
                                Причина отмены: {{ payment.cancellation_reason }}
                            </p>

                            <ul v-if="payment.refunds.length" class="mt-3 space-y-1 text-sm">
                                <li v-for="item in payment.refunds" :key="item.id"
                                    class="flex flex-wrap items-center gap-2 text-[var(--text-muted)]">
                                    <span>Возврат {{ formatMoney(item.amount) }} {{ symbol }}</span>
                                    <span class="text-xs">{{ formatDate(item.created_at) }}</span>
                                    <span v-if="!item.is_succeeded" class="text-xs text-amber-600">в обработке</span>
                                    <span v-if="item.reason" class="text-xs">· {{ item.reason }}</span>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </NCard>
            </div>

            <div class="space-y-6">
                <NCard title="Покупатель">
                    <dl class="space-y-3 text-sm">
                        <div v-for="field in filledCustomer" :key="field.code">
                            <dt class="text-xs text-[var(--text-muted)]">{{ field.name }}</dt>
                            <dd class="mt-0.5 break-words whitespace-pre-line text-[var(--text-strong)]">
                                <a v-if="fieldHref(field)" :href="fieldHref(field)" class="hover:text-brand-600 hover:underline">{{ field.value }}</a>
                                <template v-else>{{ field.value }}</template>
                            </dd>
                        </div>
                        <p v-if="!filledCustomer.length" class="text-[var(--text-muted)]">Поля формы не заполнены.</p>
                    </dl>
                </NCard>

                <NCard title="Обработка">
                    <div class="space-y-4">
                        <NField label="Статус" :error="form.error('status')">
                            <NSelect v-model="form.fields.status" :options="statuses" :disabled="!canUpdate" />
                        </NField>

                        <NField label="Комментарий менеджера" hint="Покупатель его не видит." :error="form.error('manager_comment')">
                            <textarea v-model="form.fields.manager_comment" rows="4" class="field-input resize-y"
                                      :disabled="!canUpdate"></textarea>
                        </NField>
                    </div>

                    <template v-if="canUpdate" #footer>
                        <NButton size="sm" :loading="form.busy.value" @click="save">Сохранить</NButton>
                    </template>
                </NCard>
            </div>
        </div>

        <NModal :model-value="refunding !== null" title="Возврат денег" @update:model-value="refunding = null">
            <div v-if="refunding" class="space-y-5">
                <p class="text-sm text-[var(--text-muted)]">
                    Деньги уйдут покупателю тем же способом, которым он платил. Доступно к возврату
                    {{ formatMoney(refunding.refundable) }} {{ symbol }}.
                </p>

                <NField :label="`Сумма, ${symbol}`" required :error="refundForm.error('amount')">
                    <NInput v-model="refundForm.fields.amount" type="number" min="0.01" step="0.01"
                            :max="refunding.refundable" :invalid="Boolean(refundForm.error('amount'))" />
                </NField>

                <NField label="Причина" hint="Останется в истории заказа; покупателю не показывается."
                        :error="refundForm.error('reason')">
                    <NInput v-model="refundForm.fields.reason" placeholder="Не подошёл размер" />
                </NField>
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="refunding = null">Отмена</NButton>
                <NButton size="sm" :loading="refundForm.busy.value" @click="refund">Вернуть</NButton>
            </template>
        </NModal>
    </div>
</template>
