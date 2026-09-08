<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NHtmlInput from '../../components/ui/NHtmlInput.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

const props = defineProps({ iblock: { type: [String, Number], default: null } });

const router = useRouter();
const session = useSession();
const ui = useUi();

const types = ref([]);
const codeTouched = ref(false);
const loading = ref(Boolean(props.iblock));

const form = useForm({
    iblock_type_id: null,
    code: '',
    name: '',
    element_name: '',
    description: '',
    list_url: '',
    section_url: '',
    detail_url: '',
    has_sections: true,
    has_page: false,
    is_active: true,
    sort: 500,
    pagination_template: 'pagination',
    per_page: 20,
    has_load_more: false,
    load_more_size: 12,
});

const isEdit = computed(() => Boolean(props.iblock));

const pagePath = ref(null);

const paginationOptions = computed(() => session.paginationTemplates.map((template) => ({
    value: template.value,
    label: template.label,
})));

const paginationHint = computed(() => session.paginationTemplates
    .find((template) => template.value === form.fields.pagination_template)?.hint);

/** The button template pages by itself, so the separate switch is redundant. */
const loadsOnDemand = computed(() => form.fields.pagination_template === 'pagination_btnload');

const showsChunkSize = computed(() => loadsOnDemand.value || form.fields.has_load_more);

/** Preview of the caption the element list will show. */
const addLabel = computed(() => {
    const name = (form.fields.element_name ?? '').trim();

    return name === '' ? 'Добавить' : `Добавить ${name}`;
});

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value) {
        form.fields.code = slugify(value);
    }
}

async function save() {
    const data = await form.submit(
        isEdit.value ? 'put' : 'post',
        isEdit.value ? `iblocks/${props.iblock}` : 'iblocks',
    );

    if (!data) {
        return;
    }

    await session.refreshIblocks();

    router.push(isEdit.value
        ? { name: 'iblocks.index' }
        : { name: 'properties.index', params: { iblock: data.data.id } });
}

