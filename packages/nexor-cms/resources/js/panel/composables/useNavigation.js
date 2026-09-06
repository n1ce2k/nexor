import { computed } from 'vue';
import { registry } from '../registry';
import { useSession } from '../stores/session';

/**
 * Sidebar structure.
 *
 * Built from three sources: fixed panel entries, the infoblocks the user may
 * open (grouped by their type), and whatever a host application registered
 * through `registerMenuItem`.
 */
export function useNavigation() {
    const session = useSession();

    return computed(() => {
        const groups = [];
        const custom = registry.menuItems.filter((item) => !item.permission || session.can(item.permission));
        const extras = (group) => custom.filter((item) => item.group === group);

        groups.push({
            label: null,
            items: [{ label: 'Рабочий стол', icon: 'dashboard', to: { name: 'dashboard' } }],
        });

        // Контент: one entry per infoblock, nested under its type.
        const byType = {};

        session.iblocks.forEach((iblock) => {
            const type = iblock.type?.name ?? 'Прочее';

            (byType[type] ??= []).push({
                label: iblock.name,
                to: { name: 'elements.index', params: { iblock: iblock.id } },
            });
        });

        const content = Object.entries(byType).map(([label, children]) => ({
            label,
            icon: 'folder',
            children,
        }));

        groups.push({ label: 'Контент', items: [...content, ...extras('Контент')] });

        // Структура
        const structure = [];

        if (session.can('iblocks.view')) {
            structure.push({ label: 'Инфоблоки', icon: 'layers', to: { name: 'iblocks.index' } });
        }

        if (session.can('iblock_types.view')) {
            structure.push({ label: 'Типы инфоблоков', icon: 'database', to: { name: 'iblock-types.index' } });
        }

        groups.push({ label: 'Структура', items: [...structure, ...extras('Структура')] });

        // Настройки: mail and the developer console, each with its own subtree.
        const settings = [];
        const mail = [];

        if (session.can('mail.view')) {
            mail.push(
                { label: 'SMTP почта', to: { name: 'mail.smtp' } },
                { label: 'Почтовые шаблоны', to: { name: 'mail.templates' } },
            );
        }

        if (mail.length) {
            settings.push({ label: 'Почта', icon: 'document', children: mail });
        }

        // The console is super-admin only, mirroring the server-side check.
        if (session.isSuperAdmin) {
            settings.push({
                label: 'Инструменты',
                icon: 'database',
                children: [
                    { label: 'SQL запрос', to: { name: 'tools.sql' } },
                    { label: 'PHP-строка', to: { name: 'tools.php' } },
                ],
            });
        }

        groups.push({ label: 'Настройки', items: [...settings, ...extras('Настройки')] });

        // Администрирование
        const admin = [];

        if (session.can('users.view')) {
            admin.push({ label: 'Пользователи', icon: 'users', to: { name: 'users.index' } });
        }

        if (session.can('roles.view')) {
            admin.push({ label: 'Роли и права', icon: 'shield', to: { name: 'roles.index' } });
        }

        if (session.can('settings.view')) {
            admin.push({ label: 'Настройки сайта', icon: 'settings', to: { name: 'settings' } });
        }

        if (session.can('logs.view')) {
            admin.push({ label: 'Журнал действий', icon: 'clock', to: { name: 'logs' } });
        }

        groups.push({ label: 'Администрирование', items: [...admin, ...extras('Администрирование')] });

        const ungrouped = custom.filter((item) => !item.group);

        if (ungrouped.length) {
            groups.push({ label: 'Дополнительно', items: ungrouped });
        }

        return groups.filter((group) => group.items.length > 0);
    });
}
