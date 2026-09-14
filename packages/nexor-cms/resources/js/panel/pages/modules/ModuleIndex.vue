<script setup>
import { computed, onMounted, ref } from 'vue';
import NBadge from '../../components/ui/NBadge.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * Лицензия сайта, что входит в каждый уровень и установленные модули.
 */
const session = useSession();
const ui = useUi();

const loading = ref(true);
const license = ref(null);
const licenses = ref([]);
const features = ref([]);
const modules = ref([]);
const busy = ref(null);

const canUpdate = computed(() => session.can('modules.update'));

const rankOf = (value) => licenses.value.find((item) => item.value === value)?.rank ?? 0;

const labelOf = (value) => licenses.value.find((item) => item.value === value)?.label ?? value;

/** Функции ядра отдельно, функции модулей — под своим модулем. */
const featureGroups = computed(() => {
    const groups = [{ key: null, label: 'Ядро', items: [] }];

    modules.value.forEach((module) => groups.push({ key: module.code, label: module.name, items: [] }));

    features.value.forEach((feature) => {
        groups.find((group) => group.key === feature.module)?.items.push(feature);
    });

    return groups.filter((group) => group.items.length);
});

async function load() {
    loading.value = true;

    try {
        const data = await api.get('modules');

        license.value = data.license;
        licenses.value = data.licenses;
        features.value = data.features;
        modules.value = data.modules;
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

async function toggle(module, enabled) {
    busy.value = module.code;

    try {
        const data = await api.put(`modules/${module.code}`, { is_enabled: enabled });

        ui.notify(data.message);
        await Promise.all([load(), session.refreshFeatures()]);
    } catch (error) {
        ui.notifyError(error);
    } finally {
        busy.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <NPageHeader title="Модули" description="Лицензия сайта, функции по уровням и установленные модули." />

        <template v-if="!loading && license">
            <div>
                Лицензия: {{ license.label }}
            </div>
<!--            <NCard title="Лицензия">-->
<!--                -->

<!--            </NCard>-->

            <NCard title="Установленные модули" :padding="false">
                <NEmpty v-if="!modules.length" icon="puzzle" title="Модулей пока нет"
                        description="Модуль подключается composer-пакетом и появится здесь сам." />

                <ul v-else class="divide-y divide-[var(--surface-border)]">
                    <li v-for="module in modules" :key="module.code" class="flex flex-wrap items-center gap-4 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 font-medium text-[var(--text-strong)]">
                                {{ module.name }}
                                <code class="rounded bg-[var(--surface-muted)] px-1.5 py-0.5 font-mono text-xs font-normal text-[var(--text-muted)]">
                                    {{ module.code }} · {{ module.version }}
                                </code>
                                <NBadge color="violet">{{ module.license.label }}</NBadge>
                            </p>
                            <p v-if="module.description" class="mt-1 text-sm text-[var(--text-muted)]">{{ module.description }}</p>
                            <p v-if="!module.is_licensed" class="mt-1 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                <NIcon name="lock" size="size-3.5" />
                                Не входит в лицензию {{ license.label }} - данные модуля сохранены, но он не работает.
                            </p>
                        </div>




                        <NBadge :color="module.is_active ? 'green' : 'gray'">
                            {{ module.is_active ? 'работает' : 'выключен' }}
                        </NBadge>

                        <NToggle :model-value="module.is_enabled"
                                 :disabled="!canUpdate || !module.is_licensed || busy === module.code"
                                 @update:model-value="toggle(module, $event)" />
                    </li>
                </ul>
            </NCard>

            <NCard title="Что входит в уровни (шпаргалка)" :padding="false" style="display:none;">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[var(--surface-muted)] text-left text-xs text-[var(--text-muted)]">
                            <tr>
                                <th class="px-5 py-2 font-medium"></th>
                                <th v-for="item in licenses" :key="item.value"
                                    :class="['w-28 px-3 py-2 text-center font-medium', item.value === license.value && 'text-brand-600']">
                                    {{ item.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="group in featureGroups" :key="group.label">
                                <tr class="border-t border-[var(--surface-border)]">
                                    <td :colspan="licenses.length + 1"
                                        class="px-5 pt-3 pb-1 text-xs font-semibold tracking-wide text-[var(--text-muted)] uppercase">
                                        {{ group.label }}
                                    </td>
                                </tr>
                                <tr v-for="feature in group.items" :key="feature.code">
                                    <td class="px-5 py-2 text-[var(--text-strong)]">
                                        {{ feature.label }}
                                        <span class="ml-1 font-mono text-xs text-[var(--text-faint)]">{{ feature.code }}</span>
                                    </td>
                                    <td v-for="item in licenses" :key="item.value" class="px-3 py-2 text-center">
                                        <NIcon v-if="item.rank >= rankOf(feature.license)" name="check"
                                               :size="['mx-auto size-4', item.value === license.value ? 'text-emerald-600' : 'text-[var(--text-muted)]'].join(' ')" />
                                        <span v-else class="text-[var(--text-faint)]">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <template #footer>

                </template>
            </NCard>
        </template>
    </div>
</template>
