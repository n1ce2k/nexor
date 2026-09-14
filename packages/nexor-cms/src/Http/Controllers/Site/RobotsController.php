<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Nexor\Cms\Models\Setting;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $body = Setting::get('seo.robots') ?: "User-agent: *\nAllow: /";

        return response($body."\n\nSitemap: ".url('/sitemap.xml')."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
