<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

/**
 * Визуальный редактор для любого поля, которое хранит HTML.
 *
 * Оборачивает Quill: наружу отдаёт обычную строку, поэтому компонент
 * подставляется вместо <textarea> без изменений на сервере.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Начните вводить текст…' },
    disabled: { type: Boolean, default: false },
    // Высота области ввода; крупные описания просят больше места.
    rows: { type: String, default: '16rem' },
});

const emit = defineEmits(['update:modelValue']);

const host = ref(null);

let quill = null;

/** Пустой документ Quill — не текст, а разметка; наружу отдаём пустую строку. */
const EMPTY = ['<p><br></p>', '<p></p>', '<div><br></div>'];

function currentHtml() {
    const html = quill.root.innerHTML;

    return EMPTY.includes(html) ? '' : html;
}

function setHtml(value) {
    const next = value ?? '';

    if (next === currentHtml()) {
        return;
    }

    // Клавиатурный фокус не должен прыгать в начало при внешнем обновлении.
    const selection = quill.getSelection();

    quill.setContents(quill.clipboard.convert({ html: next }), 'silent');

    if (selection) {
        quill.setSelection(selection.index, selection.length, 'silent');
    }
}

onMounted(() => {
    quill = new Quill(host.value, {
        theme: 'snow',
        placeholder: props.placeholder,
        readOnly: props.disabled,
        modules: {
            toolbar: [
                [{ header: [2, 3, 4, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['blockquote', 'code-block'],
                ['link', 'image'],
                ['clean'],
            ],
        },
    });

    setHtml(props.modelValue);

    quill.on('text-change', () => emit('update:modelValue', currentHtml()));
});

onBeforeUnmount(() => {
    quill = null;
});

watch(() => props.modelValue, (value) => {
    if (quill) {
        setHtml(value);
    }
});

watch(() => props.disabled, (disabled) => {
    quill?.enable(!disabled);
});
</script>

<template>
    <div class="nexor-editor overflow-hidden rounded-lg border border-[var(--surface-border-strong)]">
        <div ref="host" :style="{ height: rows }"></div>
    </div>
</template>

<style>
/*
 * Quill приходит со своей светлой темой; здесь она переводится на токены
 * панели, чтобы редактор жил и в тёмном оформлении.
 */
.nexor-editor .ql-toolbar.ql-snow,
.nexor-editor .ql-container.ql-snow {
    border: 0;
    font-family: inherit;
}

.nexor-editor .ql-toolbar.ql-snow {
    border-bottom: 1px solid var(--surface-border);
    background: var(--surface-muted);
}

.nexor-editor .ql-container.ql-snow {
    background: var(--surface-panel);
}

.nexor-editor .ql-editor {
    color: var(--text-strong);
    font-size: 0.875rem;
    line-height: 1.6;
}

.nexor-editor .ql-editor.ql-blank::before {
    color: var(--text-faint);
    font-style: normal;
}

.nexor-editor .ql-snow .ql-stroke {
    stroke: var(--text-muted);
}

.nexor-editor .ql-snow .ql-fill {
    fill: var(--text-muted);
}

.nexor-editor .ql-snow .ql-picker {
    color: var(--text-muted);
}

.nexor-editor .ql-snow .ql-picker-options {
    border-color: var(--surface-border-strong);
    background: var(--surface-panel);
}

.nexor-editor .ql-snow.ql-toolbar button:hover .ql-stroke,
.nexor-editor .ql-snow.ql-toolbar button.ql-active .ql-stroke,
.nexor-editor .ql-snow.ql-toolbar .ql-picker-label:hover .ql-stroke {
    stroke: var(--color-brand-600);
}

.nexor-editor .ql-snow.ql-toolbar button:hover .ql-fill,
.nexor-editor .ql-snow.ql-toolbar button.ql-active .ql-fill {
    fill: var(--color-brand-600);
}

.nexor-editor .ql-snow.ql-toolbar button:hover,
.nexor-editor .ql-snow.ql-toolbar button.ql-active,
.nexor-editor .ql-snow.ql-toolbar .ql-picker-label:hover {
    color: var(--color-brand-600);
}

/* Всплывающее окно ссылки. */
.nexor-editor .ql-snow .ql-tooltip {
    z-index: 30;
    border-color: var(--surface-border-strong);
    background: var(--surface-panel);
    box-shadow: 0 10px 30px rgb(0 0 0 / 0.15);
    color: var(--text-base);
}

.nexor-editor .ql-snow .ql-tooltip input[type="text"] {
    border-color: var(--surface-border-strong);
    background: var(--surface-muted);
    color: var(--text-strong);
}

.nexor-editor .ql-editor blockquote {
    border-left-color: var(--surface-border-strong);
    color: var(--text-muted);
}

.nexor-editor .ql-editor pre.ql-syntax {
    background: var(--surface-muted);
    color: var(--text-strong);
}
</style>
