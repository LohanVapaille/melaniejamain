<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AdminController; // <-- LIGNE AJOUTÉE ICI
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SuggestionController;

Route::get('/', function () {
    // MODIFICATION : On prend l'état 1 (publié) ET l'état 2 (à venir)
    $articles = DB::table('articles')
        ->whereIn('is_published', [1, 2])
        ->orderBy('created_at', 'desc')
        ->get();

    $lang = session('lang', 'fr');
    $info = DB::table('infos')->where('langue', $lang)->first() ?? DB::table('infos')->where('langue', 'fr')->first();

    $captchaQuestion = ContactController::captchaQuestion($lang === 'en');
    $suggestionCaptcha = SuggestionController::captchaQuestion($lang === 'en');

    return view('welcome', compact('articles', 'info', 'lang', 'captchaQuestion', 'suggestionCaptcha'));
});

// Formulaire de contact : 6 envois maximum par tranche de 10 minutes et par visiteur
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:6,10')->name('contact.send');

// Suggestions de sujets : même limite, avec un compteur séparé de celui du contact
Route::post('/suggestions', [SuggestionController::class, 'send'])->middleware('throttle:6,10,suggestions')->name('suggestions.send');

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
    $suggestionCaptcha = SuggestionController::captchaQuestion($lang === 'en');

    return view('articles.show', compact('article', 'lang', 'info', 'suggestionCaptcha'));
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

    Route::post('/admin/suggestions/store', [SuggestionController::class, 'store'])->name('admin.suggestions.store');
    Route::delete('/admin/suggestions/{id}', [SuggestionController::class, 'delete'])->name('admin.suggestions.delete');
});
