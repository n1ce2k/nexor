<script setup>
import { onMounted, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NPagination from '../../components/ui/NPagination.vue';
import NTable from '../../components/ui/NTable.vue';
import { api } from '../../api';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

const session = useSession();
const ui = useUi();

const rows = ref([]);
const meta = ref(null);
const loading = ref(true);
const page = ref(1);

const columns = [
    { key: 'name', label: 'Роль' },
    { key: 'code', label: 'Код', muted: true, width: '12rem' },
    { key: 'permissions_count', label: 'Прав', align: 'center', width: '8rem' },
    { key: 'users_count', label: 'Пользователей', align: 'center', width: '10rem', muted: true },
    { key: 'actions', label: 'Действия', align: 'right', width: '7rem' },
];

async function load() {
    loading.value = true;

    try {
        const data = await api.get('roles', { page: page.value });

        rows.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function goToPage(next) {
    page.value = next;
    load();
}

async function remove(role) {
    const confirmed = await ui.confirm({
        title: 'Удалить роль?',
        message: `Роль «${role.name}» будет удалена.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`roles/${role.id}`);

        ui.notify(data.message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(load);
</script>

<template>
    <div>
        <NPageHeader title="Роли и права" description="Набор прав, который назначается пользователям.">
            <template #actions>
                <NButton v-if="session.can('roles.create')" icon="plus" :to="{ name: 'roles.create' }">
                    Добавить роль
                </NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <NTable :columns="columns" :rows="rows" :loading="loading">
                <template #cell-name="{ row }">
                    <p class="flex items-center gap-2 font-medium text-[var(--text-strong)]">
                        {{ row.name }}
                        <NBadge v-if="row.is_system">системная</NBadge>
                    </p>
                    <p v-if="row.description" class="mt-0.5 text-xs text-[var(--text-muted)]">{{ row.description }}</p>
                </template>

                <template #cell-code="{ row }">
                    <code class="font-mono text-xs">{{ row.code }}</code>
                </template>

                <template #cell-permissions_count="{ row }">
                    <NBadge :color="row.is_super_admin ? 'violet' : 'blue'">
                        {{ row.is_super_admin ? 'все' : row.permissions_count }}
                    </NBadge>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-0.5">
                        <router-link v-if="session.can('roles.update')"
                                     :to="{ name: 'roles.edit', params: { role: row.id } }" title="Изменить"
                                     class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                            <NIcon name="pencil" size="size-4" />
                        </router-link>

                        <button v-if="session.can('roles.delete') && !row.is_system" type="button" title="Удалить"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="remove(row)">
                            <NIcon name="trash" size="size-4" />
                        </button>
                    </div>
                </template>
            </NTable>

            <NPagination :meta="meta" @change="goToPage" />
        </NCard>
    </div>
</template>
