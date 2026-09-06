<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NField from '../../components/ui/NField.vue';
import NFilters from '../../components/ui/NFilters.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NModal from '../../components/ui/NModal.vue';
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
const query = reactive({ search: '', page: 1 });

const testOpen = ref(false);
const testTarget = ref(null);
const testAddress = ref('');
const sending = ref(false);

const canEdit = computed(() => session.can('mail.update'));

const columns = [
    { key: 'name', label: 'Шаблон' },
    { key: 'code', label: 'Код', muted: true, width: '14rem' },
    { key: 'subject', label: 'Тема' },
    { key: 'body_type', label: 'Формат', align: 'center', width: '7rem' },
    { key: 'is_active', label: 'Статус', align: 'center', width: '7rem' },
    { key: 'actions', label: 'Действия', align: 'right', width: '9rem' },
];

async function load() {
    loading.value = true;

    try {
        const data = await api.get('mail-templates', query);

        rows.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

function apply() {
    query.page = 1;
    load();
}

function reset() {
    query.search = '';
    apply();
}

function goToPage(page) {
    query.page = page;
    load();
}

async function remove(template) {
    const confirmed = await ui.confirm({
        title: 'Удалить шаблон?',
        message: `Шаблон «${template.name}» будет удалён.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`mail-templates/${template.id}`);

        ui.notify(data.message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

function openTest(template) {
    testTarget.value = template;
    testAddress.value = session.user?.email ?? '';
    testOpen.value = true;
}

async function sendTest() {
    sending.value = true;

    try {
        const data = await api.post(`mail-templates/${testTarget.value.id}/send`, { to: testAddress.value });

        ui.notify(data.message);
        testOpen.value = false;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        sending.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <NPageHeader title="Почтовые шаблоны"
                     description="Письма, которые отправляет сайт. Подстановки пишутся как #ИМЯ# и заменяются при отправке.">
            <template #actions>
                <NButton variant="secondary" icon="settings" :to="{ name: 'mail.smtp' }">SMTP</NButton>
                <NButton v-if="canEdit" icon="plus" :to="{ name: 'mail.templates.create' }">Добавить шаблон</NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <div class="border-b border-[var(--surface-border)] p-4">
                <NFilters v-model="query.search" placeholder="Название или код..." :dirty="Boolean(query.search)"
                          @apply="apply" @reset="reset" />
            </div>

            <NEmpty v-if="!loading && rows.length === 0" icon="document" title="Шаблонов нет"
                    description="Шаблон описывает одно письмо: от кого, кому, тему и текст.">
                <NButton v-if="canEdit" icon="plus" :to="{ name: 'mail.templates.create' }">Создать шаблон</NButton>
            </NEmpty>

            <template v-else>
                <NTable :columns="columns" :rows="rows" :loading="loading">
                    <template #cell-name="{ row }">
                        <p class="font-medium text-[var(--text-strong)]">{{ row.name }}</p>
                        <p v-if="row.description" class="mt-0.5 text-xs text-[var(--text-muted)]">{{ row.description }}</p>
                    </template>

                    <template #cell-code="{ row }">
                        <code class="font-mono text-xs">{{ row.code }}</code>
                    </template>

                    <template #cell-subject="{ row }">
                        <span class="text-xs">{{ row.subject }}</span>
                    </template>

                    <template #cell-body_type="{ row }">
                        <NBadge :color="row.body_type === 'html' ? 'blue' : 'gray'">
                            {{ row.body_type === 'html' ? 'HTML' : 'текст' }}
                        </NBadge>
                    </template>

                    <template #cell-is_active="{ row }">
                        <NBadge :color="row.is_active ? 'green' : 'gray'">
                            {{ row.is_active ? 'активен' : 'выключен' }}
                        </NBadge>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-0.5">
                            <button v-if="canEdit" type="button" title="Отправить тестовое письмо"
                                    class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
                                    @click="openTest(row)">
                                <NIcon name="upload" size="size-4" />
                            </button>

                            <router-link v-if="canEdit" :to="{ name: 'mail.templates.edit', params: { template: row.id } }"
                                         title="Изменить"
                                         class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                <NIcon name="pencil" size="size-4" />
                            </router-link>

                            <button v-if="canEdit" type="button" title="Удалить"
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

        <NModal v-model="testOpen" title="Тестовое письмо" max-width="max-w-md">
            <NField label="Адрес получателя" hint="Подстановки будут заменены на свои имена в квадратных скобках.">
                <NInput v-model="testAddress" type="email" />
            </NField>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="testOpen = false">Отмена</NButton>
                <NButton size="sm" :loading="sending" @click="sendTest">Отправить</NButton>
            </template>
        </NModal>
    </div>
</template>
