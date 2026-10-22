<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    public const VISUAL_MODELS = ['planet', 'gas_giant', 'moon', 'meteor', 'satellite', 'station', 'star'];

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
            'visual_model' => 'required|in:'.implode(',', self::VISUAL_MODELS),
            'visual_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // 15 Mo maximum : au-delà, le modèle met trop longtemps à s'afficher.
            'model_3d' => 'nullable|file|extensions:glb|max:15360',
        ], [
            'model_3d.max' => 'Le modèle 3D ne doit pas dépasser 15 Mo. Compressez-le d’abord (gltf.report ou gltf-transform).',
            'model_3d.extensions' => 'Le modèle 3D doit être un fichier .glb.',
        ]);

        $id = $request->input('id');
        // Récupère le statut (1 pour publié, 0 pour brouillon)
        $isPublished = (int) $request->input('is_published', 1);
        $currentModelPath = $id ? DB::table('articles')->where('id', $id)->value('model_3d_path') : null;
        $modelPath = $currentModelPath;
        if ($request->hasFile('model_3d')) {
            $modelPath = $request->file('model_3d')->store('models/articles', 'public');
        } elseif ($request->boolean('remove_model_3d')) {
            $modelPath = null;
        }
        // L'ancien fichier n'est plus utilisé : on libère l'espace disque.
        if ($currentModelPath && $currentModelPath !== $modelPath) {
            Storage::disk('public')->delete($currentModelPath);
        }

        $visualModel = $request->input('visual_model', 'planet');
        $visualColor = $request->input('visual_color') ?: null;

        if ($id) {
            // MODE ÉDITION
            DB::table('articles')->where('id', $id)->update([
                'nom' => $request->input('nom'),
                'contenu' => $request->input('contenu'),
                'nom_anglais' => $request->input('nom_anglais'),
                'contenu_anglais' => $request->input('contenu_anglais'),
                'is_published' => $isPublished, // <-- LIGNE AJOUTÉE
                'visual_model' => $visualModel,
                'visual_color' => $visualColor,
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
                'visual_color' => $visualColor,
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
        // Supprime l'article ciblé et son éventuel modèle 3D
        $modelPath = DB::table('articles')->where('id', $id)->value('model_3d_path');
        if ($modelPath) {
            Storage::disk('public')->delete($modelPath);
        }
        DB::table('articles')->where('id', $id)->delete();

        return redirect()->route('admin.dashboard')->with('success', 'L’article a bien été supprimé.');
    }
}
