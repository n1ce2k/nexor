<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Support\Captcha\NexorCaptcha;
use Nexor\Cms\Support\FormCaptcha;

/**
 * Nexor Captcha на сайте: картинка задачи и новая задача по кнопке «обновить».
 */
class CaptchaController extends Controller
{
    public function image(string $id): Response
    {
        $image = NexorCaptcha::image($id) ?? abort(404);

        return response($image['body'], 200, [
            'Content-Type' => $image['type'],
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public function fresh(Request $request): JsonResponse
    {
        $form = FeedbackForm::findForSite((string) $request->query('form', ''));
        $id = $form ? FormCaptcha::challenge($form) : null;

        abort_if($id === null, 404);

        return response()->json([
            'id' => $id,
            'image' => route('nexor.captcha.image', $id),
        ])->header('Cache-Control', 'no-store');
    }
}
