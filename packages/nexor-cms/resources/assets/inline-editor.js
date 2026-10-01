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

    /** Текст, который можно безопасно вставить как разметку. */
    const escape = (value) => {
        const box = document.createElement('div');
        box.textContent = value;

        return box.innerHTML;
    };

    const flash = (element, ok, text) => {
        element.classList.remove('nexor-edit--saved', 'nexor-edit--failed');
        element.classList.add(ok ? 'nexor-edit--saved' : 'nexor-edit--failed');
        setTimeout(() => element.classList.remove('nexor-edit--saved', 'nexor-edit--failed'), 1200);

        if (!ok && text) {
            alert(text);
        }
    };

    /**
     * Перенос строки на месте курсора.
     *
     * Ровно <br> и ничего больше: браузер на своё усмотрение заворачивает
     * строки в <div> или <p>, а это уже чужая разметка внутри блока сайта.
     */
    const insertBreak = () => {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) {
            return;
        }

        const range = selection.getRangeAt(0);
        range.deleteContents();

        const br = document.createElement('br');
        range.insertNode(br);

        // В конце строки одиночный <br> не виден, пока за ним ничего нет:
        // ставим второй и оставляем курсор между ними.
        const tail = document.createElement('br');
        br.after(tail);

        range.setStartAfter(br);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    };

    // ------------------------------------------------------------------ текст

    const editText = (element) => {
        if (element.isContentEditable) {
            return;
        }

        const html = element.dataset.nexorType === 'html';
        // Блок, которому разрешены переносы: Enter вставляет строку, а
        // сохраняет клик вне блока или Ctrl+Enter.
        const breaks = element.dataset.nexorBreaks === '1';
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
                    // из разметки там могли что-то вычистить. Переводы строк
                    // при этом снова становятся <br>: текстом они схлопнулись
                    // бы в пробел, и абзацы слиплись бы до перезагрузки.
                    if (html) {
                        element.innerHTML = data.value;
                    } else if (breaks) {
                        element.innerHTML = escape(data.value).replace(/\n/g, '<br>');
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

                return;
            }

            if (event.key !== 'Enter') {
                return;
            }

            // Однострочный блок: Enter — это «готово».
            if (!breaks || event.ctrlKey || event.metaKey) {
                event.preventDefault();
                element.blur();

                return;
            }

            // Многострочный: вставляем именно <br>, иначе браузер завернёт
            // строку в <div> или <p> — и разметка блока поедет.
            event.preventDefault();
            insertBreak();
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

    // Что делать с выбранным файлом: картинка и фон грузятся одинаково, а
    // показываются по-разному.
    let onPick = null;

    const pick = (callback) => {
        onPick = callback;
        picker.click();
    };

    const upload = (key, file) => {
        const body = new FormData();
        body.append('image', file);

        return send(`${base}/${encodeURIComponent(key)}/image`, { method: 'POST', body });
    };

    const isImage = (file) => Boolean(file && file.type.startsWith('image/'));

    const uploadImage = (element, file) => {
        if (!isImage(file)) {
            return;
        }

        upload(element.dataset.nexorEdit, file)
            .then((data) => {
                element.src = `${data.url}?v=${Date.now()}`;
                element.dataset.nexorEdited = '1';
                flash(element, true);
            })
            .catch((error) => flash(element, false, error.message));
    };

    picker.addEventListener('change', () => {
        onPick?.(picker.files[0]);
        onPick = null;
        picker.value = '';
    });

    // ----------------------------------------------------------------- сброс

    const reset = (element, key = element.dataset.nexorEdit) => {
        if (!confirm('Вернуть блок к тому, что написано в шаблоне?')) {
            return;
        }

        send(`${base}/${encodeURIComponent(key)}`, { method: 'DELETE' })
            .then(() => window.location.reload())
            .catch((error) => flash(element, false, error.message));
    };

    // -------------------------------------------------------------------- фон
    //
    // Секция с @editBackground получает кнопки в правом верхнем углу. Рамки у
    // самой секции нет: внутри неё свои правимые тексты со своими рамками.
    // Пунктир виден, только пока курсор на кнопках, — чтобы было понятно, к
    // какой области относится фон.
    //
    // Кнопки живут в отдельном слое поверх страницы, а не внутри секции:
    // чтобы поставить их в угол изнутри, секции пришлось бы дать
    // position: relative, и у вёрстки сайта поехали бы её элементы.

    const layer = document.createElement('div');
    layer.className = 'nexor-bg-layer';
    document.body.appendChild(layer);

    const showBackground = (section, url) => {
        const value = `url("${url.replace(/"/g, '%22')}")`;
        const variable = section.dataset.nexorBgVar;

        if (variable) {
            section.style.setProperty(variable, value);
        } else {
            section.style.backgroundImage = value;
        }
    };

    const backgrounds = [...document.querySelectorAll('[data-nexor-bg]')].map((section) => {
        const box = document.createElement('div');
        box.className = 'nexor-bg-controls';

        const change = document.createElement('button');
        change.type = 'button';
        change.className = 'nexor-bg-button';
        change.textContent = 'Фон';
        change.title = 'Сменить фон секции. Картинку можно и перетащить на кнопку.';

        const restore = document.createElement('button');
        restore.type = 'button';
        restore.className = 'nexor-bg-button nexor-bg-button--muted';
        restore.textContent = 'Вернуть';
        restore.title = 'Вернуть фон из шаблона';
        restore.hidden = section.dataset.nexorEdited !== '1';

        box.append(change, restore);
        layer.appendChild(box);

        const uploadBackground = (file) => {
            if (!isImage(file)) {
                return;
            }

            upload(section.dataset.nexorBg, file)
                .then((data) => {
                    showBackground(section, `${data.url}?v=${Date.now()}`);
                    section.dataset.nexorEdited = '1';
                    restore.hidden = false;
                    flash(section, true);
                })
                .catch((error) => flash(section, false, error.message));
        };

        change.addEventListener('click', () => pick(uploadBackground));
        restore.addEventListener('click', () => reset(section, section.dataset.nexorBg));

        box.addEventListener('mouseenter', () => section.classList.add('nexor-bg--hover'));
        box.addEventListener('mouseleave', () => section.classList.remove('nexor-bg--hover'));

        box.addEventListener('dragover', (event) => {
            event.preventDefault();
            box.classList.add('nexor-edit--drop');
            section.classList.add('nexor-bg--hover');
        });

        box.addEventListener('dragleave', () => {
            box.classList.remove('nexor-edit--drop');
            section.classList.remove('nexor-bg--hover');
        });

        box.addEventListener('drop', (event) => {
            event.preventDefault();
            box.classList.remove('nexor-edit--drop');
            section.classList.remove('nexor-bg--hover');
            uploadBackground(event.dataTransfer?.files?.[0]);
        });

        return { section, box };
    });

    // Кнопки стоят по координатам секции в документе: пересчитываем, когда
    // вёрстка могла сдвинуться — догрузились картинки, шрифты, сменилась ширина.
    let placing = false;

    const place = () => {
        if (placing) {
            return;
        }

        placing = true;

        requestAnimationFrame(() => {
            placing = false;

            backgrounds.forEach(({ section, box }) => {
                const rect = section.getBoundingClientRect();

                box.hidden = rect.width === 0 && rect.height === 0;
                box.style.top = `${rect.top + window.scrollY + 8}px`;
                box.style.left = `${rect.right + window.scrollX - box.offsetWidth - 8}px`;
            });
        });
    };

    if (backgrounds.length > 0) {
        place();
        window.addEventListener('load', place);
        window.addEventListener('resize', place);

        if ('ResizeObserver' in window) {
            new ResizeObserver(place).observe(document.body);
        }
    }

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
            pick((file) => uploadImage(element, file));

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

        // Уходим на тот же адрес без ?nexor-edit: иначе он снова включил бы
        // режим при первой же перезагрузке страницы.
        const url = new URL(window.location.href);
        url.searchParams.delete('nexor-edit');

        send(`${base}/mode`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ active: false }),
        }).then(() => window.location.replace(url.toString()));
    });
})();
