<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NHtmlInput from '../../components/ui/NHtmlInput.vue';
import NInput from '../../components/ui/NInput.vue';
import NModal from '../../components/ui/NModal.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useUi } from '../../stores/ui';

const props = defineProps({ agreement: { type: [String, Number], default: null } });

const router = useRouter();
const ui = useUi();

const ready = ref(false);
const codeTouched = ref(false);
const previewOpen = ref(false);
const previewChecked = ref(false);
const siteUrl = ref(null);

const form = useForm({
    code: '',
    name: '',
    label: 'Я даю согласие на обработку персональных данных',
    link_text: 'обработку персональных данных',
    text: '',
    text_type: 'html',
    is_active: true,
    sort: 500,
});

const isEdit = computed(() => Boolean(props.agreement));

/** Та же разбивка подписи, что и на сервере (Agreement::labelParts). */
const labelParts = computed(() => {
    const label = form.fields.label ?? '';
    const link = (form.fields.link_text ?? '').trim();

    if (link === '') {
        return { before: '', link: label, after: '' };
    }

    const position = label.indexOf(link);

    if (position === -1) {
        return { before: `${label.trimEnd()} `, link, after: '' };
    }

    return {
        before: label.slice(0, position),
        link,
        after: label.slice(position + link.length),
    };
});

const linkMissing = computed(() => {
    const link = (form.fields.link_text ?? '').trim();

    return link !== '' && !(form.fields.label ?? '').includes(link);
});

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value && !isEdit.value) {
        form.fields.code = slugify(value);
    }
}

async function save() {
    const data = await form.submit(
        isEdit.value ? 'put' : 'post',
        isEdit.value ? `agreements/${props.agreement}` : 'agreements',
    );

    if (data) {
        router.push({ name: 'agreements.index' });
    }
}

onMounted(async () => {
    if (isEdit.value) {
        try {
            const { data } = await api.get(`agreements/${props.agreement}`);

            form.fill({
                code: data.code,
                name: data.name,
                label: data.label,
                link_text: data.link_text ?? '',
                text: data.text ?? '',
                text_type: data.text_type,
                is_active: data.is_active,
                sort: data.sort,
            });

            siteUrl.value = data.url;
            codeTouched.value = true;
        } catch (error) {
            ui.notifyError(error);
        }
    }

    ready.value = true;
});
</script>

<template>
    <div v-if="ready">
        <NPageHeader :title="isEdit ? form.fields.name || 'Соглашение' : 'Новое соглашение'"
                     :back="{ name: 'agreements.index' }"
                     :breadcrumbs="[
                         { label: 'Соглашения', to: { name: 'agreements.index' } },
                         { label: isEdit ? form.fields.name : 'Новое' },
                     ]">
            <template v-if="siteUrl" #actions>
                <NButton variant="secondary" icon="eye" :href="siteUrl" target="_blank">Страница на сайте</NButton>
            </template>
        </NPageHeader>

        <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="save">
            <div class="space-y-6 lg:col-span-2">
                <NCard title="Основное">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField label="Название" required :error="form.error('name')">
                            <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                                    placeholder="Согласие на обработку персональных данных"
                                    @update:model-value="onName" />
                        </NField>

                        <NField label="Символьный код" required :error="form.error('code')"
                                hint="Адрес страницы: /agreement/код">
                            <NInput v-model="form.fields.code" class="font-mono" :invalid="Boolean(form.error('code'))"
                                    @update:model-value="codeTouched = true" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="Галочка у формы" description="Вид соглашения в форме: галочка и текст рядом с ней.">
                    <div class="grid gap-5">
                        <NField label="Текст у галочки" required :error="form.error('label')">
                            <NInput v-model="form.fields.label" :invalid="Boolean(form.error('label'))" />
                        </NField>

                        <NField label="Текст ссылки" :error="form.error('link_text')"
                                :hint="linkMissing
                                    ? 'Этой фразы нет в тексте у галочки — ссылка допишется в конец'
                                    : 'Часть текста у галочки, по клику на которую открывается соглашение. Пусто — ссылкой будет весь текст'">
                            <NInput v-model="form.fields.link_text" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="Текст соглашения">
                    <div class="space-y-5">
                        <NField label="Формат" :error="form.error('text_type')">
                            <NSelect v-model="form.fields.text_type"
                                     :options="[{ value: 'html', label: 'HTML (визуальный редактор)' }, { value: 'text', label: 'Обычный текст' }]" />
                        </NField>

                        <NField :error="form.error('text')">
                            <NHtmlInput v-if="form.fields.text_type === 'html'" v-model="form.fields.text" rows="26rem" />

                            <textarea v-else v-model="form.fields.text" rows="18"
                                      class="field-input resize-y"
                                      placeholder="Переносы строк сохранятся"></textarea>
                        </NField>
                    </div>
                </NCard>
            </div>

            <div class="space-y-6">
                <NCard title="Параметры">
                    <div class="space-y-5">
                        <NToggle v-model="form.fields.is_active" label="Активно"
                                 hint="Выключенное соглашение не показывается в формах" />

                        <NField label="Сортировка" :error="form.error('sort')">
                            <NInput v-model="form.fields.sort" type="number" min="0" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="Как это выглядит">
                    <label class="flex cursor-pointer items-start gap-2.5 text-sm">
                        <input v-model="previewChecked" type="checkbox" class="mt-0.5 size-4 rounded">
                        <span>
                            {{ labelParts.before }}<a href="#" class="text-brand-600 underline dark:text-brand-400"
                               @click.prevent="previewOpen = true">{{ labelParts.link }}</a>{{ labelParts.after }}
                        </span>
                    </label>
                </NCard>

                <div class="flex items-center gap-2">
                    <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                    <NButton variant="secondary" size="lg" :to="{ name: 'agreements.index' }">Отмена</NButton>
                </div>
            </div>
        </form>

        <NModal v-model="previewOpen" :title="form.fields.name || 'Соглашение'" max-width="max-w-2xl">
            <div v-if="form.fields.text_type === 'html'" class="prose prose-sm max-w-none dark:prose-invert"
                 v-html="form.fields.text"></div>
            <p v-else class="text-sm whitespace-pre-line">{{ form.fields.text }}</p>
        </NModal>
    </div>
</template>
