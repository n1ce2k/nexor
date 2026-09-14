/**
 * Всё, что страницам магазина нужно из панели ядра, — одним местом.
 *
 * Пакеты лежат рядом (packages/ или vendor/n1ce2k/), поэтому относительный путь
 * одинаков в обоих случаях. Переедет ядро — поправить только здесь.
 */
export { api, toFormData } from '../../../nexor-cms/resources/js/panel/api.js';
export { registerPage } from '../../../nexor-cms/resources/js/panel/registry.js';
export { useForm } from '../../../nexor-cms/resources/js/panel/composables/useForm.js';
export { useSession } from '../../../nexor-cms/resources/js/panel/stores/session.js';
export { useUi } from '../../../nexor-cms/resources/js/panel/stores/ui.js';

export { default as NBadge } from '../../../nexor-cms/resources/js/panel/components/ui/NBadge.vue';
export { default as NButton } from '../../../nexor-cms/resources/js/panel/components/ui/NButton.vue';
export { default as NCard } from '../../../nexor-cms/resources/js/panel/components/ui/NCard.vue';
export { default as NEmpty } from '../../../nexor-cms/resources/js/panel/components/ui/NEmpty.vue';
export { default as NField } from '../../../nexor-cms/resources/js/panel/components/ui/NField.vue';
export { default as NIcon } from '../../../nexor-cms/resources/js/panel/components/ui/NIcon.vue';
export { default as NInput } from '../../../nexor-cms/resources/js/panel/components/ui/NInput.vue';
export { default as NModal } from '../../../nexor-cms/resources/js/panel/components/ui/NModal.vue';
export { default as NPageHeader } from '../../../nexor-cms/resources/js/panel/components/ui/NPageHeader.vue';
export { default as NPagination } from '../../../nexor-cms/resources/js/panel/components/ui/NPagination.vue';
export { default as NSelect } from '../../../nexor-cms/resources/js/panel/components/ui/NSelect.vue';
export { default as NTable } from '../../../nexor-cms/resources/js/panel/components/ui/NTable.vue';
export { default as NTabs } from '../../../nexor-cms/resources/js/panel/components/ui/NTabs.vue';
export { default as NToggle } from '../../../nexor-cms/resources/js/panel/components/ui/NToggle.vue';

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
