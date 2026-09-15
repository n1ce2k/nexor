import * as Vue from 'vue';
import * as VueRouter from 'vue-router';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import Draggable from 'vuedraggable';

import App from './App.vue';
import { createPanelRouter } from './router';
import { api, ApiError, toFormData } from './api';
import { slugify, useForm } from './composables/useForm';
import * as registry from './registry';
import { useSession } from './stores/session';
import { useUi } from './stores/ui';

import NBadge from './components/ui/NBadge.vue';
import NButton from './components/ui/NButton.vue';
import NCard from './components/ui/NCard.vue';
import NEditor from './components/ui/NEditor.vue';
import NEmpty from './components/ui/NEmpty.vue';
import NField from './components/ui/NField.vue';
import NHtmlInput from './components/ui/NHtmlInput.vue';
import NIcon from './components/ui/NIcon.vue';
import NInput from './components/ui/NInput.vue';
import NModal from './components/ui/NModal.vue';
import NPageHeader from './components/ui/NPageHeader.vue';
import NPagination from './components/ui/NPagination.vue';
import NSelect from './components/ui/NSelect.vue';
import NTable from './components/ui/NTable.vue';
import NTabs from './components/ui/NTabs.vue';
import NToggle from './components/ui/NToggle.vue';

import FieldBoolean from './components/fields/FieldBoolean.vue';
import FieldFile from './components/fields/FieldFile.vue';
import FieldHtml from './components/fields/FieldHtml.vue';
import FieldSelect from './components/fields/FieldSelect.vue';
import FieldText from './components/fields/FieldText.vue';
import FieldTextarea from './components/fields/FieldTextarea.vue';

/**
 * Built-in property editors. Registered exactly the way a host application
 * would register its own, so nothing here is privileged.
 */
function registerBuiltInFields() {
    ['string', 'integer', 'decimal', 'date', 'datetime', 'color'].forEach((type) => {
        registry.registerField(type, FieldText);
    });

    ['text', 'json'].forEach((type) => registry.registerField(type, FieldTextarea));

    // HTML-свойства правятся визуальным редактором, с переключением в исходник.
    registry.registerField('html', FieldHtml);

    registry.registerField('boolean', FieldBoolean);

    ['select', 'element', 'section', 'user'].forEach((type) => registry.registerField(type, FieldSelect));

    ['file', 'image'].forEach((type) => registry.registerField(type, FieldFile));
}

/**
 * Public surface for host applications. Extensions register against this
 * before `mount()` is called from the panel's Blade shell.
 */
const Nexor = {
    api,
    ApiError,
    toFormData,
    useForm,
    slugify,

    /**
     * Модули собираются отдельно от панели и берут Vue и роутер отсюда: две копии
     * Vue на одной странице не видят реактивность и хранилища друг друга.
     */
    vendor: { vue: Vue, vueRouter: VueRouter, draggable: Draggable },

    /** UI-кит панели — чтобы страницы модулей выглядели как родные. */
    ui: {
        NBadge, NButton, NCard, NEditor, NEmpty, NField, NHtmlInput, NIcon, NInput,
        NModal, NPageHeader, NPagination, NSelect, NTable, NTabs, NToggle,
    },

    registry: registry.registry,
    registerField: registry.registerField,
    registerPage: registry.registerPage,
    registerMenuItem: registry.registerMenuItem,
    registerColumn: registry.registerColumn,
    registerFormField: registry.registerFormField,
    on: registry.on,
    emit: registry.emit,
    stores: { useSession, useUi },
    booted: false,

    /** Deferred callbacks let a late-loading bundle still register in time. */
    ready(callback) {
        this.booted ? callback(this) : queue.push(callback);
    },

    mount(selector = '#nexor-panel') {
        const element = document.querySelector(selector);

        if (!element) {
            return null;
        }

        registerBuiltInFields();
        queue.forEach((callback) => callback(this));
        this.booted = true;

        const app = createApp(App);

        app.use(createPinia());
        app.use(createPanelRouter(element.dataset.base ?? '/admin/vue'));
        app.mount(element);

        return app;
    },
};

const queue = [];

window.Nexor = Nexor;

// The shell calls mount() itself once extension bundles have loaded.
if (document.querySelector('#nexor-panel')?.dataset.autoMount !== 'false') {
    document.addEventListener('DOMContentLoaded', () => Nexor.mount());
}

export default Nexor;
