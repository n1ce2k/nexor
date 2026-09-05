import Alpine from 'alpinejs';
import { resolveTheme, setTheme } from './theme';

const SIDEBAR_KEY = 'admin.sidebar.collapsed';

Alpine.store('theme', {
    current: resolveTheme(),

    set(theme) {
        setTheme(theme);
        this.current = resolveTheme();
    },

    toggle() {
        this.set(this.current === 'dark' ? 'light' : 'dark');
    },
});

Alpine.store('sidebar', {
    collapsed: localStorage.getItem(SIDEBAR_KEY) === '1',
    mobileOpen: false,

    toggle() {
        this.collapsed = !this.collapsed;
        localStorage.setItem(SIDEBAR_KEY, this.collapsed ? '1' : '0');
    },
});

Alpine.store('toasts', {
    items: [],
    nextId: 1,

    push(message, type = 'success', timeout = 5000) {
        const id = this.nextId++;

        this.items.push({ id, message, type });

        if (timeout) {
            setTimeout(() => this.dismiss(id), timeout);
        }
    },

    dismiss(id) {
        this.items = this.items.filter((toast) => toast.id !== id);
    },
});

window.notify = (message, type = 'success') => Alpine.store('toasts').push(message, type);
