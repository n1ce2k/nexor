<script setup>
import { inject, ref } from 'vue';
import Draggable from 'vuedraggable';
import MenuItemForm from './MenuItemForm.vue';
import NBadge from '../../components/ui/NBadge.vue';
import NIcon from '../../components/ui/NIcon.vue';

/**
 * Одна ветка дерева пунктов. Файл рекурсивно подключает сам себя.
 *
 * Вкладывать можно только в «Подменю» и на его глубину — перетаскиванием.
 * У подменю под пунктом свой блок ссылок, как список полей в редакторе формы
 * обратной связи: ⋮⋮ перетащить, ↑ ↓, ✎ правка прямо в списке, 🗑 и внизу
 * «Добавить ссылку».
 */
const props = defineProps({
    // Пункты этого уровня; правится на месте, поэтому именно массив, а не копия
    items: { type: Array, required: true },
    level: { type: Number, default: 0 },
    disabled: { type: Boolean, default: false },
    // Сколько уровней ещё можно вложить под пункты этой ветки; null — ветка вне подменю
    room: { type: Number, default: null },
    // Можно ли бросить пункт в эту ветку
    accepts: { type: Boolean, default: true },
    // Что сейчас правится прямо в списке: { mode: 'edit', id } или { mode: 'create', parentId }
    inline: { type: Object, default: null },
    menu: { type: Object, default: null },
    types: { type: Array, default: () => [] },
    visibility: { type: Array, default: () => [] },
});

const emit = defineEmits(['change', 'edit', 'remove', 'move', 'add', 'saved', 'cancel']);

// Идёт ли перетаскивание: пока да, у ссылок внутри подменю видно, куда их можно бросить.
const dragging = inject('menuDragging', ref(false));

/** Сколько уровней можно вложить под пункт. */
function roomOf(node) {
    if (node.accepts_children === false) {
        return 0;
    }

    if (node.type === 'submenu') {
        const own = Math.max(1, Number(node.max_depth) || 1);

        return props.room === null ? own : Math.min(props.room, own);
    }

    return props.room ?? 0;
}

/** Можно ли что-то вложить в пункт: подменю или ссылка внутри него, пока глубина позволяет. */
function canHold(node) {
    return roomOf(node) > 0 && (node.type === 'submenu' || props.room !== null);
}

/**
 * Блок ссылок под пунктом.
 *
 * У подменю он есть всегда. У ссылки внутри подменю — только когда в нём
 * что-то лежит, когда сюда добавляют или когда идёт перетаскивание: пустой
 * блок под каждой ссылкой только мешал бы читать список.
 */
function hasBlock(node) {
    if (!canHold(node)) {
        return false;
    }

    return node.type === 'submenu' || node.children.length > 0 || creatingIn(node) || dragging.value;
}

/** Лимит для веток ниже: вне подменю — снова без лимита. */
function childRoom(node) {
    return node.type !== 'submenu' && props.room === null ? null : roomOf(node) - 1;
}

function editingHere(node) {
    return props.inline?.mode === 'edit' && props.inline.id === node.id;
}

function creatingIn(node) {
    return props.inline?.mode === 'create' && props.inline.parentId === node.id;
}

const kindIcon = {
    link: 'chevron-right',
    page: 'document',
    section: 'folder',
    sections: 'layers',
    submenu: 'menu',
    heading: 'menu',
    divider: 'menu',
};
</script>

