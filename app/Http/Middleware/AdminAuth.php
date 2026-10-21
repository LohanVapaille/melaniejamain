<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('admin_authenticated') !== true) {

            // On vide la session par sécurité et on redirige vers le login
            $request->session()->forget('admin_authenticated');
            return redirect()->route('admin.login')->with('error', 'Accès refusé. Veuillez vous connecter.');
        }

        return $next($request);
    }
}