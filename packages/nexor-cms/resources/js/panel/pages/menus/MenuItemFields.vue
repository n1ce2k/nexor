<script setup>
import NField from '../../components/ui/NField.vue';
import NInput from '../../components/ui/NInput.vue';
import NSelect from '../../components/ui/NSelect.vue';
import NToggle from '../../components/ui/NToggle.vue';

/**
 * Поля пункта меню — без обёртки.
 *
 * Одни и те же поля показываются в окне (пункт в корне меню) и прямо в списке
 * ссылок подменю. Вся логика — загрузка инфоблока, сохранение — у формы, здесь
 * только разметка.
 */
defineProps({
    form: { type: Object, required: true },
    shows: { type: Object, required: true },
    types: { type: Array, default: () => [] },
    visibility: { type: Array, default: () => [] },
    typeHint: { type: String, default: null },
    titleHint: { type: String, default: null },
    iblockOptions: { type: Array, default: () => [] },
    sectionedOnly: { type: Array, default: () => [] },
    elements: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    loadingSource: { type: Boolean, default: false },
});
</script>

<template>
    <div class="space-y-5">
        <NField label="Тип пункта" required :hint="typeHint" :error="form.error('type')">
            <NSelect v-model="form.fields.type" :options="types" />
        </NField>

        <NField v-if="shows.iblock" label="Инфоблок" required :error="form.error('iblock_id')">
            <NSelect v-model="form.fields.iblock_id"
                     :options="shows.root ? sectionedOnly : iblockOptions"
                     placeholder="Выберите инфоблок" />
        </NField>

        <NField v-if="shows.element" label="Страница" required
                hint="Адрес считается сам и переживёт переименование кода."
                :error="form.error('element_id')">
            <NSelect v-model="form.fields.element_id" :options="elements"
                     :placeholder="loadingSource ? 'Загружаем…' : 'Выберите страницу'" />
        </NField>

        <NField v-if="shows.section" label="Раздел" required :error="form.error('section_id')">
            <NSelect v-model="form.fields.section_id" :options="sections"
                     :placeholder="loadingSource ? 'Загружаем…' : 'Выберите раздел'" />
        </NField>

        <NField v-if="shows.root" label="От какого раздела" hint="Пусто — от корня инфоблока."
                :error="form.error('section_id')">
            <NSelect v-model="form.fields.section_id" :options="sections"
                     placeholder="— весь инфоблок —" />
        </NField>

        <div v-if="shows.depth" class="grid gap-5 sm:grid-cols-2">
            <NField label="Глубина" :hint="shows.depthHint" :error="form.error('max_depth')">
                <NInput v-model="form.fields.max_depth" type="number" min="1" max="5" />
            </NField>

            <NField v-if="shows.root" label="Элементы">
                <NToggle v-model="form.fields.with_elements" label="Показывать и элементы" />
            </NField>
        </div>

        <NField v-if="shows.withTitle" label="Свой пункт" :hint="shows.withTitleHint">
            <NToggle v-model="form.fields.with_title" label="Выводить название" />
        </NField>

        <NField v-if="shows.title" :label="shows.titleRequired ? 'Название' : 'Название (необязательно)'"
                :required="shows.titleRequired"
                :hint="titleHint"
                :error="form.error('title')">
            <NInput v-model="form.fields.title" />
        </NField>

        <NField v-if="shows.url" :label="shows.urlRequired ? 'Адрес' : 'Адрес (необязательно)'"
                :required="shows.urlRequired"
                :hint="shows.urlHint"
                :error="form.error('url')">
            <NInput v-model="form.fields.url" class="font-mono" placeholder="/katalog" />
        </NField>

        <div v-if="shows.link" class="grid gap-5 sm:grid-cols-2">
            <NField label="Открывать">
                <NSelect v-model="form.fields.target"
                         :options="[{ value: '', label: 'В этой вкладке' }, { value: '_blank', label: 'В новой вкладке' }]" />
            </NField>

            <NField label="Подсветка">
                <NToggle v-model="form.fields.highlight_children"
                         label="Активен и на вложенных страницах" />
            </NField>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <NField label="Кому виден" :error="form.error('visibility')">
                <NSelect v-model="form.fields.visibility" :options="visibility" />
            </NField>

            <NField label="Активность">
                <NToggle v-model="form.fields.is_active" label="Показывать на сайте" />
            </NField>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <NField label="CSS-класс" :error="form.error('css_class')">
                <NInput v-model="form.fields.css_class" class="font-mono" />
            </NField>

            <NField label="Иконка" hint="Имя иконки для вашего шаблона." :error="form.error('icon')">
                <NInput v-model="form.fields.icon" class="font-mono" />
            </NField>
        </div>
    </div>
</template>
