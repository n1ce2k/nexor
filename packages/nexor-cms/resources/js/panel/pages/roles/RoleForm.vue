<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NField from '../../components/ui/NField.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import { api } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useUi } from '../../stores/ui';

const props = defineProps({ role: { type: [String, Number], default: null } });

const router = useRouter();
const ui = useUi();

const groups = ref([]);
const selected = ref([]);
const isSystem = ref(false);
const isSuperAdmin = ref(false);
const codeTouched = ref(false);
const ready = ref(false);

const form = useForm({ code: '', name: '', description: '', sort: 500 });

const isEdit = computed(() => Boolean(props.role));

function onName(value) {
    form.fields.name = value;

    if (!codeTouched.value && !isEdit.value) {
        form.fields.code = slugify(value);
    }
}

function toggleGroup(group, checked) {
    const ids = group.items.map((permission) => permission.id);

    selected.value = checked
        ? [...new Set([...selected.value, ...ids])]
        : selected.value.filter((id) => !ids.includes(id));
}

function groupChecked(group) {
    return group.items.every((permission) => selected.value.includes(permission.id));
}

async function save() {
    const data = await form.submit(
        isEdit.value ? 'put' : 'post',
        isEdit.value ? `roles/${props.role}` : 'roles',
        { body: { ...form.fields, permissions: selected.value } },
    );

    if (data) {
        router.push({ name: 'roles.index' });
    }
}

onMounted(async () => {
    try {
        // The permission catalogue travels with the list endpoint.
        const list = await api.get('roles', { per_page: 1 });
        const permissions = list.meta?.permissions ?? [];

        const byGroup = {};
        permissions.forEach((permission) => {
            const label = permission.group_label ?? permission.group;

            (byGroup[label] ??= []).push(permission);
        });

        groups.value = Object.entries(byGroup).map(([label, items]) => ({ label, items }));

        if (isEdit.value) {
            const data = await api.get(`roles/${props.role}`);

            form.fill({
                code: data.data.code,
                name: data.data.name,
                description: data.data.description ?? '',
                sort: data.data.sort,
            });

            selected.value = data.data.permission_ids ?? [];
            isSystem.value = data.data.is_system;
            isSuperAdmin.value = data.data.is_super_admin;
            codeTouched.value = true;
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
        <NPageHeader :title="isEdit ? form.fields.name || 'Роль' : 'Новая роль'"
                     :back="{ name: 'roles.index' }"
                     description="Отметьте права, которые получают пользователи с этой ролью."
                     :breadcrumbs="[
                         { label: 'Роли и права', to: { name: 'roles.index' } },
                         { label: isEdit ? form.fields.name : 'Новая роль' },
                     ]" />

        <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="save">
            <div class="space-y-6 lg:col-span-2">
                <p v-if="isSuperAdmin"
                   class="flex items-start gap-2 rounded-xl bg-violet-50 p-4 text-sm text-violet-800 dark:bg-violet-500/10 dark:text-violet-300">
                    <NIcon name="shield" size="size-4 mt-0.5 shrink-0" />
                    Роль супер-администратора всегда получает все права, включая права новых инфоблоков.
                    Список ниже показан для справки.
                </p>

                <NCard v-for="group in groups" :key="group.label" :title="group.label">
                    <template #actions>
                        <label class="flex cursor-pointer items-center gap-2 text-xs text-[var(--text-muted)]">
                            <input type="checkbox" :checked="groupChecked(group)" :disabled="isSuperAdmin"
                                   class="size-3.5 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-brand-500/40"
                                   @change="toggleGroup(group, $event.target.checked)">
                            выбрать все
                        </label>
                    </template>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label v-for="permission in group.items" :key="permission.id"
                               class="flex cursor-pointer items-start gap-2.5 select-none">
                            <input type="checkbox" :value="permission.id" v-model="selected" :disabled="isSuperAdmin"
                                   class="mt-0.5 size-4 shrink-0 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-2 focus:ring-brand-500/40">
                            <span class="text-sm">
                                <span class="font-medium text-[var(--text-strong)]">{{ permission.name }}</span>
                                <span class="mt-0.5 block font-mono text-xs font-normal text-[var(--text-muted)]">
                                    {{ permission.code }}
                                </span>
                            </span>
                        </label>
                    </div>
                </NCard>
            </div>

            <div class="space-y-6">
                <NCard title="Параметры роли">
                    <div class="space-y-5">
                        <NField label="Название" required :error="form.error('name')">
                            <NInput :model-value="form.fields.name" :invalid="Boolean(form.error('name'))"
                                    @update:model-value="onName" />
                        </NField>

                        <NField label="Символьный код" :required="!isSystem"
                                hint="Латиница, цифры, дефис. Используется в коде проверок прав."
                                :error="form.error('code')">
                            <NInput v-model="form.fields.code" class="font-mono" :disabled="isSystem"
                                    :invalid="Boolean(form.error('code'))"
                                    @update:model-value="codeTouched = true" />
                        </NField>

                        <NField label="Описание" :error="form.error('description')">
                            <textarea v-model="form.fields.description" rows="3" class="field-input resize-y"></textarea>
                        </NField>

                        <NField label="Сортировка" :error="form.error('sort')">
                            <NInput v-model="form.fields.sort" type="number" min="0" />
                        </NField>
                    </div>
                </NCard>

                <div class="flex items-center gap-2">
                    <NButton type="submit" size="lg" :loading="form.busy.value">Сохранить</NButton>
                    <NButton variant="secondary" size="lg" :to="{ name: 'roles.index' }">Отмена</NButton>
                </div>
            </div>
        </form>
    </div>
</template>
