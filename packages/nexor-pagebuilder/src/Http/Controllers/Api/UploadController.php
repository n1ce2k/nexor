<?php

namespace Nexor\PageBuilder\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Uploads;

/**
 * Загрузка файлов в блоки: картинки, видео и документы.
 *
 * Загружать может только тот, кто вправе создавать или менять элементы этого
 * инфоблока. Принимаются растровые картинки, видео и документы: SVG и HTML
 * с кодом внутри на сайт через конструктор не попадут.
 */
class UploadController extends Controller
{
    /**
     * Тип файла → проверка.
     *
     * @var array<string, array{mimes: string, mimetypes: string|null, max: int}>
     */
    protected const KINDS = [
        'image' => ['mimes' => 'jpg,jpeg,png,webp,gif', 'mimetypes' => 'image/jpeg,image/png,image/webp,image/gif', 'max' => 8192],
        'video' => ['mimes' => 'mp4,webm,ogv', 'mimetypes' => 'video/mp4,video/webm,video/ogg', 'max' => 102400],
        // Документы проверяются по содержимому (mimes): HTML под видом .pdf не пройдёт.
        'document' => ['mimes' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,rtf,txt,csv,zip,rar,7z', 'mimetypes' => null, 'max' => 51200],
    ];

    public function store(Request $request): JsonResponse
    {
        $kind = self::KINDS[$request->input('kind', 'image')] ?? self::KINDS['image'];

        $data = $request->validate([
            'kind' => ['nullable', 'in:'.implode(',', array_keys(self::KINDS))],
            'iblock' => ['required', 'integer'],
            'file' => array_filter([
                'required', 'file', 'mimes:'.$kind['mimes'],
                $kind['mimetypes'] ? 'mimetypes:'.$kind['mimetypes'] : null,
                'max:'.$kind['max'],
            ]),
        ], [], ['file' => 'файл']);

        $iblock = Iblock::query()->findOrFail($data['iblock']);
        $user = $request->user();

        abort_unless(
            $user->hasPermission($iblock->permissionCode('update')) || $user->hasPermission($iblock->permissionCode('create')),
            403,
        );

        $path = $request->file('file')->store(Nexor::directory('pagebuilder'), Uploads::disk());

        return response()->json([
            'path' => $path,
            'src' => Uploads::url($path),
        ], 201);
    }
}
