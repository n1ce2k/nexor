<?php

namespace Nexor\Shop\Http\Controllers\Site;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Nexor\Shop\Support\Shop;

/**
 * Страница оформления — только у корзины Ultimate на лицензии со Standart.
 */
class CheckoutPageController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(Shop::checkoutEnabled(), 404);

        return view('nexor-shop::pages.checkout');
    }
}
