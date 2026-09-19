<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

const props = defineProps({
    iblock: { type: [String, Number], required: true },
    property: { type: [String, Number], default: null },
});

const router = useRouter();
const session = useSession();
const ui = useUi();

const iblocks = ref([]);
const enums = ref([]);
const codeTouched = ref(false);

const form = useForm({
    code: '',
    name: '',
    hint: '',
    type: 'string',
    is_multiple: false,
    is_required: false,
    is_filterable: false,
    is_searchable: false,
    is_shown_in_list: false,
    is_active: true,
    sort: 500,
    default_value: '',
    with_description: false,
    settings: {},
});

const isEdit = computed(() => Boolean(props.property));

const typeOptions = computed(() => Object.entries(session.propertyTypeGroups).map(([label, types]) => ({
    label,
    options: types.map((type) => ({ value: type.value, label: type.label })),
})));

const currentType = computed(() => session.propertyType(form.fields.type));

const settingKeys = computed(() => currentType.value?.settings ?? []);

const usesEnums = computed(() => currentType.value?.uses_enums ?? false);

function uses(key) {
    return settingKeys.value.includes(key);
}

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value) {
        form.fields.code = slugify(value).toUpperCase().replace(/-/g, '_');
    }
}

function addEnum() {
    enums.value.push({ id: null, value: '', code: '', sort: (enums.value.length + 1) * 100, is_default: false });
}

function removeEnum(index) {
    enums.value.splice(index, 1);
}

watch(usesEnums, (value) => {
    if (value && enums.value.length === 0) {
        addEnum();
    }
});

async function save() {
    const payload = { ...form.fields, enums: usesEnums.value ? enums.value : [] };

    const data = await form.submit(
        isEdit.value ? 'put' : 'post',
        isEdit.value
            ? `iblocks/${props.iblock}/properties/${props.property}`
            : `iblocks/${props.iblock}/properties`,
        { body: payload },
    );

    if (data) {
        router.push({ name: 'properties.index', params: { iblock: props.iblock } });
    }
}

onMounted(async () => {
    try {
        const list = await api.get('iblocks', { per_page: 200 });

        iblocks.value = list.data.map((item) => ({ value: item.id, label: item.name }));

        if (isEdit.value) {
            const data = await api.get(`iblocks/${props.iblock}/properties/${props.property}`);

            form.fill({
                code: data.data.code,
                name: data.data.name,
                hint: data.data.hint ?? '',
                type: data.data.type,
                is_multiple: data.data.is_multiple,
                is_required: data.data.is_required,
                is_filterable: data.data.is_filterable,
                is_searchable: data.data.is_searchable,
                is_shown_in_list: data.data.is_shown_in_list,
                is_active: data.data.is_active,
                sort: data.data.sort,
                default_value: data.data.default_value ?? '',
                with_description: data.data.with_description ?? false,
                settings: data.data.settings ?? {},
            });

            enums.value = (data.data.enums ?? []).map((item) => ({ ...item }));
            codeTouched.value = true;
        }
    } catch (error) {
        ui.notifyError(error);
    }
});
</script>

