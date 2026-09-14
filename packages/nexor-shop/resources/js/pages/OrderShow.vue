<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import {
    api, formatDate, formatMoney, formatQuantity, NBadge, NButton, NCard, NField, NPageHeader, NSelect, useForm, useSession, useUi,
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
const symbol = computed(() => data.value?.currency_symbol ?? '');

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
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-[var(--text-muted)]">Доставка</dt>
                            <dd class="mt-0.5 text-[var(--text-strong)]">{{ data.delivery?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[var(--text-muted)]">Оплата</dt>
                            <dd class="mt-0.5 text-[var(--text-strong)]">{{ data.payment ?? '—' }}</dd>
                        </div>
                    </dl>
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
    </div>
</template>
