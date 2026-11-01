<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema; // <--- AJOUTE ÇA
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191); // <--- AJOUTE ÇA

        // En production, canonical et sitemap utilisent toujours l'adresse officielle (ni www, ni http).
        // Si APP_URL pointe encore sur localhost, on ne force rien pour ne pas casser le site.
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($this->app->isProduction() && !in_array(parse_url($appUrl, PHP_URL_HOST), [null, 'localhost', '127.0.0.1'], true)) {
            URL::forceRootUrl($appUrl);
            URL::forceScheme(parse_url($appUrl, PHP_URL_SCHEME) ?: 'https');
        }
    }
}