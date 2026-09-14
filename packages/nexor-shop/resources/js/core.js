/**
 * Всё, что страницам магазина нужно из панели ядра, — через window.Nexor.
 *
 * Магазин собирается отдельно от панели и приходит в vendor готовым, поэтому
 * не импортирует файлы ядра напрямую: иначе в сборке оказалась бы своя копия
 * Vue, Pinia и UI-кита. Панель к моменту загрузки модуля уже опубликовала всё
 * это в window.Nexor (скрипт ядра стоит на странице раньше).
 */
const Nexor = window.Nexor;

export const api = Nexor.api;
export const toFormData = Nexor.toFormData;
export const registerPage = Nexor.registerPage;
export const useForm = Nexor.useForm;
export const useSession = Nexor.stores.useSession;
export const useUi = Nexor.stores.useUi;

export const {
    NBadge, NButton, NCard, NEmpty, NField, NIcon, NInput,
    NModal, NPageHeader, NPagination, NSelect, NTable, NTabs, NToggle,
} = Nexor.ui;

const money = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 });

/** «1 800» — копейки только если они есть. */
export function formatMoney(value) {
    return money.format(Number(value ?? 0));
}

/** «2.500» → «2.5»: количество без хвостовых нулей. */
export function formatQuantity(value) {
    return String(Number(value ?? 0));
}

export function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' }) : '';
}
