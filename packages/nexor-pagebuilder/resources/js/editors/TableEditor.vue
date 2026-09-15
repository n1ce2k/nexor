<script setup>
import { computed } from 'vue';
import { NField, NInput, NToggle } from '../core.js';
import Segmented from '../components/Segmented.vue';

/**
 * Таблица ячейками: ряды и колонки добавляются и удаляются кнопками,
 * текст вставляется из Excel — табуляции и переносы разложатся по ячейкам.
 */
const props = defineProps({ data: { type: Object, required: true } });

props.data.showTitle ??= false;
props.data.titleTag ??= 'h3';
props.data.header ??= true;

const titleTags = ['h2', 'h3', 'h4', 'h5'].map((tag) => ({ value: tag, label: tag.toUpperCase() }));

const MAX_ROWS = 200;
const MAX_COLUMNS = 12;

const rows = computed(() => {
    props.data.rows ??= [['', '']];

    return props.data.rows;
});

const width = computed(() => Math.max(1, ...rows.value.map((row) => row.length)));

function addRow(at = rows.value.length) {
    if (rows.value.length < MAX_ROWS) {
        rows.value.splice(at, 0, Array(width.value).fill(''));
    }
}

function addColumn() {
    if (width.value < MAX_COLUMNS) {
        rows.value.forEach((row) => row.push(''));
    }
}

function removeRow(index) {
    if (rows.value.length > 1) {
        rows.value.splice(index, 1);
    }
}

function removeColumn(index) {
    if (width.value > 1) {
        rows.value.forEach((row) => row.splice(index, 1));
    }
}

/** Вставка из таблицы: несколько ячеек сразу раскладываются от текущей. */
function onPaste(event, rowIndex, columnIndex) {
    const text = event.clipboardData?.getData('text/plain') ?? '';

    if (!text.includes('\t') && !text.includes('\n')) {
        return;
    }

    event.preventDefault();

    text.replace(/\r/g, '').replace(/\n$/, '').split('\n').forEach((line, lineOffset) => {
        const target = rowIndex + lineOffset;

        if (target >= MAX_ROWS) {
            return;
        }

        while (rows.value.length <= target) {
            addRow();
        }

        line.split('\t').forEach((cell, cellOffset) => {
            const column = columnIndex + cellOffset;

            if (column >= MAX_COLUMNS) {
                return;
            }

            while (width.value <= column) {
                addColumn();
            }

            rows.value[target][column] = cell;
        });
    });
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            <NToggle v-model="data.showTitle" label="Заголовок над таблицей" />
            <NToggle v-model="data.header" label="Первая строка — шапка" />
        </div>

        <div v-if="data.showTitle" class="flex flex-wrap items-end gap-3">
            <NField label="Заголовок таблицы" class="min-w-60 flex-1">
                <NInput v-model="data.title" />
            </NField>
            <Segmented v-model="data.titleTag" :options="titleTags" mono />
        </div>

        <div class="overflow-x-auto rounded-lg border border-[var(--surface-border)]">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th class="w-8 bg-[var(--surface-muted)]"></th>
                        <th v-for="column in width" :key="column"
                            class="border-l border-[var(--surface-border)] bg-[var(--surface-muted)] px-1 py-1 text-right">
                            <button type="button" title="Удалить колонку" :disabled="width === 1"
                                    class="rounded p-0.5 text-[var(--text-faint)] hover:text-red-600 disabled:opacity-30"
                                    @click="removeColumn(column - 1)">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, rowIndex) in rows" :key="rowIndex" class="border-t border-[var(--surface-border)]">
                        <td class="bg-[var(--surface-muted)] text-center">
                            <button type="button" title="Удалить строку" :disabled="rows.length === 1"
                                    class="rounded p-0.5 text-[var(--text-faint)] hover:text-red-600 disabled:opacity-30"
                                    @click="removeRow(rowIndex)">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                            </button>
                        </td>
                        <td v-for="column in width" :key="column" class="min-w-32 border-l border-[var(--surface-border)] p-0">
                            <input v-model="row[column - 1]" type="text"
                                   :class="['w-full bg-transparent px-2 py-1.5 text-[var(--text-strong)] outline-none focus:bg-brand-50 dark:focus:bg-brand-500/10',
                                            data.header && rowIndex === 0 && 'font-semibold']"
                                   @paste="onPaste($event, rowIndex, column - 1)">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-4 text-sm">
            <button type="button" class="font-medium text-brand-600 hover:underline dark:text-brand-400" @click="addRow()">+ Строка</button>
            <button type="button" class="font-medium text-brand-600 hover:underline dark:text-brand-400" @click="addColumn">+ Колонка</button>
            <span class="text-xs text-[var(--text-muted)]">Можно вставить таблицу из Excel или Google Таблиц.</span>
        </div>
    </div>
</template>
