<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
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
