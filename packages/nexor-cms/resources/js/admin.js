import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

import './admin/theme';
import './admin/stores';
import './admin/components';

Alpine.plugin(collapse);
Alpine.plugin(focus);

window.Alpine = Alpine;

Alpine.start();
