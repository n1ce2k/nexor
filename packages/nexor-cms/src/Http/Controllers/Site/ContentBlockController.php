<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nexor\Cms\Models\ContentBlock;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\ContentBlocks;
use Nexor\Cms\Support\InlineEditor;
use Nexor\Cms\Support\Uploads;

/**
 * Приём правок, сделанных на странице сайта.
 *
 * Отдельно от админского API: правка приходит с публичной страницы, из сессии
 * того же пользователя. Право проверяется здесь, а не только в разметке —
 * разметку в браузере подделать нетрудно.
 */
class ContentBlockController extends Controller
{
    /**
     * Включить или выключить режим правки.
     */
    public function mode(Request $request): JsonResponse
    {
        abort_unless(InlineEditor::allowed(), 403);

        $request->session()->put(InlineEditor::KEY, $request->boolean('active'));

        return response()->json(['active' => (bool) session(InlineEditor::KEY)]);
    }

    /**
     * Сохранить текст блока.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        abort_unless(InlineEditor::allowed(), 403);

        $data = $request->validate([
            'value' => ['present', 'string', 'max:20000'],
            'type' => ['required', 'in:text,html'],
        ]);

        $value = InlineEditor::clean($data['value'], $data['type']);

        ContentBlocks::put($key, $data['type'], $value, $request->user()?->id);
        $this->log($key, 'Блок «'.$key.'» изменён на сайте');

        // Возвращаем то, что реально сохранилось: браузер подставит это вместо
        // набранного, и правщик сразу увидит, что из разметки вычистили.
        return response()->json(['value' => $value]);
    }

    /**
     * Заменить картинку блока.
     */
    public function image(Request $request, string $key): JsonResponse
    {
        abort_unless(InlineEditor::allowed(), 403);

        $request->validate(['image' => ['required', 'image', 'max:8192']]);

        $previous = ContentBlocks::get($key);
        $path = $request->file('image')->store('content', Uploads::disk());

        ContentBlocks::put($key, 'image', $path, $request->user()?->id);

        // Прежняя картинка больше нигде не используется: ключ один на сайт.
        if ($previous && $previous !== $path) {
            Uploads::delete($previous);
        }

        $this->log($key, 'Картинка блока «'.$key.'» заменена на сайте');

        return response()->json(['value' => $path, 'url' => Uploads::url($path)]);
    }

    /**
     * Вернуть блок к тому, что написано в шаблоне.
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        abort_unless(InlineEditor::allowed(), 403);

        $stored = ContentBlocks::all()[$key] ?? null;

        if (($stored['type'] ?? null) === 'image' && $stored['value']) {
            Uploads::delete($stored['value']);
        }

        ContentBlocks::forget($key);
        $this->log($key, 'Блок «'.$key.'» возвращён к шаблонному');

        return response()->json(['reset' => true]);
    }

    /**
     * Запись в журнал действий: у файлового хранилища модели нет, поэтому
     * пишем по записи из базы, когда она есть.
     */
    protected function log(string $key, string $message): void
    {
        $block = ContentBlocks::usesDatabase()
            ? ContentBlock::query()->where('key', $key)->first()
            : null;

        if ($block) {
            ActivityLogger::updated($block, $message);
        }
    }
}
