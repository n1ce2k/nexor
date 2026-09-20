/**
 * Правка блоков прямо на странице сайта.
 *
 * Скрипт попадает в страницу только в режиме правки и только тому, кто вправе
 * менять блоки, поэтому здесь нет ни проверок прав, ни зависимостей: находим
 * элементы с `data-nexor-edit`, даём их поправить и отправляем результат.
 */
(() => {
    const root = document.currentScript?.dataset ?? {};
    const base = root.base || '/nexor/content';
    const token = root.token || '';

    const send = (url, options = {}) => fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', ...(options.headers || {}) },
        ...options,
    }).then(async (response) => {
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || 'Не удалось сохранить.');
        }

        return data;
    });

    const flash = (element, ok, text) => {
        element.classList.remove('nexor-edit--saved', 'nexor-edit--failed');
        element.classList.add(ok ? 'nexor-edit--saved' : 'nexor-edit--failed');
        setTimeout(() => element.classList.remove('nexor-edit--saved', 'nexor-edit--failed'), 1200);

        if (!ok && text) {
            alert(text);
        }
    };

    // ------------------------------------------------------------------ текст

    const editText = (element) => {
        if (element.isContentEditable) {
            return;
        }

        const html = element.dataset.nexorType === 'html';
        const before = element.innerHTML;
        const beforeValue = html ? before : element.innerText;

        element.contentEditable = 'true';
        element.focus();

        const stop = (save) => {
            element.contentEditable = 'false';
            element.removeEventListener('blur', onBlur);
            element.removeEventListener('keydown', onKey);

            if (!save) {
                element.innerHTML = before;

                return;
            }

            const value = html ? element.innerHTML : element.innerText;

            // Ничего не поменяли — и сохранять нечего.
            if (value.trim() === beforeValue.trim()) {
                element.innerHTML = before;

                return;
            }

            send(`${base}/${encodeURIComponent(element.dataset.nexorEdit)}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ value, type: element.dataset.nexorType }),
            })
                .then((data) => {
                    // Показываем то, что сохранилось на сервере, а не набранное:
                    // из разметки там могли что-то вычистить.
                    if (html) {
                        element.innerHTML = data.value;
                    } else {
                        element.textContent = data.value;
                    }

                    element.dataset.nexorEdited = '1';
                    flash(element, true);
                })
                .catch((error) => {
                    element.innerHTML = before;
                    flash(element, false, error.message);
                });
        };

        const onBlur = () => stop(true);
        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                stop(false);
            }

            // Enter сохраняет, Shift+Enter переносит строку — но только там,
            // где перенос вообще разрешён разметкой.
            if (event.key === 'Enter' && (!event.shiftKey || element.dataset.nexorType !== 'html')) {
                event.preventDefault();
                element.blur();
            }
        };

        element.addEventListener('blur', onBlur);
        element.addEventListener('keydown', onKey);
    };

    // --------------------------------------------------------------- картинка

    const picker = document.createElement('input');
    picker.type = 'file';
    picker.accept = 'image/*';
    picker.style.display = 'none';
    document.body.appendChild(picker);

    let target = null;

    const uploadImage = (element, file) => {
        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const body = new FormData();
        body.append('image', file);

        send(`${base}/${encodeURIComponent(element.dataset.nexorEdit)}/image`, { method: 'POST', body })
            .then((data) => {
                element.src = `${data.url}?v=${Date.now()}`;
                element.dataset.nexorEdited = '1';
                flash(element, true);
            })
            .catch((error) => flash(element, false, error.message));
    };

    picker.addEventListener('change', () => {
        if (target) {
            uploadImage(target, picker.files[0]);
        }

        picker.value = '';
    });

    // ----------------------------------------------------------------- сброс

    const reset = (element) => {
        if (!confirm('Вернуть блок к тому, что написано в шаблоне?')) {
            return;
        }

        send(`${base}/${encodeURIComponent(element.dataset.nexorEdit)}`, { method: 'DELETE' })
            .then(() => window.location.reload())
            .catch((error) => flash(element, false, error.message));
    };

    // -------------------------------------------------------------- события

    document.addEventListener('click', (event) => {
        const element = event.target.closest('[data-nexor-edit]');

        if (!element) {
            return;
        }

        if (event.altKey) {
            event.preventDefault();
            reset(element);

            return;
        }

        if (element.dataset.nexorType === 'image') {
            event.preventDefault();
            target = element;
            picker.click();

            return;
        }

        // Ссылку внутри правимого текста в этом режиме не открываем.
        event.preventDefault();
        editText(element);
    }, true);

    document.addEventListener('dragover', (event) => {
        const element = event.target.closest('[data-nexor-edit][data-nexor-type="image"]');

        if (element) {
            event.preventDefault();
            element.classList.add('nexor-edit--drop');
        }
    });

    document.addEventListener('dragleave', (event) => {
        event.target.closest?.('[data-nexor-edit]')?.classList.remove('nexor-edit--drop');
    });

    document.addEventListener('drop', (event) => {
        const element = event.target.closest('[data-nexor-edit][data-nexor-type="image"]');

        if (!element) {
            return;
        }

        event.preventDefault();
        element.classList.remove('nexor-edit--drop');
        uploadImage(element, event.dataTransfer?.files?.[0]);
    });

    document.querySelector('[data-nexor-edit-off]')?.addEventListener('click', (event) => {
        event.preventDefault();

        send(`${base}/mode`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ active: false }),
        }).then(() => window.location.reload());
    });
})();
