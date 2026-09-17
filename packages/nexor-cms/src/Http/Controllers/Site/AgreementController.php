<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Nexor\Cms\Models\Agreement;

/**
 * Полный текст соглашения на отдельной странице — `/agreement/<код>`.
 *
 * Свою вёрстку страницы можно положить в resources/views/vendor/nexor/site/agreement.blade.php.
 */
class AgreementController extends Controller
{
    public function __invoke(string $code): View
    {
        $agreement = Agreement::query()->active()->where('code', $code)->firstOrFail();

        return view('nexor::site.agreement', ['agreement' => $agreement]);
    }
}
