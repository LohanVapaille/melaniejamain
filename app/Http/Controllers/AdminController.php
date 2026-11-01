<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function showLogin()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        // La valeur vient de la configuration afin de fonctionner avec config:cache.
        $adminPassword = (string) config('admin.password');

        if ($adminPassword !== '' && hash_equals($adminPassword, (string) $request->input('password'))) {
            $request->session()->put('admin_authenticated', true);
            return redirect()->route('admin.dashboard');
        }

        return back()->with('error', 'Mot de passe incorrect.');
    }

    public function dashboard(Request $request)
    {
        // 1. Récupère tous les articles pour le tableau
        $articles = DB::table('articles')->orderBy('created_at', 'desc')->get();

        // 2. Vérifie si un ID d'article est demandé pour l'édition
        $articleToEdit = null;
        if ($request->has('edit')) {
            $articleToEdit = DB::table('articles')->where('id', $request->query('edit'))->first();
            // Taille du modèle .glb actuel, affichée dans le formulaire
            if ($articleToEdit?->model_3d_path && Storage::disk('public')->exists($articleToEdit->model_3d_path)) {
                $articleToEdit->model_3d_size = Storage::disk('public')->size($articleToEdit->model_3d_path);
            }
        }

        // 3. LIGNE À AJOUTER : Récupère les infos globales du site
        $info = DB::table('infos')->where('langue', 'fr')->first();
        $infoEn = DB::table('infos')->where('langue', 'en')->first();

        // 4. Récupère les suggestions des visiteurs et celle à modifier
        $suggestions = DB::table('suggestions')->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();
        $suggestionToEdit = $request->has('edit_suggestion')
            ? $suggestions->firstWhere('id', (int) $request->query('edit_suggestion'))
            : null;

        // 5. Renvoie le tout à la vue
        return view('admin.dashboard', compact('articles', 'articleToEdit', 'info', 'infoEn', 'suggestions', 'suggestionToEdit'));
    }
    public function logout(Request $request)
    {
        // Supprime la clé d'authentification admin de la session
        $request->session()->forget('admin_authenticated');

        // Optionnel : Détruit complètement la session actuelle et régénère le jeton CSRF
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Vous avez été déconnecté.');
    }

    public function updateInfos(Request $request)
    {
        // Validation rapide des champs principaux
        $request->validate([
            'mail' => 'nullable|email',
            'hero_h1' => 'required|max:255',
        ]);

        // On met à jour la première (et unique) ligne de la table infos
        $fields = [
            'nom' => $request->input('nom'),
            'prenom' => $request->input('prenom'),
            'mail' => $request->input('mail'),
            'linkedin' => $request->input('linkedin'),
            'status_pro' => $request->input('status_pro'),
            'ecole' => $request->input('ecole'),
            'hero_h1' => $request->input('hero_h1'),
            'hero_slogan' => $request->input('hero_slogan'),
            'hero_tag1' => $request->input('hero_tag1'),
            'hero_tag2' => $request->input('hero_tag2'),
            'hero_tag3' => $request->input('hero_tag3'),
            'hero_tag4' => $request->input('hero_tag4'),
            'about_title' => $request->input('about_title'),
            'about_text' => $request->input('about_text'), // HTML de la bio
            'about_pillar_title1' => $request->input('about_pillar_title1'),
            'about_pillar_content1' => $request->input('about_pillar_content1'),
            'about_pillar_title2' => $request->input('about_pillar_title2'),
            'about_pillar_content2' => $request->input('about_pillar_content2'),
            'about_pillar_title3' => $request->input('about_pillar_title3'),
            'about_pillar_content3' => $request->input('about_pillar_content3'),
            'section_article_title' => $request->input('section_article_title'),
            'section_contact_title' => $request->input('section_contact_title'),
            'section_contact_text' => $request->input('section_contact_text'),
            'updated_at' => now(),
        ];
        DB::table('infos')->where('langue', 'fr')->update($fields);
        $englishFields = [];
        foreach (array_keys($fields) as $key) {
            if ($key !== 'updated_at' && $request->exists('en_'.$key)) $englishFields[$key] = $request->input('en_'.$key);
        }
        $englishFields['updated_at'] = now();
        DB::table('infos')->where('langue', 'en')->update($englishFields);

        return redirect()->to(route('admin.dashboard') . '#textes')->with('success', 'Les textes du site ont bien été mis à jour !');
    }


}
