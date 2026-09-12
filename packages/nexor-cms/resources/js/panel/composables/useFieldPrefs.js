import { ref, watch } from 'vue';

/**
 * Набор выбранных полей, который переживает перезагрузку страницы.
 *
 * Лежит в браузере, а не в настройках инфоблока: какие колонки видеть — вкус
 * редактора, у соседа он свой. Ключ приходит функцией, потому что инфоблок
 * предложений известен только после запроса.
 *
 * @param {string|(() => string|null)} key Ключ хранения; null — пока не знаем
 * @param {Array<string>} fallback Что показывать, пока ничего не выбрали
 */
export function useFieldPrefs(key, fallback = []) {
    const fields = ref([...fallback]);
    const resolve = () => (typeof key === 'function' ? key() : key);

    let current = null;

    watch(resolve, (next) => {
        current = next;

        if (!next) {
            return;
        }

        try {
            const stored = JSON.parse(localStorage.getItem(next) ?? 'null');

            fields.value = Array.isArray(stored) ? stored : [...fallback];
        } catch {
            fields.value = [...fallback];
        }
    }, { immediate: true });

    watch(fields, (value) => {
        if (!current) {
            return;
        }

        try {
            localStorage.setItem(current, JSON.stringify(value));
        } catch {
            // Приватный режим и запрет на хранение — не повод ронять форму.
        }
    }, { deep: true });

    return fields;
}

/**
 * Печатное значение свойства элемента: списки разворачиваются в подписи.
 *
 * @param {Array<object>} properties Свойства инфоблока со своими вариантами
 * @param {object} row Элемент из API, с картой `properties`
 * @param {string} code Код свойства
 */
export function propertyText(properties, row, code) {
    const property = properties.find((item) => item.code === code);
    const raw = row?.properties?.[code];

    if (raw === null || raw === undefined || raw === '') {
        return '—';
    }

    const values = Array.isArray(raw) ? raw : [raw];

    if (values.length === 0) {
        return '—';
    }

    return values.map((value) => {
        if (value === true) {
            return 'да';
        }

        if (value === false) {
            return 'нет';
        }

        // Значение списка приходит идентификатором варианта.
        const option = (property?.enums ?? []).find((item) => String(item.id) === String(value));

        return option ? option.value : value;
    }).join(', ');
}
