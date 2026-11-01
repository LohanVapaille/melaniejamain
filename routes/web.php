<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AdminController; // <-- LIGNE AJOUTÉE ICI
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SuggestionController;
use App\Http\Middleware\SetLanguage;

// Une URL par langue : français à la racine, anglais sous /en
foreach (['fr' => '', 'en' => 'en'] as $lang => $prefix) {
    Route::prefix($prefix)->name("$lang.")->middleware(SetLanguage::class . ":$lang")->group(function () {
        Route::get('/', [ArticleController::class, 'home'])->name('home');
        Route::get('/articles/{article}', [ArticleController::class, 'show'])
            ->where('article', '[0-9]+(?:-[a-z0-9-]+)?')
            ->name('articles.show');
    });
}

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots']);

// Formulaire de contact : 6 envois maximum par tranche de 10 minutes et par visiteur
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:6,10')->name('contact.send');

// Suggestions de sujets : même limite, avec un compteur séparé de celui du contact
Route::post('/suggestions', [SuggestionController::class, 'send'])->middleware('throttle:6,10,suggestions')->name('suggestions.send');

// Sélecteur de langue des pages d'administration, qui n'ont pas d'URL par langue
Route::get('/langue/{lang}', function ($lang) {
    abort_unless(in_array($lang, ['fr', 'en'], true), 404);
    session(['lang' => $lang]);
    return redirect()->to(url()->previous('/') ?: url('/'));
})->name('language.switch');


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
