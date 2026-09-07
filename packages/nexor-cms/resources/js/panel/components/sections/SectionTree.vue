<script setup>
import NIcon from '../ui/NIcon.vue';

/**
 * Раскрывающееся дерево разделов.
 *
 * Компонент рекурсивный: ветка рисует саму себя для своих детей. Состояние
 * (что раскрыто, что выбрано) живёт в родительском экране, поэтому дерево
 * остаётся «глупым» и одинаково годится и для боковой панели, и для любого
 * другого места, где понадобится структура инфоблока.
 */
defineProps({
    // [{ id, name, elements_count, is_active, children: [] }]
    nodes: { type: Array, required: true },
    // id выбранного раздела, null — «все элементы»
    selected: { type: [Number, null], default: null },
    // { [id]: true } — раскрытые ветки
    expanded: { type: Object, required: true },
    iblock: { type: [String, Number], required: true },
    abilities: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['select', 'toggle', 'remove']);
</script>

<template>
    <ul class="space-y-0.5">
        <li v-for="node in nodes" :key="node.id">
            <div :class="['group flex items-center gap-0.5 rounded-lg pr-1 transition',
                          selected === node.id
                              ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'
                              : 'hover:bg-[var(--surface-muted)]']">
                <button v-if="node.children.length" type="button"
                        class="shrink-0 rounded p-1 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]"
                        :title="expanded[node.id] ? 'Свернуть' : 'Развернуть'"
                        @click="emit('toggle', node.id)">
                    <NIcon name="chevron-right" size="size-3.5 transition"
                           :class="expanded[node.id] && 'rotate-90'" />
                </button>

                <span v-else class="size-5 shrink-0"></span>

                <button type="button" class="flex min-w-0 flex-1 items-center gap-2 py-1.5 text-left"
                        @click="emit('select', node.id)">
                    <NIcon name="folder" size="size-4 shrink-0"
                           :class="selected === node.id ? '' : 'text-[var(--text-faint)]'" />

                    <span :class="['min-w-0 flex-1 truncate text-sm', !node.is_active && 'opacity-50']">
                        {{ node.name }}
                    </span>

                    <span class="shrink-0 text-xs text-[var(--text-faint)] group-hover:hidden">
                        {{ node.elements_count ?? 0 }}
                    </span>
                </button>

                <div class="hidden shrink-0 items-center gap-0.5 group-hover:flex">
                    <router-link v-if="abilities.create"
                                 :to="{ name: 'sections.create', params: { iblock }, query: { parent: node.id } }"
                                 title="Добавить вложенный раздел"
                                 class="rounded p-1 text-[var(--text-muted)] transition hover:text-[var(--text-strong)]">
                        <NIcon name="plus" size="size-3.5" />
                    </router-link>

                    <router-link v-if="abilities.update"
                                 :to="{ name: 'sections.edit', params: { iblock, section: node.id } }"
                                 title="Изменить раздел"
                                 class="rounded p-1 text-[var(--text-muted)] transition hover:text-[var(--text-strong)]">
                        <NIcon name="pencil" size="size-3.5" />
                    </router-link>

                    <button v-if="abilities.delete" type="button" title="Удалить раздел"
                            class="rounded p-1 text-[var(--text-muted)] transition hover:text-red-600"
                            @click="emit('remove', node)">
                        <NIcon name="trash" size="size-3.5" />
                    </button>
                </div>
            </div>

            <SectionTree v-if="expanded[node.id] && node.children.length" :nodes="node.children"
                         :selected="selected" :expanded="expanded" :iblock="iblock" :abilities="abilities"
                         class="ml-4 border-l border-[var(--surface-border)] pl-2"
                         @select="emit('select', $event)"
                         @toggle="emit('toggle', $event)"
                         @remove="emit('remove', $event)" />
        </li>
    </ul>
</template>
