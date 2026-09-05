import { reactive, ref } from 'vue';
import { ApiError, api } from '../api';
import { useUi } from '../stores/ui';

/**
 * Form state shared by every editor screen.
 *
 * Keeps the fields, the server-side validation errors and the busy flag in one
 * place, so a page only describes what to submit and where to go afterwards.
 */
export function useForm(initial = {}) {
    const fields = reactive({ ...initial });
    const errors = ref({});
    const busy = ref(false);
    const ui = useUi();

    function fill(values) {
        Object.assign(fields, values);
    }

    function reset(values = initial) {
        Object.keys(fields).forEach((key) => delete fields[key]);
        Object.assign(fields, values);
        errors.value = {};
    }

    function error(field) {
        return errors.value[field]?.[0] ?? null;
    }

    /**
     * @param {'post'|'put'|'patch'|'delete'} method
     * @param {object} options `body` overrides the fields, `files` switches to multipart
     */
    async function submit(method, url, { body = null, files = false, onSuccess = null } = {}) {
        busy.value = true;
        errors.value = {};

        try {
            const data = await api[method](url, body ?? { ...fields }, files);

            if (data?.message) {
                ui.notify(data.message);
            }

            onSuccess?.(data);

            return data;
        } catch (exception) {
            if (exception instanceof ApiError && exception.status === 422) {
                errors.value = exception.errors;
                ui.notify('Проверьте заполнение формы', 'error');
            } else {
                ui.notifyError(exception);
            }

            return null;
        } finally {
            busy.value = false;
        }
    }

    return { fields, errors, busy, fill, reset, error, submit };
}

/**
 * Turns a name into a URL-safe symbolic code, transliterating Cyrillic.
 */
const TRANSLIT = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i',
    й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't',
    у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '',
    э: 'e', ю: 'yu', я: 'ya',
};

export function slugify(value) {
    return String(value ?? '')
        .toLowerCase()
        .split('')
        .map((char) => (char in TRANSLIT ? TRANSLIT[char] : char))
        .join('')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 190);
}
