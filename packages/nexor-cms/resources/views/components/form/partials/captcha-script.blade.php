{{--
    Скрипт капч форм — один на страницу.

    - Рисует виджеты Yandex SmartCaptcha и Google reCAPTCHA (их скрипты
      подгружаются только там, где капча есть).
    - Перед отправкой формы берёт токен (невидимую капчу и reCAPTCHA v3 —
      запускает) и кладёт его в скрытое поле captcha_token. Отправку
      перехватывает на фазе погружения, раньше Livewire.
    - Кнопка «другой код» у Nexor Captcha в обычной форме.
    - Событие nexor-captcha-reset (из Livewire) сбрасывает виджет после
      неудачной проверки: токен одноразовый.
--}}

@once
<script>
(() => {
    if (window.NexorCaptcha) {
        return;
    }

    const scripts = {};

    function load(src) {
        return scripts[src] ??= new Promise((resolve, reject) => {
            const callback = `nexorCaptchaLoaded${Object.keys(scripts).length}`;
            const script = document.createElement('script');

            window[callback] = resolve;
            script.src = src.replace('{callback}', callback);
            script.async = true;
            script.onerror = reject;
            document.head.append(script);
        });
    }

    const providers = {
        yandex: {
            async render(box, options) {
                await load('https://smartcaptcha.cloud.yandex.ru/captcha.js?render=onload&onload={callback}');

                return window.smartCaptcha.render(box, {
                    sitekey: options.key,
                    invisible: options.invisible,
                    hideShield: options.hideShield,
                    webview: options.webview,
                    hl: (document.documentElement.lang || 'ru').slice(0, 2),
                    callback: (token) => box.nexorResolve?.(token),
                });
            },
            token(box, id, options) {
                const token = window.smartCaptcha.getResponse(id);

                if (token || !options.invisible) {
                    return Promise.resolve(token);
                }

                return new Promise((resolve) => {
                    box.nexorResolve = resolve;
                    window.smartCaptcha.execute(id);
                });
            },
            reset(id) {
                window.smartCaptcha.reset(id);
            },
        },
        google: {
            async render(box, options) {
                if (options.version === 'v3') {
                    await load(`https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(options.key)}&onload={callback}`);

                    return null;
                }

                await load('https://www.google.com/recaptcha/api.js?render=explicit&onload={callback}');

                return window.grecaptcha.render(box, { sitekey: options.key });
            },
            token(box, id, options) {
                if (options.version === 'v3') {
                    return new Promise((resolve) => window.grecaptcha.ready(() => {
                        window.grecaptcha.execute(options.key, { action: options.action }).then(resolve, () => resolve(''));
                    }));
                }

                return Promise.resolve(window.grecaptcha.getResponse(id));
            },
            reset(id, options) {
                if (options.version !== 'v3') {
                    window.grecaptcha.reset(id);
                }
            },
        },
    };

    /** Виджет рисуется один раз; повторные вызовы получают тот же результат. */
    function ready(box) {
        return box.nexorReady ??= (async () => {
            const options = JSON.parse(box.dataset.options || '{}');
            const provider = providers[box.dataset.provider];
            const id = await provider.render(box, options);

            return { provider, options, id };
        })();
    }

    function init(root = document) {
        root.querySelectorAll('[data-nexor-captcha-box]').forEach((box) => {
            ready(box).catch((error) => console.error('Капча не загрузилась', error));
        });
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        const box = form.querySelector('[data-nexor-captcha-box]');

        if (!box) {
            return;
        }

        if (form.dataset.nexorCaptchaPassed) {
            delete form.dataset.nexorCaptchaPassed;

            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        let token = '';

        try {
            const state = await ready(box);
            token = (await state.provider.token(box, state.id, state.options)) || '';
        } catch (error) {
            console.error('Капча не ответила', error);
        }

        const input = form.querySelector('[data-nexor-captcha-token]');
        input.value = token;
        input.dispatchEvent(new Event('input', { bubbles: true }));

        form.dataset.nexorCaptchaPassed = '1';
        form.requestSubmit();
    }, true);

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-nexor-captcha-refresh]');

        if (!button) {
            return;
        }

        const wrap = button.closest('[data-nexor-captcha-nexor]');
        const response = await fetch(button.dataset.nexorCaptchaRefresh, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        wrap.querySelector('[data-nexor-captcha-image]').src = data.image;
        wrap.querySelector('[data-nexor-captcha-id]').value = data.id;
        wrap.parentElement.querySelector('input[name="captcha_answer"]').value = '';
    });

    window.addEventListener('nexor-captcha-reset', async (event) => {
        const root = document.querySelector(`[wire\\:id="${event.detail?.form}"]`);

        for (const box of root?.querySelectorAll('[data-nexor-captcha-box]') ?? []) {
            const state = await ready(box);
            state.provider.reset(state.id, state.options);
        }
    });

    window.NexorCaptcha = { init };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init());
    } else {
        init();
    }
})();
</script>
@endonce
