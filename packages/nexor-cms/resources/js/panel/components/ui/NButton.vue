<script setup>
import { computed } from 'vue';
import NIcon from './NIcon.vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    to: { type: [String, Object], default: null },
    href: { type: String, default: null },
    icon: { type: String, default: null },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const variants = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600 shadow-sm',
    secondary: 'border border-[var(--surface-border-strong)] bg-[var(--surface-panel)] text-[var(--text-base)] hover:bg-[var(--surface-muted)]',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600 shadow-sm',
    ghost: 'text-[var(--text-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]',
    link: 'text-brand-600 hover:text-brand-700 hover:underline dark:text-brand-400',
};

const sizes = {
    sm: 'gap-1.5 px-2.5 py-1.5 text-xs',
    md: 'gap-2 px-3.5 py-2 text-sm',
    lg: 'gap-2 px-4 py-2.5 text-sm',
    icon: 'p-2',
};

const classes = computed(() => [
    'inline-flex items-center justify-center rounded-lg font-medium transition',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
    'disabled:cursor-not-allowed disabled:opacity-50 button-primary',
    variants[props.variant] ?? variants.primary,
    sizes[props.size] ?? sizes.md,
]);

const tag = computed(() => (props.to ? 'router-link' : props.href ? 'a' : 'button'));
</script>

<template>
    <component :is="tag" :to="to" :href="href" :class="classes"
               :type="tag === 'button' ? type : undefined"
               :disabled="tag === 'button' ? disabled || loading : undefined">
        <svg v-if="loading" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z" />
        </svg>
        <NIcon v-else-if="icon" :name="icon" size="size-4" />
        <slot />
    </component>
</template>
