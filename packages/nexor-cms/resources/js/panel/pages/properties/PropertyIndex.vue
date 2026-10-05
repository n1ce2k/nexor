<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NTable from '../../components/ui/NTable.vue';
import NTabs from '../../components/ui/NTabs.vue';
import { api } from '../../api';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * Свойства инфоблока: у элементов и у разделов — свои списки.
 *
 * Список один и тот же, переключается адресом `?target=section`: так на
 * нужный список можно дать ссылку, а после сохранения свойства вернуться туда же.
 */
const props = defineProps({ iblock: { type: [String, Number], required: true } });
const route = useRoute();
const router = useRouter();
const session = useSession();
const ui = useUi();

const target = computed(() => (route.query.target === 'section' ? 'section' : 'element'));
const forSections = computed(() => target.value === 'section');

/** Адрес этого же списка — для ссылок «назад» и после сохранения. */
const query = computed(() => (forSections.value ? { target: 'section' } : {}));

const targets = [
    { key: 'element', label: 'Свойства элементов' },
    { key: 'section', label: 'Свойства разделов' },
];

function switchTarget(key) {
    router.replace({ name: 'properties.index', params: { iblock: props.iblock }, query: key === 'section' ? { target: 'section' } : {} });
}

const rows = ref([]);

const loading = ref(true);

// Не `iblock`: в шаблоне это имя уже занято пропом с id, и ссылки получали объект.
const info = ref(session.iblock(props.iblock));

const columns = [
    { key: 'id', label: 'id', width: '4rem', muted: true },
    { key: 'name', label: 'Название' },


    { key: 'code', label: 'Код', muted: true },
    { key: 'sort', label: 'Сорт.', width: '4rem', muted: true },
    { key: 'type_label', label: 'Тип' },
    { key: 'flags', label: 'Флаги' },
    // { key: 'values_count', label: 'Значений', align: 'center', width: '8rem', muted: true },

    { key: 'actions', label: 'Действия', align: 'right', width: '7rem' },
];

async function load() {
    loading.value = true;

    try {
        const [list, meta] = await Promise.all([
            api.get(`iblocks/${props.iblock}/properties`, forSections.value ? { target: 'section' } : {}),
            info.value ? Promise.resolve(null) : api.get(`iblocks/${props.iblock}`),
        ]);



        rows.value = list.data;


        if (meta) {
            info.value = meta.data;
        }
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

async function remove(property) {
    const confirmed = await ui.confirm({
        title: 'Удалить свойство?',
        message: `Свойство «${property.name}» и все его значения (${property.values_count} шт.) будут удалены безвозвратно.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`iblocks/${props.iblock}/properties/${property.id}`);

        ui.notify(data.message);
        load();
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(load);

watch(target, load);
</script>

<template>
    <div>
        <NPageHeader :title="`${forSections ? 'Свойства разделов' : 'Свойства'}: ${info?.name ?? ''}`"
                     :back="{ name: 'iblocks.index' }"
                     :description="forSections
                         ? 'Набор полей, который будет у каждого раздела этого инфоблока.'
                         : 'Набор полей, который будет у каждого элемента этого инфоблока.'"
                     :breadcrumbs="[
                         { label: 'Инфоблоки', to: { name: 'iblocks.index' } },
                         { label: info?.name ?? '', to: { name: 'elements.index', params: { iblock } } },
                         { label: forSections ? 'Свойства разделов' : 'Свойства' },
                     ]">
            <template #actions>
                <NButton v-if="forSections" variant="secondary" icon="folder"
                         :to="{ name: 'sections.index', params: { iblock } }">
                    Разделы
                </NButton>
                <NButton v-else variant="secondary" icon="document" :to="{ name: 'elements.index', params: { iblock } }">
                    Наполнение
                </NButton>
                <NButton icon="plus" :to="{ name: 'properties.create', params: { iblock }, query }">
                    {{ forSections ? 'Добавить свойство раздела' : 'Добавить свойство' }}
                </NButton>
            </template>
        </NPageHeader>

        <!-- Свойства разделов есть только у инфоблока с разделами. -->
        <div v-if="info?.has_sections" class="mb-4">
            <NTabs :model-value="target" :tabs="targets" @update:model-value="switchTarget" />
        </div>

        <NCard :padding="false">
            <NEmpty v-if="!loading && rows.length === 0" icon="grip"
                    :title="forSections ? 'Свойств разделов пока нет' : 'Свойств пока нет'"
                    :description="forSections
                        ? 'У раздела уже есть название, картинка и описание. Свойства добавляют к ним ваши собственные данные — баннер, цвет плашки, текст под заголовком.'
                        : 'У элементов уже есть базовые поля. Свойства добавляют к ним ваши собственные данные — цену, HEX-код цвета, привязку к другому инфоблоку.'">
                <NButton icon="plus" :to="{ name: 'properties.create', params: { iblock }, query }">
                    Добавить первое свойство
                </NButton>
            </NEmpty>

            <NTable v-else :columns="columns" :rows="rows" :loading="loading">

                <template #cell-name="{ row }">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-[var(--text-strong)]">{{ row.name }}</p>
                        <NBadge v-if="!row.is_active">выключено</NBadge>
                    </div>
                    <p v-if="row.hint" class="mt-0.5 text-xs text-[var(--text-muted)]">{{ row.hint }}</p>
                </template>

                <template #cell-code="{ row }">
                    <code class="font-mono text-xs">{{ row.code }}</code>
                </template>

                <template #cell-type_label="{ row }">
                    <NBadge color="blue">{{ row.type_label }}</NBadge>
                    <span v-if="row.enums?.length" class="ml-1 text-xs text-[var(--text-muted)]">
                        {{ row.enums.length }} знач.
                    </span>
                </template>

                <template #cell-flags="{ row }">
                    <div
                        v-if="row.is_multiple ||
                        row.is_required ||
                        row.is_filterable ||
                        row.is_searchable ||
                        row.is_shown_in_list"
                        class="flex flex-wrap gap-1">
                        <NBadge v-if="row.is_multiple" color="violet">множественное</NBadge>
                        <NBadge v-if="row.is_required" color="red">обязательное</NBadge>
                        <NBadge v-if="row.is_filterable" color="amber">фильтр</NBadge>
                        <NBadge v-if="row.is_searchable" color="green">поиск</NBadge>
                        <NBadge v-if="row.is_shown_in_list">в списке</NBadge>
                    </div>
                    <div v-else class="text-gray-400 text-xs">
                        Нет флагов
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-0.5">
                        <router-link :to="{ name: 'properties.edit', params: { iblock, property: row.id }, query }"
                                     title="Изменить"
                                     class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                            <NIcon name="pencil" size="size-4" />
                        </router-link>

                        <button type="button" title="Удалить"
                                class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="remove(row)">
                            <NIcon name="trash" size="size-4" />
                        </button>
                    </div>
                </template>
            </NTable>
        </NCard>
    </div>
</template>
