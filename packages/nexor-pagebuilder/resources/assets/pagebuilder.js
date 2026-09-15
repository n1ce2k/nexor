/**
 * Поведение блоков конструктора на сайте.
 *
 * Работает, только если обёртка вывелась со стилями модуля
 * (data-pb-standalone="true"): сайт со своими скриптами для pb-* его не
 * получает вовсе. Без зависимостей; Fancybox и Swiper подхватываются, если
 * сайт их подключил.
 */
(() => {
    const ACTIVE = 'data-active';

    /** Аккордеон: один пункт открыт, высота анимируется по содержимому. */
    function initAccordion(root) {
        if (root.dataset.accordionInitialized) {
            return;
        }

        const items = [...root.querySelectorAll('[data-accordion-item]')];

        const parts = (item) => ({
            trigger: item.querySelector('[data-accordion-trigger]'),
            body: item.querySelector('[data-accordion-body]'),
            content: item.querySelector('[data-accordion-content]'),
        });

        // Содержимое поменяло высоту (картинка догрузилась) — подстроить открытый пункт.
        const observer = new ResizeObserver((entries) => {
            entries.forEach(({ target }) => {
                const item = target.closest('[data-accordion-item]');

                if (item?.hasAttribute(ACTIVE)) {
                    parts(item).body.style.height = `${target.scrollHeight}px`;
                }
            });
        });

        const open = (item) => {
            const { trigger, body, content } = parts(item);

            [item, trigger, body].forEach((node) => node?.setAttribute(ACTIVE, ''));
            trigger?.setAttribute('aria-expanded', 'true');
            body?.setAttribute('aria-hidden', 'false');

            if (body && content) {
                body.style.height = `${content.scrollHeight}px`;
                observer.observe(content);
            }
        };

        const close = (item) => {
            const { trigger, body, content } = parts(item);

            [item, trigger, body].forEach((node) => node?.removeAttribute(ACTIVE));
            trigger?.setAttribute('aria-expanded', 'false');
            body?.setAttribute('aria-hidden', 'true');

            if (body) {
                body.style.height = '';
            }

            if (content) {
                observer.unobserve(content);
            }
        };

        const toggle = (item) => {
            if (item.hasAttribute(ACTIVE)) {
                close(item);

                return;
            }

            items.filter((other) => other !== item && other.hasAttribute(ACTIVE)).forEach(close);
            open(item);
        };

        items.forEach((item) => {
            const { trigger, body } = parts(item);

            if (trigger) {
                trigger.setAttribute('role', 'button');
                trigger.setAttribute('tabindex', '0');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    toggle(item);
                });
                trigger.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        toggle(item);
                    }
                });
            }

            body?.setAttribute('role', 'region');
            body?.setAttribute('aria-hidden', 'true');

            if (item.hasAttribute(ACTIVE)) {
                open(item);
            }
        });

        root.dataset.accordionInitialized = 'true';
    }

    /** Табы — задел под блок второй очереди, разметка как в аккордеоне. */
    function initTabs(root) {
        if (root.dataset.tabsInitialized) {
            return;
        }

        const triggers = [...root.querySelectorAll('[data-tab-target]')];
        const panes = [...root.querySelectorAll('[data-tab-content]')];

        const open = (id) => {
            triggers.forEach((trigger) => {
                const active = trigger.dataset.tabTarget === id;

                trigger.toggleAttribute(ACTIVE, active);
                trigger.setAttribute('aria-selected', String(active));
            });

            panes.forEach((pane) => {
                const active = pane.dataset.tabContent === id;

                pane.toggleAttribute(ACTIVE, active);
                pane.hidden = !active;
            });
        };

        triggers.forEach((trigger) => {
            trigger.setAttribute('role', 'tab');
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                open(trigger.dataset.tabTarget);
            });
        });

        const first = triggers.find((trigger) => trigger.hasAttribute(ACTIVE)) ?? triggers[0];

        if (first) {
            open(first.dataset.tabTarget);
        }

        root.dataset.tabsInitialized = 'true';
    }

    /** Оглавление: плавная прокрутка к блоку с поправкой на шапку сайта. */
    function initScrollLinks(wrapper) {
        wrapper.querySelectorAll('.js-scroll-to').forEach((link) => {
            link.addEventListener('click', (event) => {
                const target = document.querySelector(link.getAttribute('href'));

                if (!target) {
                    return;
                }

                event.preventDefault();

                const header = document.querySelector('.header') ?? document.querySelector('header');
                const offset = (header?.offsetHeight ?? 0) + 20;

                window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
                history.replaceState(null, '', link.getAttribute('href'));
            });
        });
    }

    function initGallery() {
        if (!window.Fancybox) {
            return;
        }

        try {
            window.Fancybox.unbind('[data-fancybox]');
        } catch {
            // Ещё ничего не привязано.
        }

        window.Fancybox.bind('[data-fancybox]', { closeButton: 'auto', DragToClose: false });
    }

    /** Слайдеры блока второй очереди — если сайт подключил Swiper. */
    function initSliders() {
        const Swiper = window.NwSwiper ?? window.Swiper;

        if (!Swiper) {
            return;
        }

        document.querySelectorAll('.js-pb-slider').forEach((slider) => {
            if (slider.swiper) {
                return;
            }

            const slides = parseInt(slider.dataset.slides || '3', 10);

            new Swiper(slider, {
                slidesPerView: 1,
                spaceBetween: parseInt(slider.dataset.gap || '20', 10),
                speed: 800,
                autoplay: slider.dataset.autoplay === 'true' ? { delay: 3000 } : false,
                breakpoints: { 640: { slidesPerView: 2 }, 1024: { slidesPerView: slides } },
                navigation: {
                    nextEl: slider.querySelector('.swiper-button-next'),
                    prevEl: slider.querySelector('.swiper-button-prev'),
                },
                pagination: { el: slider.querySelector('.swiper-pagination'), dynamicBullets: true, clickable: true },
            });
        });
    }

    function init() {
        const wrappers = [...document.querySelectorAll('.pb-wrapper[data-pb-standalone="true"]')];

        if (!wrappers.length) {
            return;
        }

        wrappers.forEach((wrapper) => {
            wrapper.querySelectorAll('[data-accordion]').forEach(initAccordion);
            wrapper.querySelectorAll('[data-tabs]').forEach(initTabs);
            initScrollLinks(wrapper);
        });

        initGallery();
        initSliders();
    }

    window.NexorPageBuilder = { init };

    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