onMounted(async () => {
    try {
        const typeList = await api.get('iblock-types', { per_page: 200 });

        types.value = typeList.data.map((type) => ({ value: type.id, label: type.name }));

        if (isEdit.value) {
            const data = await api.get(`iblocks/${props.iblock}`);

            form.fill({
                iblock_type_id: data.data.iblock_type_id,
                code: data.data.code,
                name: data.data.name,
                element_name: data.data.element_name ?? '',
                description: data.data.description ?? '',
                list_url: data.data.list_url ?? '',
                section_url: data.data.section_url ?? '',
                detail_url: data.data.detail_url ?? '',
                has_sections: data.data.has_sections,
                has_page: data.data.has_page,
                is_active: data.data.is_active,
                sort: data.data.sort,
                pagination_template: data.data.pagination_template ?? 'pagination',
                per_page: data.data.per_page ?? 20,
                has_load_more: data.data.has_load_more,
                load_more_size: data.data.load_more_size ?? 12,
            });

            pagePath.value = data.data.page_path;
            codeTouched.value = true;
        }
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <NPageHeader :title="isEdit ? form.fields.name || 'Инфоблок' : 'Новый инфоблок'"
                     :back="{ name: 'iblocks.index' }"
                     description="Общие параметры инфоблока. Свойства настраиваются отдельно."
                     :breadcrumbs="[
                         { label: 'Инфоблоки', to: { name: 'iblocks.index' } },
                         { label: isEdit ? form.fields.name : 'Новый инфоблок' },
                     ]">
            <template v-if="isEdit" #actions>
                <NButton variant="secondary" icon="grip" :to="{ name: 'properties.index', params: { iblock } }">
                    Свойства
                </NButton>
                <NButton variant="secondary" icon="document" :to="{ name: 'elements.index', params: { iblock } }">
                    Наполнение
                </NButton>
            </template>
        </NPageHeader>

        <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="save">
            <div class="space-y-6 lg:col-span-2">
                <NCard title="Основное">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField label="Название" required :error="form.error('name')">
                            <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                                    @update:model-value="onName" />
                        </NField>

                        <NField label="Символьный код" required :error="form.error('code')"
                                hint="Уникальный код инфоблока, используется в шаблонах.">
                            <NInput v-model="form.fields.code" class="font-mono"
                                    :invalid="Boolean(form.error('code'))"
                                    @update:model-value="codeTouched = true" />
                        </NField>

                        <NField label="Тип инфоблока" required :error="form.error('iblock_type_id')">
                            <NSelect v-model="form.fields.iblock_type_id" :options="types"
                                     placeholder="Выберите тип" :invalid="Boolean(form.error('iblock_type_id'))" />
                        </NField>

                        <NField label="Сортировка" :error="form.error('sort')">
                            <NInput v-model="form.fields.sort" type="number" min="0" />
                        </NField>

                        <div class="sm:col-span-2">
                            <NField label="Название сущности"
                                    :hint="`Как называется одна запись инфоблока. Кнопка станет «${addLabel}»; оставьте пустым — будет просто «Добавить».`"
                                    :error="form.error('element_name')">
                                <NInput v-model="form.fields.element_name" placeholder="товар" class="sm:max-w-xs" />
                            </NField>
                        </div>

                        <div class="sm:col-span-2">
                            <NField label="Описание" :error="form.error('description')">
                                <NHtmlInput v-model="form.fields.description" rows="12rem" />
                            </NField>
                        </div>
                    </div>
                </NCard>

                <NCard title="Адреса на сайте"
                       description="Шаблоны URL для публичной части. Доступны подстановки #ID#, #CODE#, #SECTION_CODE#.">
                    <div class="space-y-5">
                        <NField label="URL списка" :error="form.error('list_url')">
                            <NInput v-model="form.fields.list_url" class="font-mono" placeholder="/catalog" />
                        </NField>

                        <NField label="URL раздела" :error="form.error('section_url')">
                            <NInput v-model="form.fields.section_url" class="font-mono"
                                    placeholder="/catalog/#SECTION_CODE#" />
                        </NField>

                        <NField label="URL элемента" :error="form.error('detail_url')">
                            <NInput v-model="form.fields.detail_url" class="font-mono"
                                    placeholder="/catalog/#SECTION_CODE#/#CODE#" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="Постраничная навигация"
                       description="Компонент pagination создаётся рядом со страницей инфоблока.">
                    <div class="space-y-5">
                        <NField label="Шаблон" :hint="paginationHint" :error="form.error('pagination_template')">
                            <NSelect v-model="form.fields.pagination_template" :options="paginationOptions" />
                        </NField>

                        <NField v-if="!loadsOnDemand" label="Элементов на странице"
                                hint="Сколько элементов показывает одна страница списка."
                                :error="form.error('per_page')">
                            <NInput v-model="form.fields.per_page" type="number" min="1" max="500" class="sm:max-w-40" />
                        </NField>

                        <NToggle v-if="!loadsOnDemand" v-model="form.fields.has_load_more"
                                 label="Кнопка «Показать ещё»"
                                 hint="Добавит кнопку под списком — следующая порция подгружается без перезагрузки." />

                        <NField v-if="showsChunkSize" label="Сколько отображать"
                                hint="Размер порции, которую добавляет кнопка."
                                :error="form.error('load_more_size')">
                            <NInput v-model="form.fields.load_more_size" type="number" min="1" max="500"
                                    class="sm:max-w-40" />
                        </NField>

                        <p class="rounded-lg bg-[var(--surface-muted)] p-3 text-xs text-[var(--text-muted)]">
                            Это шаблон компонента <code class="font-mono text-[var(--text-base)]">pagination</code>;
                            файлы при смене не перезаписываются. Своя вёрстка —
                            <code class="font-mono text-[var(--text-base)]">php artisan nexor:component pagination</code>.
                        </p>
                    </div>
                </NCard>
            </div>

            <div class="space-y-6">
                <NCard title="Параметры">
                    <div class="space-y-5">
                        <NToggle v-model="form.fields.has_sections" label="Использовать разделы"
                                 hint="Древовидная структура внутри инфоблока." />
                        <NToggle v-model="form.fields.is_active" label="Активен" />

                        <NToggle v-model="form.fields.has_page" label="Создать страницу"
                                 hint="Заведёт папку в resources/views с шаблоном страницы — как папка-страница в Битриксе." />

                        <p v-if="form.fields.has_page"
                           class="rounded-lg bg-[var(--surface-muted)] p-3 text-xs text-[var(--text-muted)]">
                            <template v-if="pagePath">
                                Файл уже создан:
                                <code class="font-mono text-[var(--text-base)]">resources/views/{{ pagePath }}</code>.
                                Повторное сохранение его не перезапишет.
                            </template>
                            <template v-else>
                                После сохранения появится папка
                                <code class="font-mono text-[var(--text-base)]">resources/views/{{ form.fields.code || 'код' }}/</code>
                                с файлами <code class="font-mono text-[var(--text-base)]">index</code> и
                                <code class="font-mono text-[var(--text-base)]">detail</code>. Список откроется по
                                <code class="font-mono text-[var(--text-base)]">/{{ form.fields.code || 'код' }}</code>,
                                элемент — по
                                <code class="font-mono text-[var(--text-base)]">/{{ form.fields.code || 'код' }}/&lt;код&gt;</code>.
                            </template>
                        </p>
                    </div>
                </NCard>

                <div class="flex items-center gap-2">
                    <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                    <NButton variant="secondary" size="lg" :to="{ name: 'iblocks.index' }">Отмена</NButton>
                </div>
            </div>
        </form>
    </div>
</template>
