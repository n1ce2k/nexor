import Alpine from 'alpinejs';

const TRANSLIT = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i',
    й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't',
    у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '',
    э: 'e', ю: 'yu', я: 'ya',
};

/**
 * Transliterate Cyrillic and punctuation into a URL-safe symbolic code.
 */
export function slugify(value) {
    return String(value)
        .toLowerCase()
        .split('')
        .map((char) => (char in TRANSLIT ? TRANSLIT[char] : char))
        .join('')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 190);
}

window.slugify = slugify;

/**
 * Mirrors a name field into a code field until the code is edited by hand.
 */
Alpine.data('slugField', (initial = '') => ({
    code: initial,
    touched: Boolean(initial),

    fromName(event) {
        if (!this.touched) {
            this.code = slugify(event.target.value);
        }
    },

    markTouched() {
        this.touched = this.code.length > 0;
    },
}));

/**
 * File input with an inline preview and a "remove existing file" flag.
 */
Alpine.data('fileField', (existingUrl = null) => ({
    preview: existingUrl,
    removed: false,
    fileName: null,

    pick(event) {
        const file = event.target.files[0];

        if (!file) {
            return;
        }

        this.removed = false;
        this.fileName = file.name;
        this.preview = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
    },

    clear() {
        this.preview = null;
        this.fileName = null;
        this.removed = true;
        this.$refs.input.value = '';
    },
}));

/**
 * Repeatable rows — used for property enum values and multiple property values.
 */
Alpine.data('repeater', (initial = [], blank = {}) => ({
    rows: initial.length ? initial : [{ ...blank }],
    blank,

    add() {
        this.rows.push({ ...this.blank });
    },

    remove(index) {
        this.rows.splice(index, 1);

        if (this.rows.length === 0) {
            this.add();
        }
    },

    move(index, offset) {
        const target = index + offset;

        if (target < 0 || target >= this.rows.length) {
            return;
        }

        const [row] = this.rows.splice(index, 1);
        this.rows.splice(target, 0, row);
    },
}));

/**
 * Keeps a colour swatch, the native picker and the HEX text field in sync.
 */
Alpine.data('colorField', (initial = '#000000') => ({
    value: initial || '#000000',

    get normalised() {
        const value = this.value.trim();

        return /^#?[0-9a-f]{6}$/i.test(value) ? (value.startsWith('#') ? value : `#${value}`) : '#000000';
    },

    sync(event) {
        this.value = event.target.value;
    },
}));

/**
 * Checkbox column for tables: header checkbox, per-row state, bulk action guard.
 */
Alpine.data('bulkSelect', () => ({
    selected: [],

    get count() {
        return this.selected.length;
    },

    get allSelected() {
        return this.rowIds().length > 0 && this.selected.length === this.rowIds().length;
    },

    rowIds() {
        return Array.from(this.$root.querySelectorAll('[data-row-id]')).map((row) => row.dataset.rowId);
    },

    toggleAll(event) {
        this.selected = event.target.checked ? this.rowIds() : [];
    },

    clear() {
        this.selected = [];
    },
}));

/**
 * Submits a hidden form after the user confirms a destructive action.
 */
Alpine.data('confirmAction', () => ({
    open: false,
    message: '',
    action: null,

    ask(action, message) {
        this.action = action;
        this.message = message;
        this.open = true;
    },

    confirm() {
        this.open = false;
        this.action?.();
    },
}));
