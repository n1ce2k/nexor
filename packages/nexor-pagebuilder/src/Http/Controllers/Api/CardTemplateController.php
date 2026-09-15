<?php

namespace Nexor\PageBuilder\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Nexor\PageBuilder\Support\CardTemplates;

class CardTemplateController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'prefix' => CardTemplates::PREFIX,
            'default' => CardTemplates::DEFAULT,
            'data' => CardTemplates::available(),
        ]);
    }
}
