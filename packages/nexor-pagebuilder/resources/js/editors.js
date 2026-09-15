import { markRaw, shallowReactive } from 'vue';

/**
 * Редакторы блоков по типу. Свой блок сайта регистрирует свой редактор:
 *
 *     window.Nexor.pageBuilder.registerEditor('promo', PromoEditor)
 *
 * Контракт: prop `data` — реактивный объект данных блока (правится на месте),
 * `iblock` — схема инфоблока (нужна загрузке картинок).
 */
const editors = shallowReactive({});

export function registerEditor(type, component) {
    editors[type] = markRaw(component);
}

export function resolveEditor(type) {
    return editors[type] ?? null;
}
