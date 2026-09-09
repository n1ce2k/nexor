import { createRouter, createWebHistory } from 'vue-router';
import { registry } from './registry';
import { useSession } from './stores/session';

import Dashboard from './pages/Dashboard.vue';
import LogsIndex from './pages/LogsIndex.vue';
import NotFound from './pages/NotFound.vue';
import ElementForm from './pages/elements/ElementForm.vue';
import ElementIndex from './pages/elements/ElementIndex.vue';
import TypeIndex from './pages/iblock-types/TypeIndex.vue';
import IblockForm from './pages/iblocks/IblockForm.vue';
import IblockIndex from './pages/iblocks/IblockIndex.vue';
import MailSmtp from './pages/mail/MailSmtp.vue';
import MenuIndex from './pages/menus/MenuIndex.vue';
import TemplateForm from './pages/mail/TemplateForm.vue';
import TemplateIndex from './pages/mail/TemplateIndex.vue';
import PropertyForm from './pages/properties/PropertyForm.vue';
import PropertyIndex from './pages/properties/PropertyIndex.vue';
import RoleForm from './pages/roles/RoleForm.vue';
import RoleIndex from './pages/roles/RoleIndex.vue';
import SectionForm from './pages/sections/SectionForm.vue';
import SectionIndex from './pages/sections/SectionIndex.vue';
import SiteSettings from './pages/settings/SiteSettings.vue';
import PhpConsole from './pages/tools/PhpConsole.vue';
import SqlConsole from './pages/tools/SqlConsole.vue';
import UserForm from './pages/users/UserForm.vue';
import UserIndex from './pages/users/UserIndex.vue';

const routes = [
    { path: '/', name: 'dashboard', component: Dashboard },

    // Контент
    { path: '/iblocks/:iblock/elements', name: 'elements.index', component: ElementIndex, props: true },
    { path: '/iblocks/:iblock/elements/create', name: 'elements.create', component: ElementForm, props: true },
    { path: '/iblocks/:iblock/elements/:element/edit', name: 'elements.edit', component: ElementForm, props: true },
    { path: '/iblocks/:iblock/sections', name: 'sections.index', component: SectionIndex, props: true },
    { path: '/iblocks/:iblock/sections/create', name: 'sections.create', component: SectionForm, props: true },
    { path: '/iblocks/:iblock/sections/:section/edit', name: 'sections.edit', component: SectionForm, props: true },

    // Структура
    { path: '/iblocks', name: 'iblocks.index', component: IblockIndex, meta: { permission: 'iblocks.view' } },
    { path: '/iblocks/create', name: 'iblocks.create', component: IblockForm, meta: { permission: 'iblocks.create' } },
    { path: '/iblocks/:iblock/edit', name: 'iblocks.edit', component: IblockForm, props: true, meta: { permission: 'iblocks.update' } },
    { path: '/iblocks/:iblock/properties', name: 'properties.index', component: PropertyIndex, props: true, meta: { permission: 'iblocks.update' } },
    { path: '/iblocks/:iblock/properties/create', name: 'properties.create', component: PropertyForm, props: true, meta: { permission: 'iblocks.update' } },
    { path: '/iblocks/:iblock/properties/:property/edit', name: 'properties.edit', component: PropertyForm, props: true, meta: { permission: 'iblocks.update' } },
    { path: '/iblock-types', name: 'iblock-types.index', component: TypeIndex, meta: { permission: 'iblock_types.view' } },
    { path: '/menus', name: 'menus.index', component: MenuIndex, meta: { permission: 'menus.view' } },

    // Администрирование
    { path: '/users', name: 'users.index', component: UserIndex, meta: { permission: 'users.view' } },
    { path: '/users/create', name: 'users.create', component: UserForm, meta: { permission: 'users.create' } },
    { path: '/users/:user/edit', name: 'users.edit', component: UserForm, props: true, meta: { permission: 'users.update' } },
    { path: '/roles', name: 'roles.index', component: RoleIndex, meta: { permission: 'roles.view' } },
    { path: '/roles/create', name: 'roles.create', component: RoleForm, meta: { permission: 'roles.create' } },
    { path: '/roles/:role/edit', name: 'roles.edit', component: RoleForm, props: true, meta: { permission: 'roles.update' } },
    { path: '/site-settings', name: 'settings', component: SiteSettings, meta: { permission: 'settings.view' } },
    { path: '/logs', name: 'logs', component: LogsIndex, meta: { permission: 'logs.view' } },

    // Настройки
    { path: '/settings/mail/smtp', name: 'mail.smtp', component: MailSmtp, meta: { permission: 'mail.view' } },
    { path: '/settings/mail/templates', name: 'mail.templates', component: TemplateIndex, meta: { permission: 'mail.view' } },
    { path: '/settings/mail/templates/create', name: 'mail.templates.create', component: TemplateForm, meta: { permission: 'mail.update' } },
    { path: '/settings/mail/templates/:template/edit', name: 'mail.templates.edit', component: TemplateForm, props: true, meta: { permission: 'mail.update' } },
    { path: '/settings/tools/sql', name: 'tools.sql', component: SqlConsole },
    { path: '/settings/tools/php', name: 'tools.php', component: PhpConsole },

    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFound },
];

export function createPanelRouter(base) {
    // Pages registered by the host application are inserted before the catch-all.
    const custom = registry.pages.map((page) => ({
        // Registered paths may be written with or without a leading slash.
        path: page.path.startsWith('/') ? page.path : `/${page.path}`,
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
