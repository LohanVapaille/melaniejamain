<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    /**
     * Afficher le détail d'un article unique
     */
    public function show($id)
    {
        // On va chercher l'article dans la table 'articles' via son ID
        // Si l'id n'existe pas, firstOrFail() renverra une erreur 404 propre
        $article = DB::table('articles')->where('id', $id)->first();

        if (!$article) {
            abort(404, 'Article non trouvé');
        }

        // On envoie l'article à la vue 'articles.show'
        return view('articles.show', compact('article'));
    }

    // N'oublie pas d'importer Request si ce n'est pas fait : use Illuminate\Http\Request;

    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|max:255',
            'contenu' => 'required',
            'nom_anglais' => 'nullable|max:255',
            'contenu_anglais' => 'nullable',
            'visual_model' => 'required|in:planet,moon,meteor,satellite,star',
            'model_3d' => 'nullable|file|mimes:glb|max:71680',
        ]);

        $id = $request->input('id');
        // Récupère le statut (1 pour publié, 0 pour brouillon)
        $isPublished = (int) $request->input('is_published', 1);
        $modelPath = $request->input('existing_model_3d_path');
        if ($request->hasFile('model_3d')) {
            $modelPath = $request->file('model_3d')->store('models/articles', 'public');
        }

        $visualModel = $request->input('visual_model', 'planet');

        if ($id) {
            // MODE ÉDITION
            DB::table('articles')->where('id', $id)->update([
                'nom' => $request->input('nom'),
                'contenu' => $request->input('contenu'),
                'nom_anglais' => $request->input('nom_anglais'),
                'contenu_anglais' => $request->input('contenu_anglais'),
                'is_published' => $isPublished, // <-- LIGNE AJOUTÉE
                'visual_model' => $visualModel,
                'model_3d_path' => $modelPath,
                'updated_at' => now(),
            ]);
            $message = 'L’article a bien été mis à jour !';
        } else {
            // MODE CRÉATION
            DB::table('articles')->insert([
                'nom' => $request->input('nom'),
                'contenu' => $request->input('contenu'),
                'nom_anglais' => $request->input('nom_anglais'),
                'contenu_anglais' => $request->input('contenu_anglais'),
                'is_published' => $isPublished, // <-- LIGNE AJOUTÉE
                'visual_model' => $visualModel,
                'model_3d_path' => $modelPath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $message = 'L’article a bien été enregistré !';
        }

        return redirect()->route('admin.dashboard')->with('success', $message);
    }

    public function delete($id)
    {
        // Supprime l'article ciblé
        DB::table('articles')->where('id', $id)->delete();

        return redirect()->route('admin.dashboard')->with('success', 'L’article a bien été supprimé.');
    }
}
