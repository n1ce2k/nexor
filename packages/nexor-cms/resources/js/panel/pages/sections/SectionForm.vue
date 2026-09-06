<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NHtmlInput from '../../components/ui/NHtmlInput.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NTabs from '../../components/ui/NTabs.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api, toFormData } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * One section of an infoblock, on the same tabbed form the elements use.
 *
 * The tabs are fixed here: a section has no properties of its own, so there is
 * nothing for an operator to rearrange.
 */
const props = defineProps({
    iblock: { type: [String, Number], required: true },
    section: { type: [String, Number], default: null },
});

const route = useRoute();
const router = useRouter();
const session = useSession();
const ui = useUi();

const parents = ref([]);
const codeTouched = ref(false);
const ready = ref(false);

const picture = ref(null);
const pictureUrl = ref(null);
const removePicture = ref(false);

const activeTab = ref('main');

const form = useForm({
    parent_id: null,
    code: '',
    name: '',
    description: '',
    is_active: true,
    sort: 500,
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
});

const isEdit = computed(() => Boolean(props.section));

const info = computed(() => session.iblock(props.iblock));

/** A section may not be moved under itself or under one of its own children. */
const parentOptions = computed(() => parents.value
    .filter((candidate) => !isEdit.value || !isDescendant(candidate))
    .map((candidate) => ({ value: candidate.id, label: candidate.indented_name })));

function isDescendant(candidate) {
    const id = Number(props.section);

    return candidate.id === id || (candidate.path ?? '').split('/').includes(String(id));
}

const errorsIn = {
    main: ['name', 'code', 'parent_id', 'is_active', 'sort', 'picture'],
    description: ['description'],
    seo: ['meta_title', 'meta_description', 'meta_keywords'],
};

const tabs = computed(() => [
    { key: 'main', label: 'Основное' },
    { key: 'description', label: 'Описание' },
    { key: 'seo', label: 'SEO' },
].map((tab) => ({ ...tab, mark: errorsIn[tab.key].some((field) => Boolean(form.error(field))) })));

// A failed save may put the error on a tab the operator is not looking at.
watch(() => form.errors.value, () => {
    const failed = tabs.value.find((tab) => tab.mark);

    if (failed) {
        activeTab.value = failed.key;
    }
});

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value && !isEdit.value) {
        form.fields.code = slugify(value);
    }
}

function pickPicture(event) {
    picture.value = event.target.files?.[0] ?? null;
    removePicture.value = false;
}

function clearPicture() {
    picture.value = null;
    removePicture.value = true;
    pictureUrl.value = null;
}

async function save() {
    const body = toFormData({ ...form.fields });

    if (picture.value) {
        body.append('picture', picture.value);
    }

    if (removePicture.value) {
        body.append('picture_remove', '1');
    }

    const data = await form.submit(
        isEdit.value ? 'put' : 'post',
        isEdit.value
            ? `iblocks/${props.iblock}/sections/${props.section}`
            : `iblocks/${props.iblock}/sections`,
        { body, files: true },
    );

    if (data) {
        router.push({ name: 'sections.index', params: { iblock: props.iblock } });
    }
}

onMounted(async () => {
    try {
        const list = await api.get(`iblocks/${props.iblock}/sections`);

        parents.value = list.data;

        if (isEdit.value) {
            const data = await api.get(`iblocks/${props.iblock}/sections/${props.section}`);
            const section = data.data;

            form.fill({
                parent_id: section.parent_id,
                code: section.code ?? '',
                name: section.name,
                description: section.description ?? '',
                is_active: section.is_active,
                sort: section.sort,
                meta_title: section.meta_title ?? '',
                meta_description: section.meta_description ?? '',
                meta_keywords: section.meta_keywords ?? '',
            });

            pictureUrl.value = section.picture_url;
            codeTouched.value = true;
        } else if (route.query.parent) {
            // «Добавить вложенный раздел» arrives with the parent already chosen.
            form.fields.parent_id = Number(route.query.parent);
        }
    } catch (error) {
        ui.notifyError(error);
    } finally {
        ready.value = true;
    }
});
</script>

