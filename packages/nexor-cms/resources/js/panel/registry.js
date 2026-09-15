import { markRaw, shallowReactive } from 'vue';

/**
 * Extension points of the panel.
 *
 * Everything the UI renders — property editors, routes, menu entries, list
 * columns — is looked up here rather than hardcoded, so a host application can
 * add its own without forking the package. Register before `mount()` runs:
 *
 *   window.Nexor.registerField('geo', MyMapField)
 *   window.Nexor.registerPage({ path: 'reports', name: 'reports', component: Reports })
 *   window.Nexor.registerMenuItem({ label: 'Отчёты', to: { name: 'reports' }, icon: 'chart', group: 'Контент' })
 */
export const registry = shallowReactive({
    fields: {},
    pages: [],
    menuItems: [],
    columns: {},
    formFields: {},
    hooks: {},
});

/**
 * Поле формы элемента от модуля: `registerFormField('pagebuilder.content', BuilderField)`.
 *
 * Сервер кладёт его в раскладку как `module:pagebuilder.content`. Компонент
 * получает `modelValue`, `iblock` (схема инфоблока), `element` (id или null),
 * `label`, `error` и отдаёт `update:modelValue`. Значение-объект уходит на
 * сервер JSON-строкой в `modules[pagebuilder][content]`.
 */
export function registerFormField(key, component) {
    registry.formFields[key] = markRaw(component);
}

export function resolveFormField(key) {
    return registry.formFields[key] ?? null;
}

/**
 * Map a property type onto the component that edits it.
 */
export function registerField(type, component) {
    registry.fields[type] = markRaw(component);

    return registry.fields[type];
}

export function resolveField(type) {
    return registry.fields[type] ?? registry.fields.string ?? null;
}

/**
 * Add a route. `menu` shortcuts registering a matching sidebar entry.
 */
export function registerPage({ path, name, component, menu = null, permission = null, feature = null, props = true }) {
    registry.pages.push({ path, name, component: markRaw(component), permission, feature, props });

    if (menu) {
        registerMenuItem({
            ...menu,
            to: menu.to ?? { name },
            permission: menu.permission ?? permission,
            feature: menu.feature ?? feature,
        });
    }
}

/**
 * `feature` — код функции или модуля: пункт виден, только пока она доступна
 * на лицензии сайта. `group` с новым названием заводит свою группу в сайдбаре.
 */
export function registerMenuItem(item) {
    registry.menuItems.push({ group: null, icon: 'document', permission: null, feature: null, ...item });
}

/**
 * Override how one column of one infoblock's element list is rendered.
 */
export function registerColumn(iblockCode, propertyCode, component) {
    registry.columns[`${iblockCode}.${propertyCode}`] = markRaw(component);
}

export function resolveColumn(iblockCode, propertyCode) {
    return registry.columns[`${iblockCode}.${propertyCode}`] ?? null;
}

/**
 * Lightweight event bus for cross-cutting behaviour: `element.saved`, etc.
 */
export function on(hook, callback) {
    (registry.hooks[hook] ??= []).push(callback);
}

export function emit(hook, payload) {
    (registry.hooks[hook] ?? []).forEach((callback) => callback(payload));
}
