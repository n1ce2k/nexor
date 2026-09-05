import { createRouter, createWebHistory } from 'vue-router';
import { registry } from './registry';
import { useSession } from './stores/session';

import Dashboard from './pages/Dashboard.vue';
import IblockIndex from './pages/iblocks/IblockIndex.vue';
import IblockForm from './pages/iblocks/IblockForm.vue';
import PropertyIndex from './pages/properties/PropertyIndex.vue';
import PropertyForm from './pages/properties/PropertyForm.vue';
import ElementIndex from './pages/elements/ElementIndex.vue';
import ElementForm from './pages/elements/ElementForm.vue';
import NotFound from './pages/NotFound.vue';

const routes = [
    { path: '', name: 'dashboard', component: Dashboard },

    { path: 'iblocks', name: 'iblocks.index', component: IblockIndex, meta: { permission: 'iblocks.view' } },
    { path: 'iblocks/create', name: 'iblocks.create', component: IblockForm, meta: { permission: 'iblocks.create' } },
    { path: 'iblocks/:iblock/edit', name: 'iblocks.edit', component: IblockForm, props: true, meta: { permission: 'iblocks.update' } },

    { path: 'iblocks/:iblock/properties', name: 'properties.index', component: PropertyIndex, props: true, meta: { permission: 'iblocks.update' } },
    { path: 'iblocks/:iblock/properties/create', name: 'properties.create', component: PropertyForm, props: true, meta: { permission: 'iblocks.update' } },
    { path: 'iblocks/:iblock/properties/:property/edit', name: 'properties.edit', component: PropertyForm, props: true, meta: { permission: 'iblocks.update' } },

    { path: 'iblocks/:iblock/elements', name: 'elements.index', component: ElementIndex, props: true },
    { path: 'iblocks/:iblock/elements/create', name: 'elements.create', component: ElementForm, props: true },
    { path: 'iblocks/:iblock/elements/:element/edit', name: 'elements.edit', component: ElementForm, props: true },

    { path: ':pathMatch(.*)*', name: 'not-found', component: NotFound },
];

export function createPanelRouter(base) {
    // Pages registered by the host application are inserted before the catch-all.
    const custom = registry.pages.map((page) => ({
        path: page.path,
        name: page.name,
        component: page.component,
        props: page.props,
        meta: { permission: page.permission },
    }));

    const router = createRouter({
        history: createWebHistory(base),
        routes: [...routes.slice(0, -1), ...custom, routes[routes.length - 1]],
        scrollBehavior: () => ({ top: 0 }),
    });

    router.beforeEach((to) => {
        const session = useSession();
        const permission = to.meta?.permission;

        if (permission && session.ready && !session.can(permission)) {
            return { name: 'dashboard' };
        }

        return true;
    });

    return router;
}