<template>
    <div>
        <NPageHeader :title="isEdit ? form.fields.name || 'Свойство' : 'Новое свойство'"
                     :back="{ name: 'properties.index', params: { iblock } }"
                     :breadcrumbs="[
                         { label: 'Инфоблоки', to: { name: 'iblocks.index' } },
                         { label: 'Свойства', to: { name: 'properties.index', params: { iblock } } },
                         { label: isEdit ? form.fields.name : 'Новое' },
                     ]" />

        <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="save">
            <div class="space-y-6 lg:col-span-2">
                <NCard title="Основное">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField label="Название" required :error="form.error('name')">
                            <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                                    @update:model-value="onName" />
                        </NField>

                        <NField label="Код свойства" required :error="form.error('code')"
                                hint="Латиница, цифры, подчёркивание.">
                            <NInput v-model="form.fields.code" class="font-mono uppercase"
                                    :invalid="Boolean(form.error('code'))"
                                    @update:model-value="codeTouched = true" />
                        </NField>

                        <div class="sm:col-span-2">
                            <NField label="Тип данных" required :error="form.error('type')">
                                <NSelect v-model="form.fields.type" :options="typeOptions" />
                            </NField>
                        </div>

                        <div class="sm:col-span-2">
                            <NField label="Подсказка для редактора" :error="form.error('hint')">
                                <NInput v-model="form.fields.hint" placeholder="Короткое пояснение под полем" />
                            </NField>
                        </div>

                        <div class="sm:col-span-2">
                            <NField label="Значение по умолчанию" :error="form.error('default_value')">
                                <NInput v-model="form.fields.default_value" />
                            </NField>
                        </div>
                    </div>
                </NCard>

                <NCard v-if="settingKeys.length" title="Параметры типа">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField v-if="uses('placeholder')" label="Placeholder">
                            <NInput v-model="form.fields.settings.placeholder" />
                        </NField>

                        <NField v-if="uses('max_length')" label="Максимальная длина">
                            <NInput v-model="form.fields.settings.max_length" type="number" min="1" />
                        </NField>

                        <NField v-if="uses('pattern')" label="Регулярное выражение"
                                hint="Проверка формата, например ^[A-Z]{2}[0-9]{4}$">
                            <NInput v-model="form.fields.settings.pattern" class="font-mono" />
                        </NField>

                        <NField v-if="uses('rows')" label="Высота поля (строк)">
                            <NInput v-model="form.fields.settings.rows" type="number" min="1" max="50" />
                        </NField>

                        <NField v-if="uses('min')" label="Минимум">
                            <NInput v-model="form.fields.settings.min" type="number" step="any" />
                        </NField>

                        <NField v-if="uses('max')" label="Максимум">
                            <NInput v-model="form.fields.settings.max" type="number" step="any" />
                        </NField>

                        <NField v-if="uses('step')" label="Шаг">
                            <NInput v-model="form.fields.settings.step" type="number" step="any" />
                        </NField>

                        <NField v-if="uses('suffix')" label="Единица измерения" hint="Показывается справа: кг, ₽, %">
                            <NInput v-model="form.fields.settings.suffix" />
                        </NField>

                        <NField v-if="uses('accept')" label="Допустимые форматы" hint="Например: image/*, .pdf,.docx">
                            <NInput v-model="form.fields.settings.accept" class="font-mono" />
                        </NField>

                        <NField v-if="uses('max_size')" label="Макс. размер, КБ">
                            <NInput v-model="form.fields.settings.max_size" type="number" min="1" />
                        </NField>

                        <NField v-if="uses('max_width')" label="Макс. ширина, px">
                            <NInput v-model="form.fields.settings.max_width" type="number" min="1" />
                        </NField>

                        <NField v-if="uses('max_height')" label="Макс. высота, px">
                            <NInput v-model="form.fields.settings.max_height" type="number" min="1" />
                        </NField>

                        <div v-if="uses('link_iblock_id')" class="sm:col-span-2">
                            <NField label="Связанный инфоблок" hint="Из какого инфоблока выбирать значение."
                                    :error="form.error('settings.link_iblock_id')">
                                <NSelect v-model="form.fields.settings.link_iblock_id" :options="iblocks"
                                         placeholder="Выберите инфоблок" />
                            </NField>
                        </div>
                    </div>
                </NCard>

                <NCard v-if="usesEnums" title="Варианты списка"
                       description="Значения, из которых редактор выбирает при заполнении элемента.">
                    <template #actions>
                        <NButton type="button" size="sm" variant="secondary" icon="plus" @click="addEnum">
                            Добавить вариант
                        </NButton>
                    </template>

                    <div class="space-y-2">
                        <div class="hidden gap-3 px-1 text-xs font-medium text-[var(--text-muted)] sm:grid sm:grid-cols-[1fr_1fr_5rem_5rem_2.5rem]">
                            <span>Значение</span><span>Код</span><span>Сорт.</span><span>По умолч.</span><span></span>
                        </div>

                        <div v-for="(row, index) in enums" :key="index"
                             class="grid gap-3 sm:grid-cols-[1fr_1fr_5rem_5rem_2.5rem] sm:items-center">
                            <NInput v-model="row.value" placeholder="Название варианта" />
                            <NInput v-model="row.code" placeholder="code" class="font-mono" />
                            <NInput v-model="row.sort" type="number" min="0" />

                            <label class="flex items-center justify-center">
                                <input type="checkbox" v-model="row.is_default"
                                       class="size-4 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-brand-500/40">
                            </label>

                            <button type="button"
                                    class="flex items-center justify-center rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                    @click="removeEnum(index)">
                                <NIcon name="trash" size="size-4" />
                            </button>
                        </div>
                    </div>
                </NCard>
            </div>

            <div class="space-y-6">
                <NCard title="Поведение">
                    <div class="space-y-5">
                        <NToggle v-model="form.fields.is_multiple" label="Множественное"
                                 hint="Можно задать несколько значений." />
                        <NToggle v-model="form.fields.is_required" label="Обязательное"
                                 hint="Элемент нельзя сохранить без значения." />
                        <NToggle v-model="form.fields.is_filterable" label="Участвует в фильтре"
                                 hint="Появится в фильтре списка элементов." />
                        <NToggle v-model="form.fields.is_searchable" label="Участвует в поиске" />
                        <NToggle v-model="form.fields.is_shown_in_list" label="Колонка в списке"
                                 hint="Значение будет видно прямо в таблице элементов." />
                        <NToggle v-model="form.fields.with_description" label="Описание"
                                 hint="Выводить поле для описания свойства." />
                        <NToggle v-model="form.fields.is_active" label="Активно" />
                    </div>
                </NCard>

                <NCard title="Сортировка">
                    <NField hint="Порядок поля в форме элемента." :error="form.error('sort')">
                        <NInput v-model="form.fields.sort" type="number" min="0" />
                    </NField>
                </NCard>

                <div class="flex items-center gap-2">
                    <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                    <NButton variant="secondary" size="lg"
                             :to="{ name: 'properties.index', params: { iblock } }">Отмена</NButton>
                </div>
            </div>
        </form>
    </div>
</template>
