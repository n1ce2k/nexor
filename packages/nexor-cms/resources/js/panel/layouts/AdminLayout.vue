<script setup>
import { ref } from 'vue';
import NIcon from '../components/ui/NIcon.vue';
import { useNavigation } from '../composables/useNavigation';
import { useSession } from '../stores/session';
import { useUi } from '../stores/ui';

const session = useSession();
const ui = useUi();
const groups = useNavigation();

const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const openGroups = ref({});
const userMenu = ref(false);

function toggleGroup(label) {
    if (ui.sidebarCollapsed) {
        ui.toggleSidebar();

        return;
    }

    openGroups.value[label] = !(openGroups.value[label] ?? true);
}

function isOpen(label) {
    return openGroups.value[label] ?? true;
}
</script>

<template>
    <div class="flex min-h-full">
        <div v-if="ui.mobileOpen" class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"
             @click="ui.mobileOpen = false"></div>

        <aside class="sidebar fixed inset-y-0 left-0 z-50 flex flex-col border-r transition-all duration-200 lg:translate-x-0"
               :class="[ui.sidebarCollapsed ? 'w-[4.5rem]' : 'w-64',
                        ui.mobileOpen ? 'translate-x-0' : '-translate-x-full']">
            <div class="flex h-16 shrink-0 items-center gap-2.5 px-4">
                <router-link :to="{ name: 'dashboard' }" class="flex min-w-0 items-center gap-2.5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold text-white">
                        {{ session.brand.initial }}
                    </span>
                    <span v-if="!ui.sidebarCollapsed" class="min-w-0 truncate text-sm font-semibold text-white">
                        {{ session.brand.site_name }}
                    </span>
                </router-link>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-4">

                <div v-for="(group, index) in groups" :key="index">
                    <p v-if="group.label && !ui.sidebarCollapsed"
                       class="sidebar-label px-3 pb-2 text-[0.65rem] font-semibold tracking-wider uppercase">
                        {{ group.label }}
                    </p>

                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.label">
                            <template v-if="item.children">
                                <button type="button" class="sidebar-link w-full" @click="toggleGroup(item.label)">
                                    <NIcon :name="item.icon" size="size-5 shrink-0" />
                                    <span v-if="!ui.sidebarCollapsed" class="min-w-0 flex-1 truncate text-left">
                                        {{ item.label }}
                                    </span>
                                    <NIcon v-if="!ui.sidebarCollapsed" name="chevron-down"
                                           size="size-3.5 shrink-0 transition"
                                           :class="isOpen(item.label) && 'rotate-180'" />
                                </button>

                                <ul v-if="isOpen(item.label) && !ui.sidebarCollapsed"
                                    class="sidebar-sublist mt-0.5 ml-5 space-y-0.5 border-l pl-3">
                                    <li v-for="child in item.children" :key="child.label">
                                        <router-link :to="child.to" class="sidebar-link block truncate py-1.5"
                                                     active-class="is-active">
                                            {{ child.label }}
                                        </router-link>
                                    </li>
                                </ul>
                            </template>

                            <router-link v-else :to="item.to" class="sidebar-link" active-class="is-active"
                                         :title="ui.sidebarCollapsed ? item.label : null">
                                <NIcon :name="item.icon" size="size-5 shrink-0" />
                                <span v-if="!ui.sidebarCollapsed" class="min-w-0 truncate">{{ item.label }}</span>
                            </router-link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="sidebar-footer shrink-0 border-t p-3">
                <button type="button" class="sidebar-link hidden w-full lg:flex" @click="ui.toggleSidebar()">
                    <NIcon name="chevron-left" size="size-5 shrink-0 transition"
                           :class="ui.sidebarCollapsed && 'rotate-180'" />
                    <span v-if="!ui.sidebarCollapsed">Свернуть меню</span>
                </button>
            </div>

        </aside>

        <div class="flex min-w-0 flex-1 flex-col transition-all duration-200"
             :class="ui.sidebarCollapsed ? 'lg:pl-[4.5rem]' : 'lg:pl-64'">
            <header class="surface sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b px-4 sm:px-6 lg:px-8">
                <button type="button" class="-ml-1 rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] lg:hidden"
                        @click="ui.mobileOpen = true">
                    <NIcon name="menu" />
                </button>

                <div class="min-w-0 flex-1"></div>

                <a :href="session.routes.home" target="_blank" rel="noopener"
                   class="hidden items-center gap-2 rounded-lg px-3 py-2 text-sm text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)] sm:flex">
                    <NIcon name="eye" size="size-4" />
                    Сайт
                </a>

                <button type="button" title="Сменить тему"
                        class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
                        @click="ui.toggleTheme()">
                    <NIcon :name="ui.theme === 'dark' ? 'sun' : 'moon'" />
                </button>

                <div class="relative">
                    <button type="button" class="flex items-center gap-2.5 rounded-lg p-1 pr-2 transition hover:bg-[var(--surface-muted)]"
                            @click="userMenu = !userMenu">
                        <img v-if="session.user?.avatar_url" :src="session.user.avatar_url" alt=""
                             class="size-8 rounded-full object-cover">
                        <span v-else class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                            {{ session.user?.initials }}
                        </span>
                        <span class="hidden max-w-32 truncate text-sm font-medium text-[var(--text-strong)] sm:block">
                            {{ session.user?.name }}
                        </span>
                        <NIcon name="chevron-down" size="size-3.5 text-[var(--text-faint)]" />
                    </button>

                    <div v-if="userMenu" class="fixed inset-0 z-40" @click="userMenu = false"></div>

                    <div v-if="userMenu"
                         class="surface absolute right-0 z-50 mt-2 w-56 rounded-xl border p-1 shadow-lg">
                        <div class="border-b border-[var(--surface-border)] px-3 py-2">
                            <p class="truncate text-sm font-medium text-[var(--text-strong)]">{{ session.user?.name }}</p>
                            <p class="truncate text-xs text-[var(--text-muted)]">{{ session.user?.email }}</p>
                        </div>

                        <div class="pt-1">
                            <a :href="session.routes.classic"
                               class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-[var(--text-base)] transition hover:bg-[var(--surface-muted)]">
                                <NIcon name="grip" size="size-4" />
                                Классическая админка
                            </a>

                            <form :action="session.routes.logout" method="POST">
                                <input type="hidden" name="_token" :value="csrf">
                                <button type="submit"
                                        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                    <NIcon name="logout" size="size-4" />
                                    Выйти
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto w-full transition-[max-width]" :class="ui.wideContent ? 'max-w-[112rem]' : 'max-w-7xl'">
                    <router-view v-slot="{ Component }">
                        <component :is="Component" />
                    </router-view>
                </div>
            </main>

            <footer class="px-4 py-5 text-center text-xs text-[var(--text-faint)] sm:px-6 lg:px-8">
<!--                {{ session.brand.site_name }}-->
<!--                <br>-->
<!--                <div>n1ce</div>-->
            </footer>
        </div>
    </div>
</template>
