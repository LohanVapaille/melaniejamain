@extends('layouts.app')

@section('title', 'Gestion des articles')

@section('content')
    <div style="max-width: 1200px; margin: 100px auto 60px auto; padding: 20px; color: #e2e8f0;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
            <div>
                <h1 style="font-family: 'Cormorant Garamond', serif; font-size: 2.5rem; color: #fff; margin: 0;">
                    Espace Administration
                </h1>
                <p style="color: #a0aec0; margin: 5px 0 0 0;">Gestion des publications de Mélanie.</p>
            </div>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit"
                    style="background: transparent; border: 1px solid #fc8181; color: #fc8181; padding: 8px 16px; border-radius: 4px; font-weight: 500; cursor: pointer; transition: all 0.2s;"
                    onmouseover="this.style.background='#fc8181'; this.style.color='#000';"
                    onmouseout="this.style.background='transparent'; this.style.color='#fc8181';">
                    Se déconnecter
                </button>
            </form>
        </div>

        @if(session('success'))
            <div
                style="background: rgba(72, 187, 120, 0.2); border: 1px solid #48bb78; color: #48bb78; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                {{ session('success') }}
            </div>
        @endif



        <div class="admin-grid">

            <div class="form-container">
                <h2 id="form-title"
                    style="font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; color: #fff; margin-top: 0; margin-bottom: 20px;">
                    {{ request('edit') ? 'Modifier l’article' : 'Rédiger un nouvel article' }}
                </h2>

                <form action="{{ route('admin.articles.store') }}" method="POST" enctype="multipart/form-data" id="article-form"
                    style="background: rgba(135, 206, 250, 0.05); border: 1px solid rgba(214, 175, 55, 0.3); padding: 25px; border-radius: 6px;">
                    @csrf

                    <input type="hidden" id="article_id" name="id" value="{{ $articleToEdit ? $articleToEdit->id : '' }}">

                    <div style="margin-bottom: 20px;">
                        <label for="nom"
                            style="display: block; font-weight: 500; margin-bottom: 8px; color: var(--gold, #d4af37);">Titre
                            de l'article</label>
                        <input type="text" id="nom" name="nom" required
                            value="{{ old('nom', $articleToEdit ? $articleToEdit->nom : '') }}"
                            placeholder="Ex: La place des femmes..."
                            style="width: 100%; padding: 12px; background: rgba(0, 0, 0, 0.4); border: 1px solid #4a5568; color: #fff; border-radius: 4px; font-size: 1rem;">
                        @error('nom') <span style="color: #f56565; font-size: 0.85rem;">{{ $message }}</span> @enderror
                    </div>

                    @php
                        $selectedVisualModel = old('visual_model', $articleToEdit?->visual_model ?? 'planet');
                        $selectedVisualColor = old('visual_color', $articleToEdit?->visual_color ?? '');
                        $visualModels = [
                            'planet' => ['🌍', 'Planète'],
                            'gas_giant' => ['🪐', 'Géante gazeuse'],
                            'moon' => ['🌕', 'Lune'],
                            'meteor' => ['☄️', 'Astéroïde'],
                            'satellite' => ['🛰️', 'Satellite'],
                            'station' => ['🛸', 'Station spatiale'],
                            'star' => ['⭐', 'Étoile brillante'],
                        ];
                        $colorPresets = ['#2f6fb5', '#2a8f9c', '#3a7d5a', '#d9a066', '#c0564b', '#7a4fc2', '#b9b4ab', '#ffd27a'];
                        $modelSize = $articleToEdit?->model_3d_size ?? null;
                        $modelIsHeavy = $modelSize && $modelSize > 5 * 1024 * 1024;
                    @endphp
                    <fieldset class="space-editor" data-article-id="{{ $articleToEdit?->id ?? 1 }}">
                        <legend>Astre de l’article dans la visualisation 3D</legend>

                        <div class="space-editor-grid">
                            <div>
                                <div class="space-type-list" role="radiogroup" aria-label="Type d’astre">
                                    @foreach($visualModels as $value => [$icon, $label])
                                        <label class="space-type">
                                            <input type="radio" name="visual_model" value="{{ $value }}" {{ $selectedVisualModel === $value ? 'checked' : '' }}>
                                            <span><b aria-hidden="true">{{ $icon }}</b>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <span class="space-editor-label">Couleur dominante</span>
                                <input type="hidden" name="visual_color" id="visual_color" value="{{ $selectedVisualColor }}">
                                <div class="space-colors">
                                    <button type="button" class="space-color is-auto" data-color="" title="Automatique">Auto</button>
                                    @foreach($colorPresets as $preset)
                                        <button type="button" class="space-color" data-color="{{ $preset }}" style="--swatch: {{ $preset }}" title="{{ $preset }}"></button>
                                    @endforeach
                                    <label class="space-color is-custom" title="Couleur personnalisée">
                                        <input type="color" id="visual_color_picker" value="{{ $selectedVisualColor ?: '#d4af37' }}">
                                    </label>
                                </div>
                                @error('visual_color') <span class="space-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-preview">
                                <div id="space-preview-canvas"></div>
                                <small>Aperçu en direct · généré instantanément, aucun fichier à charger</small>
                            </div>
                        </div>

                        <details class="space-advanced" {{ $articleToEdit?->model_3d_path || $errors->has('model_3d') ? 'open' : '' }}>
                            <summary>Avancé : utiliser mon propre modèle .glb</summary>
                            <p>Facultatif et déconseillé : les astres intégrés s’affichent instantanément. Si vous utilisez votre propre modèle, compressez-le d’abord sur <a href="https://gltf.report" target="_blank" rel="noopener">gltf.report</a> (Draco ou Meshopt). <strong>15 Mo maximum</strong>, idéalement moins de 3 Mo.</p>
                            <input type="file" id="model_3d" name="model_3d" accept=".glb,model/gltf-binary" data-max-size="{{ 15 * 1024 * 1024 }}">
                            <small id="model_3d_feedback" class="space-file-feedback"></small>
                            @error('model_3d') <span class="space-error">{{ $message }}</span> @enderror
                            @if($articleToEdit?->model_3d_path)
                                <div class="space-current-model {{ $modelIsHeavy ? 'is-heavy' : '' }}">
                                    Modèle personnalisé actuel{{ $modelSize ? ' : ' . number_format($modelSize / 1048576, 1, ',', ' ') . ' Mo' : '' }}
                                    @if($modelIsHeavy)
                                        — trop lourd, il ralentit l’ouverture de la visualisation.
                                    @endif
                                    <label><input type="checkbox" name="remove_model_3d" value="1" {{ $modelIsHeavy ? 'checked' : '' }}> Retirer ce modèle et utiliser l’astre intégré</label>
                                </div>
                            @endif
                        </details>
                    </fieldset>

                    <div style="margin-bottom:20px;">
                        <label style="display:block;font-weight:500;margin-bottom:8px;color:var(--gold,#d4af37);">English title</label>
                        <input type="text" name="nom_anglais" value="{{ old('nom_anglais', $articleToEdit?->nom_anglais) }}" style="width:100%;padding:12px;background:rgba(0,0,0,.4);border:1px solid #4a5568;color:#fff;border-radius:4px;font-size:1rem;">
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="display:block;font-weight:500;margin-bottom:8px;color:var(--gold,#d4af37);">English article content (HTML supported)</label>
                        <div id="editor-container-en" style="height:350px;background:rgba(0,0,0,.4);color:#fff;font-size:1rem;border-color:#4a5568;border-radius:0 0 4px 4px;"></div>
                        <textarea id="contenu_anglais" name="contenu_anglais" class="html-source" spellcheck="false" hidden>{{ old('contenu_anglais', $articleToEdit?->contenu_anglais) }}</textarea>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label
                            style="display: block; font-weight: 500; margin-bottom: 8px; color: var(--gold, #d4af37);">Contenu
                            de l'article</label>

                        <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet" />

                        <div id="editor-container"
                            style="height: 350px; background: rgba(0, 0, 0, 0.4); color: #fff; font-size: 1rem; border-color: #4a5568; border-radius: 0 0 4px 4px;">
                        </div>

                        <textarea id="contenu" name="contenu" class="html-source" spellcheck="false" hidden>{{ old('contenu', $articleToEdit ? $articleToEdit->contenu : '') }}</textarea>
                        @error('contenu') <span style="color: #f56565; font-size: 0.85rem;">{{ $message }}</span> @enderror
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="submit" id="submit-btn"
                            style="background: var(--gold, #d4af37); color: #0a0a0f; border: none; padding: 12px 25px; font-size: 0.95rem; font-weight: 600; border-radius: 4px; cursor: pointer;">
                            {{ $articleToEdit ? 'Enregistrer les modifications' : 'Publier l\'article' }}
                        </button>

                        <div style="margin-bottom: 25px;">
                            <label for="is_published"
                                style="display: block; font-weight: 500; margin-bottom: 8px; color: var(--gold, #d4af37);">Statut
                                de l'article</label>
                            <select id="is_published" name="is_published"
                                style="width: 100%; padding: 12px; background: rgba(0, 0, 0, 0.4); border: 1px solid #4a5568; color: #fff; border-radius: 4px; font-size: 1rem;">
                                <option value="1" {{ old('is_published', $articleToEdit ? $articleToEdit->is_published : 1) == 1 ? 'selected' : '' }}>🟢 En ligne (Publié)</option>
                                <option value="2" {{ old('is_published', $articleToEdit ? $articleToEdit->is_published : 1) == 2 ? 'selected' : '' }}>🔵 À venir (Teaser / Sans date)</option>
                                <option value="0" {{ old('is_published', $articleToEdit ? $articleToEdit->is_published : 1) == 0 ? 'selected' : '' }}>🔴 Brouillon (Masqué)</option>
                            </select>
                        </div>

                        @if($articleToEdit)
                            <a href="{{ route('admin.dashboard') }}"
                                style="color: #a0aec0; text-decoration: none; font-size: 0.9rem;">Annuler</a>
                        @endif
                    </div>
                </form>

                @vite(['resources/js/admin3dpreview.js'])
                <style>
                    .space-editor { margin: 0 0 20px; padding: 18px; border: 1px solid #4a5568; border-radius: 6px; background: rgba(0, 0, 0, 0.25); }
                    .space-editor legend { padding: 0 8px; color: var(--gold, #d4af37); font-weight: 500; }
                    .space-editor-grid { display: grid; grid-template-columns: minmax(0, 1fr) 220px; gap: 18px; align-items: start; }
                    .space-type-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; margin-bottom: 16px; }
                    .space-type { position: relative; }
                    .space-type input { position: absolute; opacity: 0; pointer-events: none; }
                    .space-type span { display: flex; align-items: center; gap: 8px; padding: 9px 10px; border: 1px solid #4a5568; border-radius: 4px; background: rgba(0, 0, 0, 0.35); color: #e2e8f0; font-size: 0.85rem; cursor: pointer; transition: border-color 0.2s, background 0.2s; }
                    .space-type span b { font-weight: normal; font-size: 1.1rem; }
                    .space-type input:checked + span { border-color: var(--gold, #d4af37); background: rgba(212, 175, 55, 0.14); color: #fff; }
                    .space-type input:focus-visible + span { outline: 2px solid var(--gold, #d4af37); outline-offset: 2px; }
                    .space-editor-label { display: block; margin-bottom: 8px; color: #a0aec0; font-size: 0.85rem; }
                    .space-colors { display: flex; flex-wrap: wrap; gap: 8px; }
                    .space-color { position: relative; width: 30px; height: 30px; padding: 0; border: 2px solid transparent; border-radius: 50%; background: var(--swatch, #333); cursor: pointer; box-shadow: 0 0 0 1px #4a5568; }
                    .space-color.is-auto { width: auto; padding: 0 10px; border-radius: 15px; background: rgba(0, 0, 0, 0.4); color: #e2e8f0; font-size: 0.75rem; }
                    .space-color.is-custom { overflow: hidden; background: conic-gradient(#f56565, #ecc94b, #48bb78, #4299e1, #9f7aea, #f56565); }
                    .space-color.is-custom input { position: absolute; inset: -6px; width: 44px; height: 44px; opacity: 0; cursor: pointer; }
                    .space-color.is-active { border-color: #fff; box-shadow: 0 0 0 2px var(--gold, #d4af37); }
                    .space-preview { text-align: center; }
                    #space-preview-canvas { width: 220px; height: 220px; border-radius: 6px; overflow: hidden; background: #02030f; }
                    .space-preview small { display: block; margin-top: 6px; color: #a0aec0; font-size: 0.72rem; line-height: 1.4; }
                    .space-advanced { margin-top: 16px; color: #a0aec0; font-size: 0.85rem; }
                    .space-advanced summary { color: #e2e8f0; cursor: pointer; }
                    .space-advanced p { line-height: 1.5; }
                    .space-advanced a { color: #87cefa; }
                    .space-advanced input[type="file"] { width: 100%; padding: 9px; background: rgba(0, 0, 0, 0.4); border: 1px solid #4a5568; color: #fff; border-radius: 4px; }
                    .space-file-feedback { display: block; margin-top: 6px; }
                    .space-file-feedback.is-error, .space-error { color: #f56565; font-size: 0.85rem; }
                    .space-current-model { margin-top: 10px; padding: 10px; border: 1px solid #4a5568; border-radius: 4px; }
                    .space-current-model.is-heavy { border-color: #ed8936; color: #fbd38d; }
                    .space-current-model label { display: block; margin-top: 6px; color: #e2e8f0; cursor: pointer; }
                    @media (max-width: 600px) {
                        .space-editor-grid { grid-template-columns: 1fr; }
                        #space-preview-canvas { width: 100%; }
                    }

                    .ql-toolbar.ql-snow {
                        background: #1a202c !important;
                        border-color: #4a5568 !important;
                        border-radius: 4px 4px 0 0;
                    }

                    .ql-toolbar .ql-stroke {
                        stroke: #a0aec0 !important;
                    }

                    .ql-toolbar .ql-fill {
                        fill: #a0aec0 !important;
                    }

                    .ql-toolbar .ql-picker {
                        color: #a0aec0 !important;
                    }

                    .ql-container.ql-snow {
                        border-color: #4a5568 !important;
                    }

                    .ql-snow .ql-picker-options {
                        background: #1a202c !important;
                        border-color: #4a5568 !important;
                    }

                    /* Tailles de police façon Word : le libellé affiche la valeur (ex. 12pt) */
                    .ql-snow .ql-picker.ql-size {
                        width: 70px;
                    }

                    .ql-snow .ql-picker.ql-size .ql-picker-label[data-value]::before,
                    .ql-snow .ql-picker.ql-size .ql-picker-item[data-value]::before {
                        content: attr(data-value);
                    }

                    .ql-snow .ql-picker.ql-size .ql-picker-options {
                        max-height: 260px;
                        overflow-y: auto;
                    }

                    .ql-toolbar button.ql-alinea,
                    .ql-toolbar button.ql-html {
                        width: auto;
                        color: #a0aec0;
                        font-size: 0.8rem;
                        font-weight: 600;
                    }

                    .ql-toolbar button.ql-alinea.ql-active,
                    .ql-toolbar button.ql-html.ql-active {
                        color: var(--gold, #d4af37);
                    }

                    /* En mode HTML, seuls les boutons de bascule restent utilisables */
                    .ql-toolbar.is-html-mode > .ql-formats > :not(.ql-html) {
                        opacity: 0.3;
                        pointer-events: none;
                    }

                    .html-source {
                        display: block;
                        width: 100%;
                        height: 350px;
                        padding: 12px;
                        box-sizing: border-box;
                        background: rgba(0, 0, 0, 0.6);
                        border: 1px solid #4a5568;
                        border-top: none;
                        border-radius: 0 0 4px 4px;
                        color: #e2e8f0;
                        font-family: Consolas, 'Courier New', monospace;
                        font-size: 0.85rem;
                        line-height: 1.5;
                        resize: vertical;
                    }

                    .html-source[hidden] {
                        display: none;
                    }
                </style>

                <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const Parchment = Quill.import('parchment');

                        // Tailles de police en points, comme dans Word (écrites en style inline pour s'afficher partout)
                        const FONT_SIZES = ['8pt', '9pt', '10pt', '11pt', '12pt', '14pt', '16pt', '18pt', '20pt', '24pt', '28pt', '32pt', '36pt', '48pt', '72pt'];
                        const Size = Quill.import('attributors/style/size');
                        Size.whitelist = FONT_SIZES;
                        Quill.register(Size, true);

                        // Alinéa : retrait de la première ligne du paragraphe
                        const Alinea = new Parchment.StyleAttributor('alinea', 'text-indent', {
                            scope: Parchment.Scope.BLOCK,
                            whitelist: ['2em']
                        });
                        Quill.register(Alinea, true);

                        // Configuration de la barre d'outils (Titres, Taille, Gras, Italic, Listes, Retraits, Liens, HTML)
                        const toolbarOptions = [
                            [{ 'header': [2, 3, false] }],
                            [{ 'size': [false, ...FONT_SIZES] }],
                            ['bold', 'italic', 'underline', 'blockquote'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            ['alinea', { 'indent': '-1' }, { 'indent': '+1' }],
                            ['link', 'clean'], // Liens hypertexte + bouton pour nettoyer le formatage
                            ['html'] // Bascule vers l'édition du code HTML
                        ];

                        // Ce que l'éditeur visuel sait représenter ; au-delà, on reste en mode HTML pour ne rien perdre
                        const VISUAL_TAGS = new Set(['P', 'BR', 'H2', 'H3', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'A', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'SPAN']);
                        const VISUAL_ATTRS = new Set(['href', 'target', 'rel', 'class', 'style', 'data-list']);
                        const VISUAL_STYLES = new Set(['font-size', 'text-indent']);

                        function fitsVisualEditor(html) {
                            const body = new DOMParser().parseFromString(html, 'text/html').body;
                            return [...body.querySelectorAll('*')].every(el =>
                                VISUAL_TAGS.has(el.tagName)
                                && [...el.attributes].every(attr => VISUAL_ATTRS.has(attr.name))
                                && [...el.style].every(prop => VISUAL_STYLES.has(prop))
                                && [...el.classList].every(cls => cls.startsWith('ql-'))
                            );
                        }

                        function setupEditor(containerSelector, source) {
                            let htmlMode = false;

                            const quill = new Quill(containerSelector, {
                                modules: {
                                    toolbar: {
                                        container: toolbarOptions,
                                        handlers: {
                                            alinea() {
                                                this.quill.format('alinea', this.quill.getFormat().alinea ? false : '2em', 'user');
                                            },
                                            html() {
                                                setHtmlMode(!htmlMode);
                                            }
                                        }
                                    }
                                },
                                theme: 'snow'
                            });

                            // Au rechargement, Quill ajoute une tabulation devant les paragraphes en retrait : l'alinéa suffit
                            quill.clipboard.addMatcher(Node.ELEMENT_NODE, (node, delta) => {
                                const first = delta.ops[0];
                                if (node.style.textIndent === '2em' && typeof first?.insert === 'string' && first.insert.startsWith('\t')) {
                                    first.insert = first.insert.slice(1);
                                    if (!first.insert) delta.ops.shift();
                                }
                                return delta;
                            });

                            const toolbar = quill.getModule('toolbar').container;
                            const htmlButton = toolbar.querySelector('button.ql-html');
                            htmlButton.textContent = '</>';
                            htmlButton.title = 'Écrire / voir le code HTML';
                            const alineaButton = toolbar.querySelector('button.ql-alinea');
                            alineaButton.textContent = '¶→';
                            alineaButton.title = 'Alinéa (retrait de première ligne)';

                            // getSemanticHTML de Quill 2.0.2 remplace chaque espace par &nbsp;
                            const visualHtml = () => quill.getSemanticHTML().replace(/&nbsp;/g, ' ');

                            // fromVisual = false au chargement : le textarea contient déjà le HTML d'origine, Quill est encore vide
                            function setHtmlMode(enabled, fromVisual = true) {
                                if (enabled) {
                                    if (fromVisual) source.value = visualHtml();
                                } else {
                                    if (!fitsVisualEditor(source.value)
                                        && !confirm('Ce code contient des balises ou styles que l’éditeur visuel ne gère pas : ils seront perdus. Continuer ?')) {
                                        return;
                                    }
                                    quill.clipboard.dangerouslyPasteHTML(source.value);
                                }
                                htmlMode = enabled;
                                quill.container.hidden = enabled;
                                source.hidden = !enabled;
                                toolbar.classList.toggle('is-html-mode', enabled);
                                htmlButton.classList.toggle('ql-active', enabled);
                                (enabled ? source : quill).focus();
                            }

                            if (fitsVisualEditor(source.value)) {
                                quill.clipboard.dangerouslyPasteHTML(source.value);
                                quill.history.clear();
                            } else {
                                setHtmlMode(true, false);
                            }

                            // Avant l'envoi, on recopie le HTML de l'éditeur visuel dans le champ du formulaire
                            return () => {
                                if (!htmlMode) source.value = visualHtml();
                            };
                        }

                        const syncEditors = [
                            setupEditor('#editor-container', document.getElementById('contenu')),
                            setupEditor('#editor-container-en', document.getElementById('contenu_anglais'))
                        ];

                        const form = document.getElementById('article-form');
                        form.addEventListener('submit', function () {
                            syncEditors.forEach(sync => sync());
                        });
                    });
                </script>
            </div>

            <div class="table-container">
                <h2
                    style="font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; color: #fff; margin-top: 0; margin-bottom: 20px;">
                    Articles en ligne</h2>

                <div class="responsive-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Date</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($articles as $art)
                                <tr>
                                    <td style="font-weight: 500; color: #fff;">{{ Str::limit($art->nom, 40) }}</td>
                                    <td style="font-size: 0.85rem; color: #a0aec0;">
                                        {{ \Carbon\Carbon::parse($art->created_at)->locale('fr')->isoFormat('DD MMM YYYY') }}
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 8px;">
                                            <a href="{{ route('admin.dashboard', ['edit' => $art->id]) }}"
                                                class="btn-action edit">Modifier</a>

                                            <form action="{{ route('admin.articles.delete', $art->id) }}" method="POST"
                                                onsubmit="return confirm('Supprimer définitivement cet article ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-action delete">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #a0aec0; padding: 30px 10px;">Aucun
                                        article publié pour le moment.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <details
            style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(214, 175, 55, 0.2); border-radius: 6px; margin-top: 40px; margin-bottom: 40px; padding: 15px;">
            <summary
                style="font-family: 'Cormorant Garamond', serif; font-size: 1.4rem; color: var(--gold, #d4af37); font-weight: 600; cursor: pointer; user-select: none; outline: none;">
                ⚙️ Modifier les textes et configurations du site (Identité, Profil, Accueil...)
            </summary>

            <form action="{{ route('admin.infos.update') }}" method="POST"
                style="margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                @csrf

                <details class="info-language-panel" style="grid-column:span 2;">
                    <summary>English — infos en anglais</summary>
                    <div class="info-language-fields">
                @foreach(['status_pro','ecole','hero_h1','hero_slogan','hero_tag1','hero_tag2','hero_tag3','hero_tag4','about_title','about_text','about_pillar_title1','about_pillar_content1','about_pillar_title2','about_pillar_content2','about_pillar_title3','about_pillar_content3','section_article_title','section_contact_title','section_contact_text'] as $englishField)
                    <div @if(in_array($englishField, ['about_text','section_contact_text'])) style="grid-column:span 2;" @endif>
                        <label class="info-label">{{ str($englishField)->replace('_', ' ')->headline() }} (EN)</label>
                        @if(in_array($englishField, ['about_text','about_pillar_content1','about_pillar_content2','about_pillar_content3','section_contact_text']))
                            <textarea name="en_{{ $englishField }}" rows="3" class="info-input" style="height:auto;">{{ old('en_'.$englishField, $infoEn->$englishField ?? '') }}</textarea>
                        @else
                            <input type="text" name="en_{{ $englishField }}" value="{{ old('en_'.$englishField, $infoEn->$englishField ?? '') }}" class="info-input">
                        @endif
                    </div>
                @endforeach

                </div>
                </details>

                <details class="info-language-panel" open style="grid-column:span 2;">
                    <summary>Français — infos en français</summary>
                    <div class="info-language-fields">
                <div
                    style="grid-column: span 2; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px; margin-top: 10px;">
                    <strong style="color: #fff;">Identité & Contact</strong>
                </div>
                <div>
                    <label class="info-label">Prénom</label>
                    <input type="text" name="prenom" value="{{ $info->prenom }}" class="info-input">
                </div>
                <div>
                    <label class="info-label">Nom</label>
                    <input type="text" name="nom" value="{{ $info->nom }}" class="info-input">
                </div>
                <div>
                    <label class="info-label">Email de contact</label>
                    <input type="email" name="mail" value="{{ $info->mail }}" class="info-input">
                </div>
                <div>
                    <label class="info-label">Lien LinkedIn</label>
                    <input type="url" name="linkedin" value="{{ $info->linkedin }}" class="info-input">
                </div>
                <div>
                    <label class="info-label">Statut Professionnel (ex: Étudiante en Droit)</label>
                    <input type="text" name="status_pro" value="{{ $info->status_pro }}" class="info-input">
                </div>
                <div>
                    <label class="info-label">Université / École (Footer)</label>
                    <input type="text" name="ecole" value="{{ $info->ecole }}" class="info-input">
                </div>

                <div
                    style="grid-column: span 2; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px; margin-top: 15px;">
                    <strong style="color: #fff;">Section d'accueil (Hero)</strong>
                </div>
                <div style="grid-column: span 2;">
                    <label class="info-label">Titre principal (H1)</label>
                    <input type="text" name="hero_h1" value="{{ $info->hero_h1 }}" class="info-input">
                </div>
                <div style="grid-column: span 2;">
                    <label class="info-label">Slogan sous le titre</label>
                    <input type="text" name="hero_slogan" value="{{ $info->hero_slogan }}" class="info-input">
                </div>
                <div>
                    <label class="info-label">Bannière Tag 1</label>
                    <input type="text" name="hero_tag1" value="{{ $info->hero_tag1 }}" class="info-input">
                </div>
                <div>
                    <label class="info-label">Bannière Tag 2</label>
                    <input type="text" name="hero_tag2" value="{{ $info->hero_tag2 }}" class="info-input">
                </div>
                <div>
                    <label class="info-label">Bannière Tag 3</label>
                    <input type="text" name="hero_tag3" value="{{ $info->hero_tag3 }}" class="info-input">
                </div>
                <div>
                    <label class="info-label">Bannière Tag 4</label>
                    <input type="text" name="hero_tag4" value="{{ $info->hero_tag4 }}" class="info-input">
                </div>

                <div
                    style="grid-column: span 2; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px; margin-top: 15px;">
                    <strong style="color: #fff;">Section À Propos</strong>
                </div>
                <div style="grid-column: span 2;">
                    <label class="info-label">Titre de la section À Propos</label>
                    <input type="text" name="about_title" value="{{ $info->about_title }}" class="info-input">
                </div>
                <div style="grid-column: span 2;">
                    <label class="info-label">Texte de présentation (Accepte le HTML brut si tu veux mettre des paragraphes
                        &lt;p&gt;)</label>
                    <textarea name="about_text" rows="5" class="info-input"
                        style="height: auto; font-family: monospace;">{{ $info->about_text }}</textarea>
                </div>

                <div>
                    <label class="info-label">Pilier 1 : Titre</label>
                    <input type="text" name="about_pillar_title1" value="{{ $info->about_pillar_title1 }}"
                        class="info-input">
                    <label class="info-label" style="margin-top:5px;">Pilier 1 : Contenu</label>
                    <textarea name="about_pillar_content1" rows="2" class="info-input"
                        style="height:auto;">{{ $info->about_pillar_content1 }}</textarea>
                </div>
                <div>
                    <label class="info-label">Pilier 2 : Titre</label>
                    <input type="text" name="about_pillar_title2" value="{{ $info->about_pillar_title2 }}"
                        class="info-input">
                    <label class="info-label" style="margin-top:5px;">Pilier 2 : Contenu</label>
                    <textarea name="about_pillar_content2" rows="2" class="info-input"
                        style="height:auto;">{{ $info->about_pillar_content2 }}</textarea>
                </div>
                <div style="grid-column: span 2;">
                    <label class="info-label">Pilier 3 : Titre</label>
                    <input type="text" name="about_pillar_title3" value="{{ $info->about_pillar_title3 }}"
                        class="info-input">
                    <label class="info-label" style="margin-top:5px;">Pilier 3 : Contenu</label>
                    <textarea name="about_pillar_content3" rows="2" class="info-input"
                        style="height:auto;">{{ $info->about_pillar_content3 }}</textarea>
                </div>

                <div
                    style="grid-column: span 2; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px; margin-top: 15px;">
                    <strong style="color: #fff;">Titres des rubriques Articles & Contact</strong>
                </div>
                <div>
                    <label class="info-label">Titre Section Articles</label>
                    <input type="text" name="section_article_title" value="{{ $info->section_article_title }}"
                        class="info-input">
                </div>
                <div>
                    <label class="info-label">Titre Section Contact</label>
                    <input type="text" name="section_contact_title" value="{{ $info->section_contact_title }}"
                        class="info-input">
                </div>
                <div style="grid-column: span 2;">
                    <label class="info-label">Texte d'introduction Contact</label>
                    <textarea name="section_contact_text" rows="3" class="info-input"
                        style="height:auto;">{{ $info->section_contact_text }}</textarea>
                </div>

                </div>
                </details>

                <div style="grid-column: span 2; text-align: right; margin-top: 10px;">
                    <button type="submit"
                        style="background: transparent; border: 1px solid var(--gold, #d4af37); color: var(--gold, #d4af37); padding: 10px 20px; font-weight: 600; border-radius: 4px; cursor: pointer;">
                        Enregistrer les textes globaux
                    </button>
                </div>
            </form>
        </details>

        <style>
            .info-label {
                display: block;
                font-size: 0.85rem;
                color: #a0aec0;
                margin-bottom: 5px;
            }

            .info-language-panel {
                border: 1px solid rgba(214, 175, 55, 0.25);
                border-radius: 6px;
                padding: 14px;
                background: rgba(255, 255, 255, 0.02);
            }

            .info-language-panel summary {
                color: var(--gold, #d4af37);
                font: 600 1.2rem 'Cormorant Garamond', serif;
                cursor: pointer;
                user-select: none;
            }

            .info-language-fields {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 18px;
            }

            .info-input {
                width: 100%;
                padding: 8px 12px;
                background: rgba(0, 0, 0, 0.3);
                border: 1px solid #4a5568;
                color: #fff;
                border-radius: 4px;
                font-size: 0.9rem;
            }

            /* Garde le reste de tes styles (.admin-grid, etc.) en dessous */
        </style>
    </div>



    <style>
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        .responsive-table-wrapper {
            width: 100%;
            overflow-x: auto;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 6px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95rem;
        }

        .admin-table th,
        .admin-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .admin-table th {
            background: rgba(0, 0, 0, 0.3);
            color: var(--gold, #d4af37);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        /* Actions styles */
        .btn-action {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            border: none;
        }

        .btn-action.edit {
            background: rgba(135, 206, 250, 0.15);
            color: #87cefa;
        }

        .btn-action.edit:hover {
            background: rgba(135, 206, 250, 0.25);
        }

        .btn-action.delete {
            background: rgba(252, 129, 129, 0.15);
            color: #fc8181;
        }

        .btn-action.delete:hover {
            background: rgba(252, 129, 129, 0.25);
        }

        /* Responsive mobile */
        @media (max-width: 900px) {
            .admin-grid {
                grid-template-columns: 1fr;
                gap: 50px;
            }
        }
    </style>
@endsection
