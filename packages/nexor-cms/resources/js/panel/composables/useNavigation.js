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

        groups.push({
            label: null,
            items: [{ label: 'Рабочий стол', icon: 'dashboard', to: { name: 'dashboard' } }],
        });

        // Content: one entry per infoblock, nested under its type.
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

        const custom = registry.menuItems.filter((item) => !item.permission || session.can(item.permission));

        const contentExtras = custom.filter((item) => item.group === 'Контент');

        if (content.length || contentExtras.length) {
            groups.push({ label: 'Контент', items: [...content, ...contentExtras] });
        }

        const structure = [];

        if (session.can('iblocks.view')) {
            structure.push({ label: 'Инфоблоки', icon: 'layers', to: { name: 'iblocks.index' } });
        }

        if (session.can('iblock_types.view')) {
            structure.push({ label: 'Типы инфоблоков', icon: 'database', to: { name: 'iblock-types.index' } });
        }

        structure.push(...custom.filter((item) => item.group === 'Структура'));

        if (structure.length) {
            groups.push({ label: 'Структура', items: structure });
        }

        const admin = [];

        if (session.can('users.view')) {
            admin.push({ label: 'Пользователи', icon: 'users', to: { name: 'users.index' } });
        }

        if (session.can('roles.view')) {
            admin.push({ label: 'Роли и права', icon: 'shield', to: { name: 'roles.index' } });
        }

        if (session.can('settings.view')) {
            admin.push({ label: 'Настройки', icon: 'settings', to: { name: 'settings' } });
        }

        if (session.can('logs.view')) {
            admin.push({ label: 'Журнал действий', icon: 'clock', to: { name: 'logs' } });
        }

        admin.push(...custom.filter((item) => item.group === 'Администрирование'));

        if (admin.length) {
            groups.push({ label: 'Администрирование', items: admin });
        }

        const ungrouped = custom.filter((item) => !item.group);

        if (ungrouped.length) {
            groups.push({ label: 'Дополнительно', items: ungrouped });
        }

        return groups.filter((group) => group.items.length > 0);
    });
}
