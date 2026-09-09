<script setup>
import Draggable from 'vuedraggable';
import NBadge from '../../components/ui/NBadge.vue';
import NIcon from '../../components/ui/NIcon.vue';

/**
 * Одна ветка дерева пунктов. Файл рекурсивно подключает сам себя.
 *
 * Перетаскивание и кнопки живут рядом намеренно: мышью быстрее, но клавиатурой
 * и на тач-экране кнопки надёжнее, а «вложить/вынуть» ими выражается точнее.
 */
defineProps({
    // Пункты этого уровня; правится на месте, поэтому именно массив, а не копия
    items: { type: Array, required: true },
    level: { type: Number, default: 0 },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['change', 'edit', 'remove', 'move', 'nest']);

/** Пункт-разделитель и динамическая ветка детей не принимают. */
function group(node) {
    return { name: 'menu-items', pull: true, put: node.accepts_children !== false };
}

const kindIcon = {
    link: 'chevron-right',
    page: 'document',
    section: 'folder',
    sections: 'layers',
    heading: 'menu',
    divider: 'menu',
};
</script>

<template>
    <Draggable :list="items" :group="{ name: 'menu-items' }" item-key="id" handle=".menu-handle"
               :disabled="disabled" ghost-class="opacity-40" :animation="150"
               :class="['space-y-1', level > 0 && 'mt-1 ml-6 border-l border-[var(--surface-border)] pl-3']"
               @change="emit('change')">
        <template #item="{ element: node, index }">
            <div>
                <div class="surface flex items-center gap-2 rounded-lg border px-2 py-1.5">
                    <span class="menu-handle cursor-grab text-[var(--text-faint)] active:cursor-grabbing"
                          title="Перетащите, чтобы переставить">
                        <NIcon name="grip" size="size-4" />
                    </span>

                    <NIcon :name="kindIcon[node.type] ?? 'chevron-right'" size="size-4"
                           class="shrink-0 text-[var(--text-muted)]" />

                    <button type="button" class="min-w-0 flex-1 truncate text-left text-sm text-[var(--text-strong)]"
                            @click="emit('edit', node)">
                        {{ node.display_title }}
                    </button>

                    <NBadge v-if="node.type !== 'link'" color="gray">{{ node.type_label }}</NBadge>
                    <NBadge v-if="!node.is_active" color="gray">скрыт</NBadge>

                    <div class="flex shrink-0 items-center gap-0.5">
                        <button type="button" title="Выше" :disabled="index === 0"
                                class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)] disabled:opacity-30"
                                @click="emit('move', { node, delta: -1 })">
                            <NIcon name="chevron-up" size="size-3.5" />
                        </button>

                        <button type="button" title="Ниже" :disabled="index === items.length - 1"
                                class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)] disabled:opacity-30"
                                @click="emit('move', { node, delta: 1 })">
                            <NIcon name="chevron-down" size="size-3.5" />
                        </button>

                        <button type="button" title="Вложить в пункт выше" :disabled="index === 0"
                                class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)] disabled:opacity-30"
                                @click="emit('nest', { node, into: true })">
                            <NIcon name="chevron-right" size="size-3.5" />
                        </button>

                        <button type="button" title="Вынуть на уровень выше" :disabled="level === 0"
                                class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)] disabled:opacity-30"
                                @click="emit('nest', { node, into: false })">
                            <NIcon name="chevron-left" size="size-3.5" />
                        </button>

                        <button type="button" title="Изменить"
                                class="rounded p-1 text-[var(--text-muted)] transition hover:text-[var(--text-strong)]"
                                @click="emit('edit', node)">
                            <NIcon name="pencil" size="size-3.5" />
                        </button>

                        <button type="button" title="Удалить"
                                class="rounded p-1 text-[var(--text-muted)] transition hover:text-red-600"
                                @click="emit('remove', node)">
                            <NIcon name="trash" size="size-3.5" />
                        </button>
                    </div>
                </div>

                <MenuBranch v-if="node.accepts_children !== false" :items="node.children" :level="level + 1"
                            @change="emit('change')" @edit="emit('edit', $event)" @remove="emit('remove', $event)"
                            @move="emit('move', $event)" @nest="emit('nest', $event)" />
            </div>
        </template>
    </Draggable>
</template>
