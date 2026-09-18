import { computed, onUnmounted, ref } from 'vue';
import { api } from '../api';
import { useUi } from '../stores/ui';

/**
 * Задача обновления: запуск и слежение за ходом.
 *
 * Composer работает дольше веб-запроса, поэтому сервер выполняет его в фоне, а
 * панель опрашивает состояние и показывает вывод по мере появления.
 */
export function useComposerTask() {
    const ui = useUi();

    const task = ref(null);
    const starting = ref(false);
    let timer = null;

    const running = computed(() => task.value?.state === 'running');
    const failed = computed(() => task.value?.state === 'failed');

    function stop() {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    }

    async function poll() {
        stop();

        try {
            const data = await api.get(`updates/status/${task.value?.id ?? ''}`);

            if (data?.state) {
                task.value = data;
            }
        } catch {
            // Обрыв связи не повод гасить страницу: попробуем на следующем круге.
        }

        if (task.value?.state === 'running') {
            timer = setTimeout(poll, 2000);
        }
    }

    /** Подхватывает задачу, которая уже шла к моменту открытия страницы. */
    function adopt(current) {
        if (current?.state) {
            task.value = current;

            if (current.state === 'running') {
                poll();
            }
        }
    }

    /**
     * @param {string} url Адрес запуска: updates/update или updates/install
     * @param {object} body Что передать серверу
     * @param {Function|null} onFinish Что сделать, когда задача закончится
     */
    async function start(url, body = {}, onFinish = null) {
        starting.value = true;

        try {
            const data = await api.post(url, body);

            ui.notify(data.message);
            task.value = data.current ?? null;

            await watchUntilDone(onFinish);
        } catch (error) {
            ui.notifyError(error);
        } finally {
            starting.value = false;
        }
    }

    async function watchUntilDone(onFinish) {
        stop();

        while (task.value?.state === 'running') {
            await new Promise((resolve) => { timer = setTimeout(resolve, 2000); });

            try {
                const data = await api.get(`updates/status/${task.value.id}`);

                if (data?.state) {
                    task.value = data;
                }
            } catch {
                // Молчим: сервер может быть занят самим обновлением.
            }
        }

        stop();

        if (task.value?.state === 'done') {
            ui.notify('Готово.');
        } else if (task.value?.state === 'failed') {
            ui.notify('Задача завершилась с ошибкой — смотрите вывод.', 'error');
        }

        onFinish?.(task.value);
    }

    onUnmounted(stop);

    return { task, starting, running, failed, start, adopt, stop };
}