<template>
    <div>
        <NPageHeader :title="isEdit ? form.fields.name || 'Раздел' : 'Новый раздел'"
                     :back="{ name: 'sections.index', params: { iblock } }"
                     :description="info ? `Инфоблок «${info.name}»` : null"
                     :breadcrumbs="[
                         { label: info?.name ?? '', to: { name: 'elements.index', params: { iblock } } },
                         { label: 'Разделы', to: { name: 'sections.index', params: { iblock } } },
                         { label: isEdit ? form.fields.name : 'Новый раздел' },
                     ]" />

        <form v-if="ready" class="space-y-6" @submit.prevent="save">
            <NCard :padding="false">
                <div class="px-5 pt-1">
                    <NTabs v-model="activeTab" :tabs="tabs" />
                </div>

                <div v-show="activeTab === 'main'" class="grid gap-5 p-5 sm:grid-cols-2">
                    <NField label="Название" required :error="form.error('name')">
                        <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                                @update:model-value="onName" />
                    </NField>

                    <NField label="Символьный код" hint="Используется в адресе раздела." :error="form.error('code')">
                        <NInput v-model="form.fields.code" class="font-mono"
                                :invalid="Boolean(form.error('code'))"
                                @update:model-value="codeTouched = true" />
                    </NField>

                    <NField label="Родительский раздел" hint="Пусто — раздел верхнего уровня."
                            :error="form.error('parent_id')">
                        <NSelect v-model="form.fields.parent_id" :options="parentOptions"
                                 placeholder="— верхний уровень —" />
                    </NField>

                    <NField label="Сортировка" :error="form.error('sort')">
                        <NInput v-model="form.fields.sort" type="number" min="0" />
                    </NField>

                    <NField label="Активность">
                        <NToggle v-model="form.fields.is_active" label="Раздел виден на сайте" />
                    </NField>

                    <NField label="Картинка" :error="form.error('picture')">
                        <div class="flex items-center gap-3">
                            <img v-if="pictureUrl && !picture" :src="pictureUrl" alt=""
                                 class="size-12 shrink-0 rounded object-cover">

                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--surface-border-strong)] px-3 py-2 text-sm font-medium text-[var(--text-base)] transition hover:bg-[var(--surface-muted)]">
                                <NIcon name="upload" size="size-4" />
                                Выбрать файл
                                <input type="file" accept="image/*" class="sr-only" @change="pickPicture">
                            </label>

                            <span class="min-w-0 truncate text-xs text-[var(--text-muted)]">
                                {{ picture?.name ?? (pictureUrl ? 'загружена' : 'файл не выбран') }}
                            </span>

                            <button v-if="picture || pictureUrl" type="button"
                                    class="shrink-0 text-xs font-medium text-red-600 hover:underline dark:text-red-400"
                                    @click="clearPicture">
                                убрать
                            </button>
                        </div>
                    </NField>
                </div>

                <div v-show="activeTab === 'description'" class="p-5">
                    <NField label="Описание раздела" :error="form.error('description')">
                        <NHtmlInput v-model="form.fields.description" rows="20rem" />
                    </NField>
                </div>

                <div v-show="activeTab === 'seo'" class="grid gap-5 p-5">
                    <NField label="Заголовок страницы (title)" :error="form.error('meta_title')">
                        <NInput v-model="form.fields.meta_title" />
                    </NField>

                    <NField label="Описание (description)" :error="form.error('meta_description')">
                        <textarea v-model="form.fields.meta_description" rows="2"
                                  class="field-input resize-y"></textarea>
                    </NField>

                    <NField label="Ключевые слова" :error="form.error('meta_keywords')">
                        <NInput v-model="form.fields.meta_keywords" />
                    </NField>
                </div>
            </NCard>

            <div class="flex items-center gap-2">
                <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                <NButton variant="secondary" size="lg"
                         :to="{ name: 'sections.index', params: { iblock } }">Отмена</NButton>
            </div>
        </form>
    </div>
</template>
