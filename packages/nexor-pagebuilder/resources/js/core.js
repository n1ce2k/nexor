/**
 * Всё, что редактору конструктора нужно из панели ядра, — через window.Nexor.
 *
 * Модуль собирается отдельно и приходит в vendor готовым, поэтому не
 * импортирует файлы ядра напрямую: Vue, перетаскивание, визуальный редактор и
 * UI-кит берутся у панели, иначе на странице оказались бы их вторые копии.
 */
const Nexor = window.Nexor;

export const api = Nexor.api;
export const registerFormField = Nexor.registerFormField;
export const useUi = Nexor.stores.useUi;
export const Draggable = Nexor.vendor.draggable;

export const {
    NButton, NField, NHtmlInput, NIcon, NInput, NSelect, NToggle,
} = Nexor.ui;

/** Короткий id блока — чтобы Vue и перетаскивание не путали блоки. */
export function blockId() {
    return Math.random().toString(36).slice(2, 12);
}
