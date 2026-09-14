import '../css/panel.css';
import { registerPage } from './core.js';

import CartSettings from './pages/CartSettings.vue';
import CheckoutSettings from './pages/CheckoutSettings.vue';
import OrderIndex from './pages/OrderIndex.vue';
import OrderShow from './pages/OrderShow.vue';
import PromocodeIndex from './pages/PromocodeIndex.vue';

/**
 * Страницы модуля «Магазин» в панели.
 *
 * Каждая привязана к функции лицензии: выключили модуль или лицензия не
 * покрывает промокоды — пункт меню и страница пропадают сами.
 */
const group = 'Магазин';

registerPage({
    path: '/shop/orders',
    name: 'shop.orders',
    component: OrderIndex,
    permission: 'shop.orders.view',
    feature: 'shop',
    props: false,
    menu: { label: 'Заказы', icon: 'receipt', group },
});

registerPage({
    path: '/shop/orders/:order',
    name: 'shop.orders.show',
    component: OrderShow,
    permission: 'shop.orders.view',
    feature: 'shop',
});

registerPage({
    path: '/shop/cart',
    name: 'shop.cart',
    component: CartSettings,
    permission: 'shop.cart.view',
    feature: 'shop',
    props: false,
    menu: { label: 'Корзина', icon: 'cart', group },
});

registerPage({
    path: '/shop/promocodes',
    name: 'shop.promocodes',
    component: PromocodeIndex,
    permission: 'shop.promocodes.view',
    feature: 'shop.promocodes',
    props: false,
    menu: { label: 'Промокоды', icon: 'tag', group },
});

registerPage({
    path: '/shop/checkout',
    name: 'shop.checkout',
    component: CheckoutSettings,
    permission: 'shop.checkout.view',
    feature: 'shop.checkout',
    props: false,
    menu: { label: 'Доставка и оплата', icon: 'truck', group },
});
