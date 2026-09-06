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
const code = ref("return \\Nexor\\Cms\\Models\\Iblock::query()->pluck('name', 'code');");
const result = ref(null);
const busy = ref(false);

async function run() {
    busy.value = true;
    result.value = null;

    try {
        result.value = await api.post('tools/php', { code: code.value });
    } catch (error) {
        result.value = error instanceof ApiError && error.payload?.type === 'error'
            ? error.payload
            : { type: 'error', message: error.message };
    } finally {
        busy.value = false;
    }
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
        <NPageHeader title="PHP-строка"
                     description="Код выполняется в контексте приложения. Каждый запуск пишется в журнал действий.">
            <template #actions>
                <NBadge v-if="state" color="gray">PHP {{ state.php_version }} · {{ state.environment }}</NBadge>
            </template>
        </NPageHeader>

        <NEmpty v-if="state && !state.enabled" icon="alert" title="Инструменты выключены"
                description="Включите NEXOR_TOOLS=true в .env." />

        <NEmpty v-else-if="state && !state.php_allowed" icon="alert" title="PHP-консоль выключена"
                description="Отдельный переключатель: NEXOR_TOOLS_PHP=true. Держите его выключенным на боевом сервере." />

        <NEmpty v-else-if="state && !state.allowed" icon="shield" title="Недостаточно прав"
                description="PHP-консоль доступна только супер-администратору." />

        <div v-else-if="state" class="space-y-6">
            <NCard>
                <div class="mb-4 flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-400">
                    <NIcon name="alert" size="size-4 mt-0.5 shrink-0" />
                    <span>
                        Код выполняется с правами процесса сайта — ошибка здесь может испортить данные.
                        Выражение без точки с запятой считается возвращаемым значением.
                    </span>
                </div>

                <textarea v-model="code" rows="12" spellcheck="false"
                          class="field-input resize-y font-mono text-xs"
                          @keydown.ctrl.enter="run" @keydown.meta.enter="run"></textarea>

                <template #footer>
                    <span class="mr-auto text-xs text-[var(--text-faint)]">Ctrl + Enter — выполнить</span>
                    <NButton :loading="busy" icon="database" @click="run">Выполнить</NButton>
                </template>
            </NCard>

            <NCard v-if="result"
                   :title="result.type === 'error' ? 'Ошибка' : 'Результат'"
                   :description="result.duration ? `${result.duration} мс` : null">
                <div v-if="result.type === 'error'"
                     class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
                    <NIcon name="alert" size="size-4 mt-0.5 shrink-0" />
                    <span class="font-mono text-xs">
                        {{ result.message }}<template v-if="result.line"> (строка {{ result.line }})</template>
                    </span>
                </div>

                <div v-else class="space-y-4">
                    <div v-if="result.output">
                        <p class="mb-1.5 text-xs font-medium tracking-wide text-[var(--text-muted)] uppercase">Вывод</p>
                        <pre class="overflow-x-auto rounded-lg bg-[var(--surface-sunken)] p-3 font-mono text-xs text-[var(--text-base)]">{{ result.output }}</pre>
                    </div>

                    <div>
                        <p class="mb-1.5 text-xs font-medium tracking-wide text-[var(--text-muted)] uppercase">Возвращено</p>
                        <pre class="overflow-x-auto rounded-lg bg-[var(--surface-sunken)] p-3 font-mono text-xs text-[var(--text-base)]">{{ result.returned }}</pre>
                    </div>
                </div>
            </NCard>
        </div>
    </div>
</template>
