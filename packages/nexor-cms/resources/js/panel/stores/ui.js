import { defineStore } from 'pinia';

const THEME_KEY = 'admin.theme';
const SIDEBAR_KEY = 'admin.sidebar.collapsed';

function preferredTheme() {
    const stored = localStorage.getItem(THEME_KEY);

    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/**
 * Chrome state shared by every screen: theme, sidebar, toasts, confirmations.
 */
export const useUi = defineStore('ui', {
    state: () => ({
        theme: preferredTheme(),
        sidebarCollapsed: localStorage.getItem(SIDEBAR_KEY) === '1',
        mobileOpen: false,
        // A screen that needs the room — the section tree beside a list — asks
        // the layout to drop its reading-width cap while it is open.
        wideContent: false,
        toasts: [],
        nextToastId: 1,
        confirmation: null,
    }),

    actions: {
        applyTheme() {
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            document.documentElement.style.colorScheme = this.theme;
        },

        setTheme(theme) {
            this.theme = theme;
            localStorage.setItem(THEME_KEY, theme);
            this.applyTheme();
        },

        toggleTheme() {
            this.setTheme(this.theme === 'dark' ? 'light' : 'dark');
        },

        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem(SIDEBAR_KEY, this.sidebarCollapsed ? '1' : '0');
        },

        notify(message, type = 'success', timeout = 5000) {
            const id = this.nextToastId++;

            this.toasts.push({ id, message, type });

            if (timeout) {
                setTimeout(() => this.dismiss(id), timeout);
            }
        },

        /** Surfaces an ApiError without leaking stack traces into the UI. */
        notifyError(error) {
            this.notify(error?.message ?? 'Что-то пошло не так', 'error', 8000);
        },

        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },

        /**
         * Opens the confirmation modal and resolves once the user answers.
         *
         * @returns {Promise<boolean>}
         */
        confirm({ title = 'Подтвердите действие', message = '', confirmLabel = 'Удалить', danger = true }) {
            return new Promise((resolve) => {
                this.confirmation = {
                    title,
                    message,
                    confirmLabel,
                    danger,
                    resolve: (answer) => {
                        this.confirmation = null;
                        resolve(answer);
                    },
                };
            });
        },
    },
});
