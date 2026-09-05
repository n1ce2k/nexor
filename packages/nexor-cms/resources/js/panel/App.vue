<script setup>
import { onMounted } from 'vue';
import AdminLayout from './layouts/AdminLayout.vue';
import NConfirm from './components/ui/NConfirm.vue';
import NToasts from './components/ui/NToasts.vue';
import { useSession } from './stores/session';
import { useUi } from './stores/ui';

const session = useSession();
const ui = useUi();

onMounted(() => {
    ui.applyTheme();
    session.load();
});
</script>

<template>
    <div v-if="!session.ready && !session.error" class="flex min-h-screen items-center justify-center">
        <svg class="size-8 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z" />
        </svg>
    </div>

    <div v-else-if="session.error" class="flex min-h-screen items-center justify-center p-6">
        <div class="surface max-w-md rounded-2xl border p-6 text-center shadow-sm">
            <h1 class="text-lg font-semibold text-[var(--text-strong)]">Панель не загрузилась</h1>
            <p class="mt-2 text-sm text-[var(--text-muted)]">{{ session.error.message }}</p>
            <button type="button" class="mt-5 inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700"
                    @click="session.load()">
                Попробовать снова
            </button>
        </div>
    </div>

    <AdminLayout v-else />

    <NToasts />
    <NConfirm />
</template>
