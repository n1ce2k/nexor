<script setup>
import { computed, onMounted, ref } from 'vue';
import MenuBranch from './MenuBranch.vue';
import MenuItemForm from './MenuItemForm.vue';
import NButton from '../../components/ui/NButton.vue';
import NCard from '../../components/ui/NCard.vue';
import NEmpty from '../../components/ui/NEmpty.vue';
import NField from '../../components/ui/NField.vue';
import NIcon from '../../components/ui/NIcon.vue';
import NInput from '../../components/ui/NInput.vue';
import NModal from '../../components/ui/NModal.vue';
import NPageHeader from '../../components/ui/NPageHeader.vue';
import NToggle from '../../components/ui/NToggle.vue';
import { api } from '../../api';
import { slugify, useForm } from '../../composables/useForm';
import { useSession } from '../../stores/session';
import { useUi } from '../../stores/ui';

/**
 * Меню сайта: слева список меню, справа дерево его пунктов.
 *
 * Дерево правится перетаскиванием и кнопками; после любой перестановки на
 * сервер уезжает весь порядок разом — одно перетаскивание меняет и родителя, и
 * позицию сразу у нескольких пунктов.
 */
const session = useSession();
const ui = useUi();

const menus = ref([]);
const current = ref(null);
const items = ref([]);
const types = ref([]);
const visibility = ref([]);
const loading = ref(true);
const savingOrder = ref(false);

const menuOpen = ref(false);
const itemOpen = ref(false);
const editingItem = ref(null);

const menuForm = useForm({ code: '', name: '', description: '', is_active: true, sort: 500 });
const codeTouched = ref(false);

const canEdit = computed(() => session.can('menus.update'));
const canCreate = computed(() => session.can('menus.create'));

async function load() {
    loading.value = true;

    try {
        const [list, meta] = await Promise.all([api.get('menus'), api.get('menu-meta')]);

        menus.value = list.data;
        types.value = meta.types.map((one) => ({ ...one, label: one.label }));
        visibility.value = meta.visibility;

        if (menus.value.length) {
            await select(current.value?.id ?? menus.value[0].id);
        }
    } catch (error) {
        ui.notifyError(error);
    } finally {
        loading.value = false;
    }
}

async function select(id) {
    try {
        const data = await api.get(`menus/${id}`);

        current.value = data.data;
        items.value = toTree(data.data.items ?? []);
    } catch (error) {
        ui.notifyError(error);
    }
}

/** Плоский список из API в дерево, с которым работает перетаскивание. */
function toTree(flat) {
    const byId = {};
    const roots = [];

    flat.forEach((item) => {
        byId[item.id] = { ...item, children: [] };
    });

    flat.forEach((item) => {
        const node = byId[item.id];

        item.parent_id && byId[item.parent_id]
            ? byId[item.parent_id].children.push(node)
            : roots.push(node);
    });

    return roots;
}

/** Дерево обратно в плоский список: порядок обхода и есть новый порядок. */
function toFlat(nodes, parentId = null, into = []) {
    nodes.forEach((node) => {
        into.push({ id: node.id, parent_id: parentId });
        toFlat(node.children ?? [], node.id, into);
    });

    return into;
}

async function saveOrder() {
    if (!canEdit.value) {
        return;
    }

    savingOrder.value = true;

    try {
        const data = await api.put(`menus/${current.value.id}/reorder`, { items: toFlat(items.value) });

        items.value = toTree(data.items);
    } catch (error) {
        ui.notifyError(error);
        await select(current.value.id);
    } finally {
        savingOrder.value = false;
    }
}

// ------------------------------------------------------------ кнопки-стрелки

/** Список, в котором лежит пункт, и его позиция там. */
function locate(node, nodes = items.value, parent = null) {
    const index = nodes.findIndex((one) => one.id === node.id);

    if (index !== -1) {
        return { list: nodes, index, parent };
    }

    for (const one of nodes) {
        const found = locate(node, one.children ?? [], one);

        if (found) {
            return found;
        }
    }

    return null;
}

function move({ node, delta }) {
    const at = locate(node);
    const target = at.index + delta;

    if (target < 0 || target >= at.list.length) {
        return;
    }

    at.list.splice(target, 0, ...at.list.splice(at.index, 1));
    saveOrder();
}

function nest({ node, into }) {
    const at = locate(node);

    if (into) {
        // Вкладываем в соседа сверху — тот, кто выше, становится родителем.
        const above = at.list[at.index - 1];

        if (!above || above.accepts_children === false) {
            ui.notify('Пункт выше не может содержать вложенные.', 'error');

            return;
        }

        at.list.splice(at.index, 1);
        (above.children ??= []).push(node);
    } else {
        if (!at.parent) {
            return;
        }

        const parentAt = locate(at.parent);

        at.list.splice(at.index, 1);
        parentAt.list.splice(parentAt.index + 1, 0, node);
    }

    saveOrder();
}

// ------------------------------------------------------------------ пункты

function addItem() {
    editingItem.value = null;
    itemOpen.value = true;
}

function editItem(node) {
    editingItem.value = node;
    itemOpen.value = true;
}

