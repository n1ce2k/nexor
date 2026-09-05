const STORAGE_KEY = 'admin.theme';

/**
 * Resolve the theme to apply: an explicit choice wins, otherwise follow the OS.
 */
export function resolveTheme() {
    const stored = localStorage.getItem(STORAGE_KEY);

    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.style.colorScheme = theme;
}

export function setTheme(theme) {
    if (theme === 'system') {
        localStorage.removeItem(STORAGE_KEY);
    } else {
        localStorage.setItem(STORAGE_KEY, theme);
    }

    applyTheme(resolveTheme());
}

applyTheme(resolveTheme());

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (!localStorage.getItem(STORAGE_KEY)) {
        applyTheme(resolveTheme());
    }
});

window.adminTheme = { resolveTheme, applyTheme, setTheme, STORAGE_KEY };
