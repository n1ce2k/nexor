/**
 * Thin fetch wrapper over the panel's JSON API.
 *
 * The API is session-authenticated, so requests only need the CSRF token and
 * the same-origin cookie. Validation errors (422) surface as an ApiError with
 * a field-keyed `errors` object the forms bind to directly.
 */

const base = () => document.querySelector('meta[name="nexor-api"]')?.content ?? '/admin/api';
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export class ApiError extends Error {
    constructor(message, status, errors = {}, payload = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
        this.payload = payload;
    }

    /** First message for a field, ready to drop under an input. */
    first(field) {
        return this.errors[field]?.[0] ?? null;
    }
}

function url(path, query = null) {
    const target = `${base()}/${String(path).replace(/^\/+/, '')}`;

    if (!query) {
        return target;
    }

    const params = new URLSearchParams();

    Object.entries(query).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        if (typeof value === 'object') {
            Object.entries(value).forEach(([inner, innerValue]) => {
                if (innerValue !== null && innerValue !== undefined && innerValue !== '') {
                    params.append(`${key}[${inner}]`, innerValue);
                }
            });

            return;
        }

        params.append(key, value);
    });

    const qs = params.toString();

    return qs ? `${target}?${qs}` : target;
}

async function send(method, path, { query = null, body = null, files = false } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
    let payload;

    if (files) {
        // Laravel does not parse multipart on PUT/PATCH, so those are tunnelled.
        payload = body instanceof FormData ? body : toFormData(body);

        if (method !== 'POST' && method !== 'GET') {
            payload.append('_method', method);
            method = 'POST';
        }
    } else if (body !== null) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    const response = await fetch(url(path, query), {
        method,
        headers,
        body: payload,
        credentials: 'same-origin',
    });

    if (response.status === 204) {
        return null;
    }

    const data = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(
            data?.message ?? `Запрос завершился ошибкой ${response.status}`,
            response.status,
            data?.errors ?? {},
            data,
        );
    }

    return data;
}

/**
 * Flattens a plain object into FormData, keeping nested keys in bracket form
 * and turning booleans into the 1/0 Laravel validation expects.
 */
export function toFormData(source, form = new FormData(), scope = null) {
    Object.entries(source ?? {}).forEach(([key, value]) => {
        const field = scope ? `${scope}[${key}]` : key;

        if (value === undefined || value === null) {
            return;
        }

        if (value instanceof File || value instanceof Blob) {
            form.append(field, value);
        } else if (Array.isArray(value)) {
            // An empty array sends nothing at all. Appending a blank marker used
            // to reach the server as one empty element and broke `integer` rules;
            // an absent key already means "empty" on the Laravel side.
            value.forEach((item, index) => {
                if (item instanceof File || item instanceof Blob) {
                    form.append(`${field}[]`, item);
                } else if (item !== null && typeof item === 'object') {
                    toFormData(item, form, `${field}[${index}]`);
                } else {
                    form.append(`${field}[]`, item === true ? '1' : item === false ? '0' : item);
                }
            });
        } else if (typeof value === 'object') {
            toFormData(value, form, field);
        } else {
            form.append(field, value === true ? '1' : value === false ? '0' : value);
        }
    });

    return form;
}

export const api = {
    get: (path, query) => send('GET', path, { query }),
    post: (path, body, files = false) => send('POST', path, { body, files }),
    put: (path, body, files = false) => send('PUT', path, { body, files }),
    patch: (path, body, files = false) => send('PATCH', path, { body, files }),
    delete: (path) => send('DELETE', path),
};
