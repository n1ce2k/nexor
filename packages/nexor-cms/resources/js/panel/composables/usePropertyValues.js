/**
 * Значения свойств в форме — общее для элемента и раздела.
 *
 * Свойства у них одних и тех же типов, а на сервер значения уходят одинаково:
 * multipart, потому что у файловых свойств рядом со скалярами лежат настоящие
 * File. Поэтому пустые значения, разбор сохранённого и сборка тела запроса
 * живут в одном месте.
 */

export function isFileProperty(property) {
    return property.type === 'file' || property.type === 'image';
}

/** Описания значений: у обычных свойств по индексу, у файлов — внутри строк файлов. */
export function blankDescription(property) {
    return property.is_multiple ? [] : '';
}

export function blankValue(property) {
    if (isFileProperty(property)) {
        return { stored: [], remove: [], added: [], addedDescriptions: [] };
    }

    if (property.is_multiple) {
        return [];
    }

    if (property.type === 'boolean') {
        return property.default_value === '1' || property.default_value === 'true';
    }

    return property.default_value ?? null;
}

/**
 * Значения новой записи: у каждого свойства пустое или значение по умолчанию.
 *
 * @returns {{ values: Object, descriptions: Object }}
 */
export function blankPropertyValues(properties) {
    const values = {};
    const descriptions = {};

    properties.forEach((property) => {
        values[property.code] = blankValue(property);
        descriptions[property.code] = blankDescription(property);
    });

    return { values, descriptions };
}

/**
 * Значения сохранённой записи поверх пустых.
 *
 * `stored` и `storedDescriptions` — то, что отдал сервер: код свойства → значение.
 */
export function fillPropertyValues(properties, state, stored, storedDescriptions) {
    properties.forEach((property) => {
        const value = stored?.[property.code];

        state.values[property.code] = isFileProperty(property)
            ? { stored: Array.isArray(value) ? value : [], remove: [], added: [], addedDescriptions: [] }
            : (value ?? state.values[property.code]);

        if (!isFileProperty(property)) {
            state.descriptions[property.code] = storedDescriptions?.[property.code] ?? blankDescription(property);
        }
    });

    return state;
}

/**
 * Дописывает значения свойств в тело запроса.
 */
export function appendPropertyValues(body, properties, values, descriptions) {
    properties.forEach((property) => {
        const value = values[property.code];

        if (isFileProperty(property)) {
            (value?.remove ?? []).forEach((id) => body.append(`property_remove[${property.code}][]`, id));
            (value?.added ?? []).forEach((file) => {
                body.append(`property_files[${property.code}]${property.is_multiple ? '[]' : ''}`, file);
            });

            if (property.with_description) {
                (value?.stored ?? []).forEach((file) => {
                    body.append(`property_descriptions[${property.code}][saved][${file.id}]`, file.description ?? '');
                });

                (value?.added ?? []).forEach((_, index) => {
                    body.append(
                        `property_descriptions[${property.code}][added][]`,
                        (value?.addedDescriptions ?? [])[index] ?? '',
                    );
                });
            }

            return;
        }

        const description = descriptions[property.code];

        if (property.is_multiple) {
            // Значение и его описание отправляются парой: сервер отсеет пустые строки вместе.
            const raw = Array.isArray(value) ? value : [];
            const rows = raw.map((item, index) => [item, Array.isArray(description) ? (description[index] ?? '') : ''])
                .filter(([item]) => item !== null && item !== '');

            if (rows.length === 0) {
                body.append(`properties[${property.code}][]`, '');
            }

            rows.forEach(([item, text]) => {
                body.append(`properties[${property.code}][]`, item);

                if (property.with_description) {
                    body.append(`property_descriptions[${property.code}][]`, text);
                }
            });

            return;
        }

        const scalar = property.type === 'boolean' ? (value ? '1' : '0') : value;

        body.append(`properties[${property.code}]`, scalar ?? '');

        if (property.with_description) {
            body.append(`property_descriptions[${property.code}]`, typeof description === 'string' ? description : '');
        }
    });

    return body;
}

/** Поле свойства занимает всю ширину формы: длинный текст, файлы, несколько значений. */
export function isWideProperty(property) {
    return property.is_multiple || ['text', 'html', 'json', 'file', 'image'].includes(property.type);
}