<template>
    <Draggable :list="items" :group="{ name: 'menu-items', pull: true, put: accepts }" item-key="id" handle=".menu-handle"
               :disabled="disabled" ghost-class="opacity-40" :animation="150"
               :class="['space-y-1', level > 0 && 'min-h-2']"
               @start="dragging = true" @end="dragging = false"
               @change="emit('change')">
        <template #item="{ element: node, index }">
            <div>
                <div class="surface flex items-center gap-2 rounded-lg border px-2 py-1.5">
                    <span class="menu-handle cursor-grab text-[var(--text-faint)] active:cursor-grabbing"
                          title="Перетащите, чтобы переставить или вложить в подменю">
                        <NIcon name="grip" size="size-4" />
                    </span>

                    <NIcon :name="kindIcon[node.type] ?? 'chevron-right'" size="size-4"
                           class="shrink-0 text-[var(--text-muted)]" />

                    <button type="button" class="min-w-0 flex-1 truncate text-left text-sm text-[var(--text-strong)]"
                            @click="emit('edit', node)">
                        {{ node.display_title }}
                    </button>

                    <code v-if="node.type === 'link' && node.url" class="hidden truncate font-mono text-xs text-[var(--text-faint)] sm:inline">
                        {{ node.url }}
                    </code>

                    <NBadge v-if="node.type !== 'link'" color="gray">{{ node.type_label }}</NBadge>
                    <NBadge v-if="!node.is_active" color="gray">скрыт</NBadge>

                    <div class="flex shrink-0 items-center gap-0.5">
                        <button v-if="!disabled && canHold(node) && node.type !== 'submenu' && !node.children.length"
                                type="button" title="Добавить вложенную ссылку"
                                class="rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]"
                                @click="emit('add', node)">
                            <NIcon name="plus" size="size-3.5" />
                        </button>

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

                        <button type="button" :title="editingHere(node) ? 'Свернуть' : 'Изменить'"
                                class="rounded p-1 text-[var(--text-muted)] transition hover:text-[var(--text-strong)]"
                                @click="editingHere(node) ? emit('cancel') : emit('edit', node)">
                            <NIcon name="pencil" size="size-3.5" />
                        </button>

                        <button type="button" title="Удалить"
                                class="rounded p-1 text-[var(--text-muted)] transition hover:text-red-600"
                                @click="emit('remove', node)">
                            <NIcon name="trash" size="size-3.5" />
                        </button>
                    </div>
                </div>

                <div v-if="editingHere(node)" class="mt-1 ml-6">
                    <MenuItemForm inline :menu="menu" :item="node" :types="types" :visibility="visibility"
                                  @saved="emit('saved', $event)" @cancel="emit('cancel')" />
                </div>

                <!-- Подменю и ссылки внутри него: свой блок со списком и «Добавить ссылку». -->
                <div v-if="hasBlock(node)"
                     class="mt-1 ml-6 space-y-2 rounded-lg border border-dashed border-[var(--surface-border-strong)] p-2">
                    <MenuBranch :items="node.children" :level="level + 1" :disabled="disabled"
                                :room="childRoom(node)" :accepts="true"
                                :inline="inline" :menu="menu" :types="types" :visibility="visibility"
                                @change="emit('change')" @edit="emit('edit', $event)" @remove="emit('remove', $event)"
                                @move="emit('move', $event)" @add="emit('add', $event)"
                                @saved="emit('saved', $event)" @cancel="emit('cancel')" />

                    <p v-if="!node.children.length && !creatingIn(node)" class="px-1 text-xs text-[var(--text-muted)]">
                        Ссылок пока нет — добавьте или перетащите сюда.
                    </p>

                    <MenuItemForm v-if="creatingIn(node)" inline :menu="menu" :parent-id="node.id"
                                  :types="types" :visibility="visibility"
                                  @saved="emit('saved', $event)" @cancel="emit('cancel')" />

                    <button v-else-if="!disabled" type="button"
                            class="inline-flex items-center gap-1.5 px-1 text-xs font-medium text-brand-600 transition hover:underline dark:text-brand-400"
                            @click="emit('add', node)">
                        <NIcon name="plus" size="size-3.5" />
                        Добавить ссылку
                    </button>
                </div>

                <!-- Вложенные до появления «Подменю»: видны и переставляются, но новое внутрь не бросить. -->
                <div v-else-if="node.children?.length" class="mt-1 ml-6 border-l border-[var(--surface-border)] pl-3">
                    <MenuBranch :items="node.children" :level="level + 1" :disabled="disabled"
                                :room="childRoom(node)" :accepts="false"
                                :inline="inline" :menu="menu" :types="types" :visibility="visibility"
                                @change="emit('change')" @edit="emit('edit', $event)" @remove="emit('remove', $event)"
                                @move="emit('move', $event)" @add="emit('add', $event)"
                                @saved="emit('saved', $event)" @cancel="emit('cancel')" />
                </div>
            </div>
        </template>
    </Draggable>
</template>
