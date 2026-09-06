<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\View\View;
use Nexor\Cms\Support\Nexor;

/**
 * Serves the Vue panel shell. Every route below its prefix renders the same
 * document; the SPA router takes it from there.
 */
class PanelController extends Controller
{
    public function __invoke(): View
    {
        return view('nexor::admin.panel', [
            'base' => Nexor::panelBase(),
        ]);
    }
}
