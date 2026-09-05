<script setup>
import NButton from '../components/ui/NButton.vue';
import NEmpty from '../components/ui/NEmpty.vue';
</script>

<template>
    <NEmpty icon="alert" title="Раздел не найден"
            description="Такой страницы в панели нет — возможно, ссылка устарела.">
        <NButton :to="{ name: 'dashboard' }">На рабочий стол</NButton>
    </NEmpty>
</template>
