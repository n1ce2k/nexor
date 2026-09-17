<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NFilters from '../../components/ui/NFilters.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NPagination from '../../components/ui/NPagination.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NTable from '../../components/ui/NTable.vue';
import { api } from '../../api';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

const session = useSession();
const ui = useUi();

const rows = ref([]);
const meta = ref(null);
const roles = ref([]);
const loading = ref(true);
const query = reactive({ search: '', role: null, status: null, fields: {}, page: 1, sort: 'created_at', direction: 'desc' });

/** Свои поля: колонки списка и поля фильтра берутся из их настроек. */
const fields = ref([]);

const listFields = computed(() => fields.value.filter((field) => field.is_shown_in_list));
const filterFields = computed(() => fields.value.filter((field) => field.is_filterable));

const columns = computed(() => [
    { key: 'name', label: 'Пользователь', sortable: true },
    ...listFields.value.map((field) => ({ key: `field:${field.code}`, label: field.name })),
    { key: 'roles', label: 'Роли' },
    { key: 'is_active', label: 'Статус', align: 'center', width: '9rem' },
    { key: 'last_login_at', label: 'Последний вход', sortable: true, width: '11rem', muted: true },
    { key: 'actions', label: 'Действия', align: 'right', width: '7rem' },
]);

const dirty = computed(() => Boolean(query.search || query.role || query.status)
    || Object.values(query.fields).some((value) => value !== '' && value !== null));

async function load() {
    loading.value = true;

    try {
        const data = await api.get('users', query);

        rows.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

async function loadRoles() {
    try {
        const data = await api.get('roles', { per_page: 200 });

        roles.value = data.data.map((role) => ({ value: role.id, label: role.name }));
    } catch {
        roles.value = [];
    }
}

function sort(key) {
    query.direction = query.sort === key && query.direction === 'asc' ? 'desc' : 'asc';
    query.sort = key;
    load();
}

function apply() {
    query.page = 1;
    load();
}

function reset() {
    query.search = '';
    query.role = null;
    query.status = null;
    apply();
}

function goToPage(page) {
    query.page = page;
    load();
}

async function remove(user) {
    const confirmed = await ui.confirm({
        title: 'Удалить пользователя?',
        message: `Учётная запись «${user.name}» будет удалена.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`users/${user.id}`);

        ui.notify(data.message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' }) : 'ни разу';
}

async function loadFields() {
    try {
        fields.value = (await api.get('users/schema')).fields;
    } catch {
        // Схема не критична: список работает и без своих колонок.
    }
}

onMounted(() => {
    load();
    loadRoles();
    loadFields();
});
</script>

<template>
    <div>
        <NPageHeader title="Пользователи" description="Учётные записи с доступом в панель управления.">
            <template #actions>
                <NButton v-if="session.can('user_fields.manage')" variant="secondary" icon="list-tree"
                         :to="{ name: 'users.fields' }">Поля</NButton>
                <NButton v-if="session.can('users.create')" icon="plus" :to="{ name: 'users.create' }">
                    Добавить пользователя
                </NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <div class="border-b border-[var(--surface-border)] p-4">
                <NFilters v-model="query.search" placeholder="Имя, логин или e-mail..." :dirty="dirty"
                          @apply="apply" @reset="reset">
                    <NSelect v-model="query.role" :options="roles" placeholder="Все роли" class="w-auto" />
                    <NSelect v-model="query.status" class="w-auto" placeholder="Любой статус"
                             :options="[{ value: 'active', label: 'Активные' }, { value: 'blocked', label: 'Заблокированные' }]" />

                    <template v-for="field in filterFields" :key="field.id">
                        <NSelect v-if="field.type === 'select'" v-model="query.fields[field.code]" class="w-auto"
                                 :placeholder="field.name"
                                 :options="field.enums.map((item) => ({ value: item.id, label: item.value }))" />
                        <NSelect v-else-if="field.type === 'boolean'" v-model="query.fields[field.code]" class="w-auto"
                                 :placeholder="field.name"
                                 :options="[{ value: '1', label: 'Да' }, { value: '0', label: 'Нет' }]" />
                        <NInput v-else v-model="query.fields[field.code]" class="w-auto" :placeholder="field.name"
                                :type="['integer', 'decimal'].includes(field.type) ? 'number' : (field.type === 'date' ? 'date' : 'text')" />
                    </template>
                </NFilters>
            </div>

            <NEmpty v-if="!loading && rows.length === 0" icon="users" title="Пользователи не найдены"
                    description="Измените условия поиска или добавьте нового пользователя." />

            <template v-else>
                <NTable :columns="columns" :rows="rows" :sort="query.sort" :direction="query.direction"
                        :loading="loading" @sort="sort">
                    <template v-for="field in listFields" #[`cell-field:${field.code}`]="{ row }" :key="field.id">
                        <span class="text-xs text-[var(--text-muted)]">{{ row.list_fields?.[field.code] || '—' }}</span>
                    </template>

                    <template #cell-name="{ row }">
                        <div class="flex items-center gap-3">
                            <img v-if="row.avatar_url" :src="row.avatar_url" alt=""
                                 class="size-9 rounded-full object-cover">
                            <span v-else class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[var(--surface-muted)] text-xs font-semibold text-[var(--text-muted)]">
                                {{ row.initials }}
                            </span>

                            <div class="min-w-0">
                                <p class="truncate font-medium text-[var(--text-strong)]">{{ row.name }}</p>
                                <p class="truncate text-xs text-[var(--text-muted)]">
                                    <span class="font-mono">{{ row.login }}</span> · {{ row.email }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <template #cell-roles="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <NBadge v-for="role in row.roles" :key="role.id"
                                    :color="role.is_super_admin ? 'violet' : 'blue'">
                                {{ role.name }}
                            </NBadge>
                            <span v-if="!row.roles?.length" class="text-xs text-[var(--text-faint)]">—</span>
                        </div>
                    </template>

                    <template #cell-is_active="{ row }">
                        <NBadge :color="row.is_active ? 'green' : 'red'">
                            {{ row.is_active ? 'Активен' : 'Заблокирован' }}
                        </NBadge>
                    </template>

                    <template #cell-last_login_at="{ row }">
                        <span class="text-xs">{{ formatDate(row.last_login_at) }}</span>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-0.5">
                            <router-link v-if="session.can('users.update')"
                                         :to="{ name: 'users.edit', params: { user: row.id } }" title="Изменить"
                                         class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                <NIcon name="pencil" size="size-4" />
                            </router-link>

                            <button v-if="session.can('users.delete') && row.id !== session.user?.id" type="button"
                                    title="Удалить"
                                    class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                    @click="remove(row)">
                                <NIcon name="trash" size="size-4" />
                            </button>
                        </div>
                    </template>
                </NTable>

                <NPagination :meta="meta" @change="goToPage" />
            </template>
        </NCard>
    </div>
</template>
