<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NToggle from '../../components/ui/NToggle.vue';
import PropertyField from '../../components/fields/PropertyField.vue';
import { api, toFormData } from '../../api';
import { useForm } from '../../composables/useForm';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

const props = defineProps({ user: { type: [String, Number], default: null } });

const router = useRouter();
const session = useSession();
const ui = useUi();

const roles = ref([]);
const avatar = ref(null);
const avatarRemoved = ref(false);
const existingAvatar = ref(null);
const ready = ref(false);

/** Свои поля пользователя, заведённые в разделе «Поля пользователей». */
const fields = ref([]);
const fieldValues = ref({});

const isFileField = (field) => field.type === 'file' || field.type === 'image';

/** Пустое значение поля: у файлов своя форма с уже загруженными и добавленными. */
function emptyValue(field) {
    if (isFileField(field)) {
        return { stored: [], remove: [], added: [] };
    }

    return field.is_multiple ? [] : (field.type === 'boolean' ? false : null);
}

function fillFieldValues(saved = {}) {
    fieldValues.value = Object.fromEntries(fields.value.map((field) => {
        const value = saved[field.code];

        if (isFileField(field)) {
            return [field.code, { stored: value ?? [], remove: [], added: [] }];
        }

        return [field.code, value ?? emptyValue(field)];
    }));
}

const form = useForm({
    name: '',
    login: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    is_active: true,
    roles: [],
});

const isEdit = computed(() => Boolean(props.user));

/** Editing yourself must not be able to remove your own access. */
const isSelf = computed(() => isEdit.value && String(props.user) === String(session.user?.id));

/** Значения своих полей — теми же именами, что ждёт сервер. */
function appendFields(payload) {
    fields.value.forEach((field) => {
        const value = fieldValues.value[field.code];

        if (isFileField(field)) {
            (value?.remove ?? []).forEach((id) => payload.append(`field_remove[${field.code}][]`, id));
            (value?.added ?? []).forEach((file) => {
                payload.append(`field_files[${field.code}]${field.is_multiple ? '[]' : ''}`, file);
            });

            return;
        }

        if (field.is_multiple) {
            const rows = (Array.isArray(value) ? value : []).filter((item) => item !== null && item !== '');

            if (rows.length === 0) {
                payload.append(`fields[${field.code}][]`, '');
            }

            rows.forEach((item) => payload.append(`fields[${field.code}][]`, item));

            return;
        }

        const scalar = field.type === 'boolean' ? (value ? '1' : '0') : value;

        payload.append(`fields[${field.code}]`, scalar ?? '');
    });
}

async function save() {
    const payload = toFormData({ ...form.fields });

    appendFields(payload);

    if (avatar.value) {
        payload.append('avatar', avatar.value);
    }

    if (avatarRemoved.value) {
        payload.append('avatar_remove', '1');
    }

    if (isEdit.value) {
        payload.append('_method', 'PUT');
    }

    const data = await form.submit('post', isEdit.value ? `users/${props.user}` : 'users', {
        body: payload,
        files: true,
    });

    if (data) {
        router.push({ name: 'users.index' });
    }
}

function pickAvatar(event) {
    avatar.value = event.target.files?.[0] ?? null;
    avatarRemoved.value = false;
}

onMounted(async () => {
    try {
        const [roleList, schema] = await Promise.all([
            api.get('roles', { per_page: 200 }),
            api.get('users/schema'),
        ]);

        roles.value = roleList.data;
        fields.value = schema.fields;
        fillFieldValues();

        if (isEdit.value) {
            const data = await api.get(`users/${props.user}`);

            form.fill({
                name: data.data.name,
                login: data.data.login ?? '',
                email: data.data.email,
                phone: data.data.phone ?? '',
                password: '',
                password_confirmation: '',
                is_active: data.data.is_active,
                roles: data.data.role_ids ?? [],
            });

            fillFieldValues(data.data.fields ?? {});
            existingAvatar.value = data.data.avatar_url;
        }
    } catch (error) {
        ui.notifyError(error);
    } finally {
        ready.value = true;
    }
});
</script>

