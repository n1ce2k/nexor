/**
 * Баннер согласия на cookie.
 *
 * Зависимостей нет: баннер показывается посетителю, который ещё ничего не
 * загрузил и не разрешал, поэтому тянуть ради него библиотеку неправильно.
 *
 * Разрешённые коды приходят в ответе сервера и вставляются сразу — иначе
 * посетитель, нажавший «принять», попал бы в аналитику только со следующей
 * страницы.
 */
(() => {
    // Компонент могли поставить дважды — в макете и на странице. Второй запуск
    // повесил бы вторые обработчики, и клик срабатывал бы туда и обратно.
    if (window.nexorCookiesReady) {
        return;
    }

    window.nexorCookiesReady = true;

    const script = document.currentScript;
    const url = script?.dataset.url;
    const token = script?.dataset.token || '';

    const root = document.querySelector('[data-cookies]');

    /**
     * Вставка кода счётчика.
     *
     * Через innerHTML браузер теги <script> не выполняет, поэтому каждый
     * пересоздаётся: иначе счётчик молча не запускается.
     */
    const insert = (code, target) => {
        const box = document.createElement('div');
        box.innerHTML = code;

        box.querySelectorAll('script').forEach((old) => {
            const fresh = document.createElement('script');

            Array.from(old.attributes).forEach((attribute) => fresh.setAttribute(attribute.name, attribute.value));

            if (!old.src) {
                fresh.textContent = old.textContent;
            }

            old.replaceWith(fresh);
        });

        target.append(...box.childNodes);
    };

    // ----------------------------------------------------------- режим JS
    //
    // Страница пришла из кеша со всеми кодами сразу, выбирает их браузер по
    // уже сохранённому согласию. Эта часть работает и без баннера: посетитель
    // мог согласиться вчера, и баннера ему больше не показывают.
    const stored = document.getElementById('nexor-cookies-counters');

    if (stored) {
        const data = JSON.parse(stored.textContent || '{}');
        const cookie = document.cookie.split(';')
            .map((one) => one.trim())
            .find((one) => one.startsWith(`${data.cookie}=`));

        if (cookie) {
            const preferences = JSON.parse(decodeURIComponent(cookie.slice(data.cookie.length + 1)) || '{}');

            ['head', 'body'].forEach((placement) => {
                Object.entries(data[placement] ?? {}).forEach(([category, codes]) => {
                    if (preferences[category]) {
                        codes.forEach((code) => insert(code, placement === 'head' ? document.head : document.body));
                    }
                });
            });
        }
    }

    // -------------------------------------------------------------- баннер

    if (!root || !url) {
        return;
    }

    const banner = root.querySelector('[data-cookies-banner]');
    const modal = root.querySelector('[data-cookies-modal]');
    const overlay = root.querySelector('[data-cookies-overlay]');

    const show = (element) => {
        element.hidden = false;
        // Класс ставится следующим кадром, иначе переход не проигрывается.
        requestAnimationFrame(() => element.classList.add('is-open'));
    };

    const hide = (element) => {
        element.classList.remove('is-open');
        setTimeout(() => {
            element.hidden = true;
        }, 250);
    };

    const openModal = () => {
        hide(banner);
        show(overlay);
        show(modal);
    };

    const closeModal = () => {
        hide(modal);
        hide(overlay);
    };

    const save = (preferences) => {
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body: JSON.stringify(preferences),
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((data) => {
                (data.codes?.head ?? []).forEach((code) => insert(code, document.head));
                (data.codes?.body ?? []).forEach((code) => insert(code, document.body));
            })
            .catch(() => {})
            .finally(() => {
                closeModal();
                hide(banner);
            });
    };

    const chosen = () => {
        const preferences = { analytics: false, marketing: false };

        root.querySelectorAll('[data-cookies-category]').forEach((input) => {
            preferences[input.dataset.cookiesCategory] = input.checked;
        });

        return preferences;
    };

    root.querySelectorAll('[data-cookies-accept-all]').forEach((button) => {
        button.addEventListener('click', () => save({ analytics: true, marketing: true }));
    });

    root.querySelectorAll('[data-cookies-decline]').forEach((button) => {
        button.addEventListener('click', () => save({ analytics: false, marketing: false }));
    });

    root.querySelectorAll('[data-cookies-accept]').forEach((button) => {
        button.addEventListener('click', () => save(chosen()));
    });

    root.querySelectorAll('[data-cookies-settings]').forEach((button) => {
        button.addEventListener('click', openModal);
    });

    root.querySelectorAll('[data-cookies-close]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    // Описание категории сворачивается шевроном: в окне их несколько, и
    // целиком развёрнутые тексты не помещаются на небольшом экране.
    root.querySelectorAll('[data-cookies-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const description = button.closest('.nexor-cookies__category')?.querySelector('.nexor-cookies__desc');

            if (!description) {
                return;
            }

            const open = button.getAttribute('aria-expanded') !== 'false';

            button.setAttribute('aria-expanded', open ? 'false' : 'true');
            button.setAttribute('aria-label', open ? 'Показать описание' : 'Свернуть описание');
            description.hidden = open;
        });
    });

    overlay?.addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    setTimeout(() => show(banner), Number(root.dataset.delay) || 0);
})();
