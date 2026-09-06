<script setup>
import NCard from '../../components/ui/NCard.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import SettingsEditor from '../../components/SettingsEditor.vue';
</script>

<template>
    <div>
        <NPageHeader title="SMTP почта"
                     description="Параметры отправки писем. Пока переключатель выключен, письма уходят через настройки из .env." />

        <div class="mb-6">
            <NCard>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        <NIcon name="info" size="size-4" />
                    </span>

                    <div class="min-w-0 text-sm text-[var(--text-muted)]">
                        <p class="font-medium text-[var(--text-strong)]">Как проверить</p>
                        <p class="mt-1">
                            Заполните сервер, порт и учётные данные, сохраните, затем откройте
                            <router-link :to="{ name: 'mail.templates' }"
                                         class="text-brand-600 hover:underline dark:text-brand-400">почтовые шаблоны</router-link>
                            и отправьте тестовое письмо на свой адрес.
                        </p>
                        <p class="mt-1">Пароль хранится в базе в зашифрованном виде и не возвращается в браузер.</p>
                    </div>
                </div>
            </NCard>
        </div>

        <SettingsEditor :groups="['mail']" default-group="mail" />
    </div>
</template>
