<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NFilters from '../../components/ui/NFilters.vue';
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
const query = reactive({ search: '', page: 1 });

const canCreate = computed(() => session.can('forms.create'));
const canEdit = computed(() => session.can('forms.update') || session.can('forms.submissions.view'));
const canDelete = computed(() => session.can('forms.delete'));
const canSubmissions = computed(() => session.can('forms.submissions.view'));

const columns = [
    { key: 'id', label: 'ID', muted: true, width: '4rem' },
    { key: 'name', label: 'Форма' },
    { key: 'mail', label: 'Почтовый шаблон' },
    { key: 'submissions', label: 'Записи', align: 'center', width: '8rem' },
    { key: 'is_active', label: 'Статус', align: 'center', width: '7rem' },
    { key: 'actions', label: 'Действия', align: 'right', width: '9rem' },
];

async function load() {
    loading.value = true;

    try {
        const data = await api.get('forms', query);

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

async function copyTag(form) {
    try {
        await navigator.clipboard.writeText(form.tag);
        ui.notify('Тег скопирован: ' + form.tag);
    } catch {
        ui.notify(form.tag);
    }
}

async function remove(form) {
    const confirmed = await ui.confirm({
        title: 'Удалить форму?',
        message: `Форма «${form.name}» будет удалена вместе со всеми записями и файлами.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`forms/${form.id}`);

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
        <NPageHeader title="Формы обратной связи"
                     description="Формы для сайта: поля, почтовый шаблон, соглашение и записи того, что прислали.">
            <template #actions>
                <NButton v-if="session.can('agreements.view')" variant="secondary" icon="check"
                         :to="{ name: 'agreements.index' }">Соглашения</NButton>
                <NButton v-if="canCreate" icon="plus" :to="{ name: 'forms.create' }">Добавить форму</NButton>
            </template>
        </NPageHeader>

        <NCard :padding="false">
            <div class="border-b border-[var(--surface-border)] p-4">
                <NFilters v-model="query.search" placeholder="Название или код..." :dirty="Boolean(query.search)"
                          @apply="apply" @reset="reset" />
            </div>

            <NEmpty v-if="!loading && rows.length === 0" icon="document" title="Форм нет"
                    description="Форма выводится на сайте тегом &lt;x-nexor::form :id=&quot;1&quot; /&gt;.">
                <NButton v-if="canCreate" icon="plus" :to="{ name: 'forms.create' }">Создать форму</NButton>
            </NEmpty>

            <template v-else>
                <NTable :columns="columns" :rows="rows" :loading="loading">
                    <template #cell-name="{ row }">
                        <router-link v-if="canEdit" :to="{ name: 'forms.edit', params: { form: row.id } }"
                                     class="font-medium text-[var(--text-strong)] hover:underline">{{ row.name }}</router-link>
                        <p v-else class="font-medium text-[var(--text-strong)]">{{ row.name }}</p>
                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-[var(--text-muted)]">
                            <code class="font-mono">{{ row.code }}</code>
                            <NBadge v-if="row.ajax" color="blue">без перезагрузки</NBadge>
                            <NBadge v-if="row.agreement" color="violet">{{ row.agreement.name }}</NBadge>
                        </p>
                    </template>

                    <template #cell-mail="{ row }">
                        <span v-if="row.mail_template" class="text-xs">{{ row.mail_template.name }}</span>
                        <span v-else class="text-xs text-[var(--text-muted)]">стандартное письмо</span>
                    </template>

                    <template #cell-submissions="{ row }">
                        <router-link v-if="canSubmissions"
                                     :to="{ name: 'forms.edit', params: { form: row.id }, query: { tab: 'submissions' } }"
                                     class="inline-flex items-center gap-1.5 text-sm hover:underline">
                            {{ row.submissions_count }}
                            <NBadge v-if="row.unread_count" color="red">+{{ row.unread_count }}</NBadge>
                        </router-link>
                        <span v-else>{{ row.submissions_count }}</span>
                    </template>

                    <template #cell-is_active="{ row }">
                        <NBadge :color="row.is_active ? 'green' : 'gray'">
                            {{ row.is_active ? 'активна' : 'выключена' }}
                        </NBadge>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-0.5">
                            <button type="button" title="Скопировать тег для шаблона"
                                    class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
                                    @click="copyTag(row)">
                                <NIcon name="code" size="size-4" />
                            </button>

                            <router-link v-if="canEdit" :to="{ name: 'forms.edit', params: { form: row.id } }"
                                         title="Изменить"
                                         class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                <NIcon name="pencil" size="size-4" />
                            </router-link>

                            <button v-if="canDelete" type="button" title="Удалить"
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
