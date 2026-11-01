<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLanguage
{
    // La langue vient de l'URL (et non d'un choix en session) : Google voit ainsi les deux versions du site
    public function handle(Request $request, Closure $next, string $lang): Response
    {
        $request->session()->put('lang', $lang);

        return $next($request);
    }
}
