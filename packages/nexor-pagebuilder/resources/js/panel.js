import { registerFormField } from './core.js';
import { registerEditor, resolveEditor } from './editors.js';

import BuilderField from './components/BuilderField.vue';
import AccordionEditor from './editors/AccordionEditor.vue';
import HeaderEditor from './editors/HeaderEditor.vue';
import PhotoEditor from './editors/PhotoEditor.vue';
import QuoteEditor from './editors/QuoteEditor.vue';
import TableEditor from './editors/TableEditor.vue';
import TextEditor from './editors/TextEditor.vue';
import TextImageEditor from './editors/TextImageEditor.vue';
import VideoEditor from './editors/VideoEditor.vue';

/**
 * Модуль «Конструктор страниц» в панели.
 *
 * Вкладку «Конструктор» в форму элемента кладёт сервер (когда у инфоблока
 * включён конструктор), а здесь регистрируется компонент, который её рисует.
 */
registerEditor('header', HeaderEditor);
registerEditor('text', TextEditor);
registerEditor('quote', QuoteEditor);
registerEditor('text_image', TextImageEditor);
registerEditor('photo', PhotoEditor);
registerEditor('video', VideoEditor);
registerEditor('accordion', AccordionEditor);
registerEditor('table', TableEditor);

registerFormField('pagebuilder.content', BuilderField);

// Точка расширения для своих блоков сайта.
window.Nexor.pageBuilder = { registerEditor, resolveEditor };