async function removeItem(node) {
    const confirmed = await ui.confirm({
        title: 'Удалить пункт?',
        message: node.children?.length
            ? `Пункт «${node.display_title}» и всё вложенное будут удалены.`
            : `Пункт «${node.display_title}» будет удалён.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`menus/${current.value.id}/items/${node.id}`);

        ui.notify(data.message);
        await select(current.value.id);
    } catch (error) {
        ui.notifyError(error);
    }
}

// -------------------------------------------------------------------- меню

function openMenu(menu = null) {
    codeTouched.value = Boolean(menu);

    menuForm.reset(menu
        ? {
            code: menu.code,
            name: menu.name,
            description: menu.description ?? '',
            is_active: menu.is_active,
            sort: menu.sort,
        }
        : { code: '', name: '', description: '', is_active: true, sort: 500 });

    editingMenu.value = menu;
    menuOpen.value = true;
}

const editingMenu = ref(null);

function onMenuName(value) {
    menuForm.fields.name = value;

    if (!codeTouched.value) {
        menuForm.fields.code = slugify(value);
    }
}

async function saveMenu() {
    const data = await menuForm.submit(
        editingMenu.value ? 'put' : 'post',
        editingMenu.value ? `menus/${editingMenu.value.id}` : 'menus',
    );

    if (data) {
        menuOpen.value = false;
        current.value = data.data;
        await load();
    }
}

async function removeMenu(menu) {
    const confirmed = await ui.confirm({
        title: 'Удалить меню?',
        message: `Меню «${menu.name}» и все его пункты будут удалены.`,
    });

    if (!confirmed) {
        return;
    }

    try {
        const data = await api.delete(`menus/${menu.id}`);

        ui.notify(data.message);
        current.value = null;
        await load();
    } catch (error) {
        ui.notifyError(error);
    }
}

onMounted(load);
</script>

<template>
    <div>
        <NPageHeader title="Меню"
                     description="Навигация сайта: шапка, подвал, боковая колонка."
                     :breadcrumbs="[{ label: 'Структура' }, { label: 'Меню' }]">
            <template #actions>
                <NButton v-if="canCreate" icon="plus" @click="openMenu()">Создать меню</NButton>
            </template>
        </NPageHeader>

        <NEmpty v-if="!loading && !menus.length" icon="menu" title="Меню пока нет"
                description="Создайте меню и соберите его из ссылок, страниц и разделов инфоблоков.">
            <NButton v-if="canCreate" icon="plus" @click="openMenu()">Создать меню</NButton>
        </NEmpty>

        <div v-else class="grid gap-6 lg:grid-cols-[18rem_1fr]">
            <NCard :padding="false">
                <ul class="divide-y divide-[var(--surface-border)]">
                    <li v-for="menu in menus" :key="menu.id">
                        <button type="button"
                                :class="['flex w-full items-center justify-between gap-2 px-4 py-3 text-left transition',
                                         current?.id === menu.id ? 'bg-[var(--surface-muted)]' : 'hover:bg-[var(--surface-muted)]']"
                                @click="select(menu.id)">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-[var(--text-strong)]">
                                    {{ menu.name }}
                                </span>
                                <code class="block truncate font-mono text-xs text-[var(--text-muted)]">
                                    {{ menu.code }}
                                </code>
                            </span>

                            <span class="shrink-0 text-xs text-[var(--text-faint)]">{{ menu.items_count }}</span>
                        </button>
                    </li>
                </ul>
            </NCard>

            <NCard v-if="current" :title="current.name" :code="current.code">
                <template #actions>
                    <span v-if="savingOrder" class="text-xs text-[var(--text-muted)]">сохраняем порядок…</span>

                    <NButton v-if="canEdit" size="sm" variant="secondary" icon="pencil" @click="openMenu(current)">
                        Настройки
                    </NButton>

                    <NButton v-if="canEdit" size="sm" icon="plus" @click="addItem">Добавить пункт</NButton>

                    <button v-if="session.can('menus.delete')" type="button" title="Удалить меню"
                            class="rounded-lg p-2 text-[var(--text-muted)] transition hover:text-red-600"
                            @click="removeMenu(current)">
                        <NIcon name="trash" size="size-4" />
                    </button>
                </template>

                <p v-if="!items.length" class="text-sm text-[var(--text-muted)]">
                    Пунктов пока нет. Добавьте ссылку, страницу или целую ветку разделов инфоблока.
                </p>

                <MenuBranch v-else :items="items" :disabled="!canEdit"
                            @change="saveOrder" @edit="editItem" @remove="removeItem"
                            @move="move" @nest="nest" />

                <p class="mt-4 text-xs text-[var(--text-muted)]">
                    Выводится так:
                    <code class="font-mono text-[var(--text-base)]">&lt;x-nexor::menu code="{{ current.code }}" /&gt;</code>
                </p>
            </NCard>
        </div>

        <MenuItemForm v-model="itemOpen" :menu="current" :item="editingItem"
                      :types="types" :visibility="visibility"
                      @saved="select(current.id)" />

        <NModal v-model="menuOpen" :title="editingMenu ? 'Настройки меню' : 'Новое меню'">
            <div class="space-y-5">
                <NField label="Название" required :error="menuForm.error('name')">
                    <NInput :model-value="menuForm.fields.name" @update:model-value="onMenuName" />
                </NField>

                <NField label="Код" required hint="По нему меню вызывается в шаблоне."
                        :error="menuForm.error('code')">
                    <NInput v-model="menuForm.fields.code" class="font-mono"
                            @update:model-value="codeTouched = true" />
                </NField>

                <NField label="Описание" :error="menuForm.error('description')">
                    <NInput v-model="menuForm.fields.description" />
                </NField>

                <div class="grid gap-5 sm:grid-cols-2">
                    <NField label="Сортировка" :error="menuForm.error('sort')">
                        <NInput v-model="menuForm.fields.sort" type="number" min="0" />
                    </NField>

                    <NField label="Активность">
                        <NToggle v-model="menuForm.fields.is_active" label="Выводится на сайте" />
                    </NField>
                </div>
            </div>

            <template #footer>
                <NButton variant="secondary" size="sm" @click="menuOpen = false">Отмена</NButton>
                <NButton size="sm" :loading="menuForm.busy.value" @click="saveMenu">Сохранить</NButton>
            </template>
        </NModal>
    </div>
</template>
