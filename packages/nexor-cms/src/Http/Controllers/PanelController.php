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
        // Вместе с папкой, из которой открыт сам сайт (`/shop/admin`): роутер,
        // смонтированный не на том пути, где стоит браузер, переписывает
        // адрес в бессмыслицу вроде `/admin/public/admin`.
        return view('nexor::admin.panel', [
            'base' => rtrim((string) parse_url(url(Nexor::panelBase()), PHP_URL_PATH), '/'),
        ]);
    }
}
