<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import NBadge from '../../components/ui/NBadge.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useUi } from '../../stores/ui';

const props = defineProps({ template: { type: [String, Number], default: null } });

const router = useRouter();
const ui = useUi();

const codeTouched = ref(false);
const ready = ref(false);

const form = useForm({
    code: '',
    name: '',
    description: '',
    from: '',
    to: '',
    reply_to: '',
    bcc: '',
    subject: '',
    body: '',
    body_type: 'html',
    is_active: true,
    sort: 500,
});

const isEdit = computed(() => Boolean(props.template));

/** Tokens the template currently uses, so the editor can show them back. */
const placeholders = computed(() => {
    const found = `${form.fields.subject} ${form.fields.body}`.match(/#([A-Z0-9_]+)#/g) ?? [];

    return [...new Set(found)];
});

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value && !isEdit.value) {
        form.fields.code = slugify(value).toUpperCase().replace(/-/g, '_');
    }
}

async function save() {
    const data = await form.submit(
        isEdit.value ? 'put' : 'post',
        isEdit.value ? `mail-templates/${props.template}` : 'mail-templates',
    );

    if (data) {
        router.push({ name: 'mail.templates' });
    }
}

onMounted(async () => {
    if (isEdit.value) {
        try {
            const data = await api.get(`mail-templates/${props.template}`);

            form.fill({
                code: data.data.code,
                name: data.data.name,
                description: data.data.description ?? '',
                from: data.data.from ?? '',
                to: data.data.to ?? '',
                reply_to: data.data.reply_to ?? '',
                bcc: data.data.bcc ?? '',
                subject: data.data.subject,
                body: data.data.body ?? '',
                body_type: data.data.body_type,
                is_active: data.data.is_active,
                sort: data.data.sort,
            });

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
        <NPageHeader :title="isEdit ? form.fields.name || 'Шаблон' : 'Новый почтовый шаблон'"
                     :back="{ name: 'mail.templates' }"
                     :breadcrumbs="[
                         { label: 'Почтовые шаблоны', to: { name: 'mail.templates' } },
                         { label: isEdit ? form.fields.name : 'Новый' },
                     ]" />

        <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="save">
            <div class="space-y-6 lg:col-span-2">
                <NCard title="Основное">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField label="Название" required :error="form.error('name')">
                            <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                                    @update:model-value="onName" />
                        </NField>

                        <NField label="Код" required hint="Заглавная латиница: ORDER_CREATED"
                                :error="form.error('code')">
                            <NInput v-model="form.fields.code" class="font-mono uppercase"
                                    :invalid="Boolean(form.error('code'))"
                                    @update:model-value="codeTouched = true" />
                        </NField>

                        <div class="sm:col-span-2">
                            <NField label="Описание" :error="form.error('description')">
                                <NInput v-model="form.fields.description"
                                        placeholder="Когда это письмо отправляется" />
                            </NField>
                        </div>
                    </div>
                </NCard>

                <NCard title="Адреса" description="Можно писать подстановки: #EMAIL#, #SITE_EMAIL#.">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField label="От кого" :error="form.error('from')">
                            <NInput v-model="form.fields.from" placeholder="#SITE_EMAIL#" />
                        </NField>

                        <NField label="Кому" :error="form.error('to')">
                            <NInput v-model="form.fields.to" placeholder="#EMAIL#" />
                        </NField>

                        <NField label="Адрес для ответа" :error="form.error('reply_to')">
                            <NInput v-model="form.fields.reply_to" />
                        </NField>

                        <NField label="Скрытая копия" :error="form.error('bcc')">
                            <NInput v-model="form.fields.bcc" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="Письмо">
                    <div class="space-y-5">
                        <NField label="Тема" required :error="form.error('subject')">
                            <NInput v-model="form.fields.subject" :invalid="Boolean(form.error('subject'))" />
                        </NField>

                        <NField label="Формат" :error="form.error('body_type')">
                            <NSelect v-model="form.fields.body_type"
                                     :options="[{ value: 'html', label: 'HTML' }, { value: 'text', label: 'Обычный текст' }]" />
                        </NField>

                        <NField label="Текст письма" :error="form.error('body')">
                            <textarea v-model="form.fields.body" rows="16"
                                      :class="['field-input resize-y', form.fields.body_type === 'html' && 'font-mono text-xs']"></textarea>
                        </NField>
                    </div>
                </NCard>
            </div>

            <div class="space-y-6">
                <NCard title="Параметры">
                    <div class="space-y-5">
                        <NToggle v-model="form.fields.is_active" label="Активен" />

                        <NField label="Сортировка" :error="form.error('sort')">
                            <NInput v-model="form.fields.sort" type="number" min="0" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="Подстановки"
                       description="Найдены в теме и тексте. При отправке заменяются переданными значениями.">
                    <div v-if="placeholders.length" class="flex flex-wrap gap-1.5">
                        <NBadge v-for="token in placeholders" :key="token" color="violet">{{ token }}</NBadge>
                    </div>
                    <p v-else class="text-sm text-[var(--text-muted)]">
                        Подстановок пока нет. Напишите в тексте <code class="font-mono">#NAME#</code> — она появится здесь.
                    </p>
                </NCard>

                <div class="flex items-center gap-2">
                    <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                    <NButton variant="secondary" size="lg" :to="{ name: 'mail.templates' }">Отмена</NButton>
                </div>
            </div>
        </form>
    </div>
</template>
