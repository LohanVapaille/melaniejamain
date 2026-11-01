<?php

namespace App\Http\Controllers;

use App\Support\Seo;

class SeoController extends Controller
{
    public function sitemap()
    {
        return response()
            ->view('seo.sitemap', ['entries' => Seo::sitemapEntries()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            // Simple redirection qui ne contient aucune page à indexer
            'Disallow: /langue/',
            '',
            'Sitemap: ' . route('sitemap'),
        ];

        return response(implode("\n", $lines) . "\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
