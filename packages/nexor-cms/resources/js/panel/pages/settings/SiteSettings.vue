<script setup>
import NPageHeader from '../../components/ui/NPageHeader.vue';
import SettingsEditor from '../../components/SettingsEditor.vue';
</script>

<template>
    <div>
        <NPageHeader title="Настройки сайта"
                     description="Общие параметры, контакты и SEO. Своё свойство можно добавить в любую группу." />

        <SettingsEditor :groups="['general', 'contacts', 'seo']" default-group="contacts" />
    </div>
</template>
