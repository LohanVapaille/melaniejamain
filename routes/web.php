<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AdminController; // <-- LIGNE AJOUTÉE ICI

Route::get('/', function () {
    // MODIFICATION : On prend l'état 1 (publié) ET l'état 2 (à venir)
    $articles = DB::table('articles')
        ->whereIn('is_published', [1, 2])
        ->orderBy('created_at', 'desc')
        ->get();

    $lang = session('lang', 'fr');
    $info = DB::table('infos')->where('langue', $lang)->first() ?? DB::table('infos')->where('langue', 'fr')->first();

    return view('welcome', compact('articles', 'info', 'lang'));
});

Route::get('/langue/{lang}', function ($lang) {
    abort_unless(in_array($lang, ['fr', 'en'], true), 404);
    session(['lang' => $lang]);
    return redirect()->to(url()->previous('/') ?: url('/'));
})->name('language.switch');

Route::get('/articles/{id}', function ($id) {
    $article = DB::table('articles')->where('id', $id)->first();

    // Si l'article n'existe pas OU s'il est en brouillon, on bloque avec une 404
    if (!$article || !$article->is_published) {
        abort(404, 'Article non publié ou introuvable');
    }

    $lang = session('lang', 'fr');
    $info = DB::table('infos')->where('langue', $lang)->first() ?? DB::table('infos')->where('langue', 'fr')->first();
    return view('articles.show', compact('article', 'lang', 'info'));
})->name('articles.show');


// Routes d'authentification de l'admin
Route::get('/admin/login', [AdminController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login.submit');

// Modifie temporairement la ligne ainsi :
// Remets la ligne d'origine maintenant que le fichier existe
// Remplace l'ancien groupe par celui-ci utilisant l'alias
// Groupe de routes protégées directement par la classe du middleware
Route::middleware([\App\Http\Middleware\AdminAuth::class])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('/admin/articles/store', [ArticleController::class, 'store'])->name('admin.articles.store');
    Route::post('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout');

    Route::post('/admin/infos/update', [AdminController::class, 'updateInfos'])->name('admin.infos.update');
    Route::delete('/admin/articles/{id}', [ArticleController::class, 'delete'])->name('admin.articles.delete');
});
