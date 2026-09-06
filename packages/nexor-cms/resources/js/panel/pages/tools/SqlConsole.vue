<script setup>
import { onMounted, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import { ApiError, api } from '../../api';
import { useUi } from '../../stores/ui';

const ui = useUi();

const state = ref(null);
const query = ref('SELECT id, code, name FROM iblocks ORDER BY sort LIMIT 20;');
const result = ref(null);
const busy = ref(false);

async function run() {
    busy.value = true;
    result.value = null;

    try {
        result.value = await api.post('tools/sql', { query: query.value });
    } catch (error) {
        result.value = error instanceof ApiError && error.payload?.type === 'error'
            ? error.payload
            : { type: 'error', message: error.message };
    } finally {
        busy.value = false;
    }
}

function cell(value) {
    if (value === null) {
        return 'NULL';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

onMounted(async () => {
    try {
        state.value = await api.get('tools');
    } catch (error) {
        ui.notifyError(error);
    }
});
</script>

<template>
    <div>
        <NPageHeader title="SQL запрос"
                     description="Запрос выполняется на подключении сайта как есть. Каждый запуск пишется в журнал действий.">
            <template #actions>
                <NBadge v-if="state" color="gray">
                    {{ state.connection }} · {{ state.database }}
                </NBadge>
            </template>
        </NPageHeader>

        <NEmpty v-if="state && !state.enabled" icon="alert" title="Инструменты выключены"
                description="Включите NEXOR_TOOLS=true в .env. На боевом сервере их лучше держать выключенными." />

        <NEmpty v-else-if="state && !state.allowed" icon="shield" title="Недостаточно прав"
                description="SQL-консоль доступна только супер-администратору." />

        <div v-else-if="state" class="space-y-6">
            <NCard title="Запрос">
                <textarea v-model="query" rows="10" spellcheck="false"
                          class="field-input resize-y font-mono text-xs"
                          @keydown.ctrl.enter="run" @keydown.meta.enter="run"></textarea>

                <template #footer>
                    <span class="mr-auto text-xs text-[var(--text-faint)]">Ctrl + Enter — выполнить</span>
                    <NButton :loading="busy" icon="database" @click="run">Выполнить</NButton>
                </template>
            </NCard>

            <NCard v-if="result" :padding="result.type !== 'rows'"
                   :title="result.type === 'error' ? 'Ошибка' : 'Результат'"
                   :description="result.duration ? `${result.duration} мс` : null">
                <div v-if="result.type === 'error'"
                     class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
                    <NIcon name="alert" size="size-4 mt-0.5 shrink-0" />
                    <span class="font-mono text-xs">{{ result.message }}</span>
                </div>

                <p v-else-if="result.type === 'affected'" class="text-sm text-[var(--text-base)]">
                    Затронуто строк: <b>{{ result.affected }}</b>
                </p>

                <template v-else>
                    <p v-if="result.count === 0" class="p-5 text-sm text-[var(--text-muted)]">
                        Запрос вернул пустой результат.
                    </p>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="table-head text-xs font-medium tracking-wide uppercase">
                                <tr>
                                    <th v-for="column in result.columns" :key="column"
                                        class="px-4 py-3 font-medium whitespace-nowrap">{{ column }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in result.rows" :key="index" class="table-row">
                                    <td v-for="column in result.columns" :key="column"
                                        class="px-4 py-2 font-mono text-xs whitespace-nowrap text-[var(--text-base)]">
                                        {{ cell(row[column]) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </NCard>
        </div>
    </div>
</template>
