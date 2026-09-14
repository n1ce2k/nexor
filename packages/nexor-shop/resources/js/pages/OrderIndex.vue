<script setup>
import { onMounted, reactive, ref } from 'vue';
import {
    api, formatDate, formatMoney, NBadge, NCard, NEmpty, NInput, NPageHeader, NPagination, NSelect, NTable, useUi,
} from '../core.js';

/**
 * Раздел «Заказы»: список с поиском и фильтром по статусу.
 */
const ui = useUi();

const rows = ref([]);
const meta = ref(null);
const statuses = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', status: null, page: 1 });

const columns = [
    { key: 'number', label: '№', width: '7rem' },
    { key: 'created_at', label: 'Дата', width: '10rem', muted: true },
    { key: 'customer_name', label: 'Покупатель' },
    { key: 'items_count', label: 'Товаров', align: 'center', width: '6rem', muted: true },
    { key: 'total', label: 'Сумма', align: 'right', width: '9rem' },
    { key: 'status', label: 'Статус', align: 'center', width: '8rem' },
];

let searchTimer = null;

async function load() {
    loading.value = true;

    try {
        const data = await api.get('shop/orders', filters);

        rows.value = data.data;
        meta.value = data.meta;
        statuses.value = data.statuses ?? [];
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function onSearch(value) {
    filters.search = value;
    filters.page = 1;

    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 300);
}

function onStatus(value) {
    filters.status = value;
    filters.page = 1;
    load();
}

function goTo(page) {
    filters.page = page;
    load();
}

onMounted(load);
</script>

<template>
    <div>
        <NPageHeader title="Заказы" description="Заказы из корзины и оформления, новые сверху." />

        <NCard :padding="false">
            <div class="flex flex-wrap gap-3 border-b border-[var(--surface-border)] p-4">
                <NInput :model-value="filters.search" placeholder="Номер, имя или телефон" class="sm:max-w-72"
                        @update:model-value="onSearch" />
                <NSelect :model-value="filters.status" :options="statuses" placeholder="Все статусы" class="sm:max-w-48"
                         @update:model-value="onStatus" />
            </div>

            <NEmpty v-if="!loading && rows.length === 0" icon="receipt" title="Заказов пока нет"
                    description="Заказ появится здесь, как только покупатель оформит корзину." />

            <NTable v-else :columns="columns" :rows="rows" :loading="loading">
                <template #cell-number="{ row }">
                    <router-link :to="{ name: 'shop.orders.show', params: { order: row.id } }"
                                 class="font-mono font-medium text-brand-600 hover:underline">
                        {{ row.number }}
                    </router-link>
                </template>

                <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}</template>

                <template #cell-total="{ row }">
                    <span class="font-medium text-[var(--text-strong)]">{{ formatMoney(row.total) }} {{ row.currency_symbol }}</span>
                </template>

                <template #cell-status="{ row }">
                    <NBadge :color="row.status_color">{{ row.status_label }}</NBadge>
                </template>
            </NTable>

            <NPagination :meta="meta" @change="goTo" />
        </NCard>
    </div>
</template>
