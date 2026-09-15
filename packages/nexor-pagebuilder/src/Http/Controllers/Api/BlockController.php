<?php

namespace Nexor\PageBuilder\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Nexor\PageBuilder\Blocks\Block;
use Nexor\PageBuilder\Enums\Surface;
use Nexor\PageBuilder\PageBuilder;

class BlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'surface' => ['nullable', Rule::enum(Surface::class)],
        ]);

        $surface = Surface::tryFrom($data['surface'] ?? '') ?? Surface::Detail;

        return response()->json([
            'data' => array_values(array_map(
                fn (Block $block) => $block->toArray(),
                PageBuilder::blocks()->for($surface),
            )),
        ]);
    }
}
