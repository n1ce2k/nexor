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
    hooks: {},
});

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
export function registerPage({ path, name, component, menu = null, permission = null, props = true }) {
    registry.pages.push({ path, name, component: markRaw(component), permission, props });

    if (menu) {
        registerMenuItem({ ...menu, to: menu.to ?? { name }, permission: menu.permission ?? permission });
    }
}

export function registerMenuItem(item) {
    registry.menuItems.push({ group: null, icon: 'document', permission: null, ...item });
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