<template>
    <div v-if="ready">
        <NPageHeader :title="isEdit ? form.fields.name || 'Пользователь' : 'Новый пользователь'"
                     :back="{ name: 'users.index' }"
                     :breadcrumbs="[
                         { label: 'Пользователи', to: { name: 'users.index' } },
                         { label: isEdit ? form.fields.name : 'Новый' },
                     ]" />

        <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="save">
            <div class="space-y-6 lg:col-span-2">
                <NCard title="Основное">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <NField label="Имя" required :error="form.error('name')">
                                <NInput v-model="form.fields.name" :invalid="Boolean(form.error('name'))" />
                            </NField>
                        </div>

                        <NField label="Логин" required hint="Им можно входить вместо e-mail"
                                :error="form.error('login')">
                            <NInput v-model="form.fields.login" class="font-mono"
                                    :invalid="Boolean(form.error('login'))" />
                        </NField>

                        <NField label="E-mail" required :error="form.error('email')">
                            <NInput v-model="form.fields.email" type="email"
                                    :invalid="Boolean(form.error('email'))" />
                        </NField>

                        <NField label="Телефон" :error="form.error('phone')">
                            <NInput v-model="form.fields.phone" placeholder="+7 900 000-00-00" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="Пароль"
                       :description="isEdit ? 'Оставьте поля пустыми, чтобы не менять пароль.' : null">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <NField label="Пароль" :required="!isEdit" :error="form.error('password')">
                            <NInput v-model="form.fields.password" type="password"
                                    :invalid="Boolean(form.error('password'))" />
                        </NField>

                        <NField label="Повторите пароль" :required="!isEdit">
                            <NInput v-model="form.fields.password_confirmation" type="password" />
                        </NField>
                    </div>
                </NCard>

                <NCard title="Роли" description="Права доступа определяются набором ролей.">
                    <p v-if="isSelf"
                       class="mb-4 flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-400">
                        <NIcon name="info" size="size-4 mt-0.5 shrink-0" />
                        Собственные роли и статус изменить нельзя — это защита от потери доступа.
                    </p>

                    <div class="space-y-3">
                        <label v-for="role in roles" :key="role.id"
                               class="flex cursor-pointer items-start gap-2.5 select-none">
                            <input type="checkbox" :value="role.id" v-model="form.fields.roles" :disabled="isSelf"
                                   class="mt-0.5 size-4 shrink-0 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-2 focus:ring-brand-500/40">
                            <span class="text-sm">
                                <span class="font-medium text-[var(--text-strong)]">{{ role.name }}</span>
                                <span v-if="role.description"
                                      class="mt-0.5 block text-xs font-normal text-[var(--text-muted)]">
                                    {{ role.description }}
                                </span>
                            </span>
                        </label>
                    </div>
                </NCard>
            </div>

            <div class="space-y-6">
                <NCard title="Статус">
                    <NToggle v-model="form.fields.is_active" label="Учётная запись активна"
                             hint="Заблокированный пользователь не сможет войти." :disabled="isSelf" />
                </NCard>

                <NCard title="Аватар">
                    <div class="flex items-center gap-3">
                        <img v-if="existingAvatar && !avatarRemoved && !avatar" :src="existingAvatar" alt=""
                             class="size-14 rounded-full object-cover">

                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--surface-border-strong)] px-3 py-2 text-sm font-medium text-[var(--text-base)] transition hover:bg-[var(--surface-muted)]">
                            <NIcon name="upload" size="size-4" />
                            Выбрать
                            <input type="file" accept="image/*" class="sr-only" @change="pickAvatar">
                        </label>

                        <span class="truncate text-xs text-[var(--text-muted)]">{{ avatar?.name ?? '' }}</span>

                        <button v-if="existingAvatar || avatar" type="button"
                                class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"
                                @click="avatar = null; avatarRemoved = true">
                            убрать
                        </button>
                    </div>
                </NCard>

                <NCard v-if="fields.length" title="Дополнительно"
                       description="Свои поля пользователей — настраиваются в разделе «Поля пользователей».">
                    <div class="grid gap-5">
                        <PropertyField v-for="field in fields" :key="field.id" :property="field"
                                       :model-value="fieldValues[field.code]"
                                       :error="form.error(`fields.${field.code}`) || form.error(`field_files.${field.code}`)"
                                       @update:model-value="fieldValues[field.code] = $event" />
                    </div>
                </NCard>

                <div class="flex items-center gap-2">
                    <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                    <NButton variant="secondary" size="lg" :to="{ name: 'users.index' }">Отмена</NButton>
                </div>
            </div>
        </form>
    </div>
</template>
