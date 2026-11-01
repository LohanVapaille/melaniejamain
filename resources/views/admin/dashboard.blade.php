@extends('layouts.app')

@section('title', 'Administration')

@section('content')
    @php
        // Les deux formulaires partagent des noms de champs (ex. « nom ») : on ne reprend les anciennes saisies que du bon formulaire
        $oldForm = old('_form');
        $initialTab = match (true) {
            $oldForm === 'infos' => 'textes',
            $oldForm === 'suggestion', request()->has('edit_suggestion') => 'suggestions',
            default => 'articles',
        };
        $articleValue = fn($field, $default) => $oldForm === 'article' ? old($field) : $default;
        $suggestionValue = fn($field) => $oldForm === 'suggestion' ? old($field) : $suggestionToEdit?->$field;
        $infoValue = fn($field, $isEn) => $oldForm === 'infos'
            ? old(($isEn ? 'en_' : '') . $field)
            : ($isEn ? ($infoEn->$field ?? '') : ($info->$field ?? ''));

        $statuses = [
            1 => ['En ligne', 'is-live', 'Visible par tous sur le site'],
            2 => ['À venir', 'is-soon', 'Annoncé sur le site comme « À venir », sans date'],
            0 => ['Brouillon', 'is-draft', 'Masqué, visible seulement ici'],
        ];
        $hasEnglish = fn($article) => filled($article->nom_anglais) && filled(trim(strip_tags($article->contenu_anglais ?? '')));
        $statusCounts = $articles->countBy(fn($a) => (int) $a->is_published);

        $selectedStatus = (int) $articleValue('is_published', $articleToEdit?->is_published ?? 1);
        $selectedVisualModel = $articleValue('visual_model', $articleToEdit?->visual_model ?? 'planet');
        $selectedVisualColor = $articleValue('visual_color', $articleToEdit?->visual_color ?? '');
        $visualModels = [
            'planet' => ['🌍', 'Planète'],
            'gas_giant' => ['🪐', 'Géante gazeuse'],
            'moon' => ['🌕', 'Lune'],
            'meteor' => ['☄️', 'Astéroïde'],
            'satellite' => ['🛰️', 'Satellite'],
            'station' => ['🛸', 'Station spatiale'],
            'star' => ['⭐', 'Étoile brillante'],
            'none' => ['🚫', 'Sans objet 3D'],
        ];
        $colorPresets = ['#2f6fb5', '#2a8f9c', '#3a7d5a', '#d9a066', '#c0564b', '#7a4fc2', '#b9b4ab', '#ffd27a'];
        $modelSize = $articleToEdit?->model_3d_size ?? null;
        $modelIsHeavy = $modelSize && $modelSize > 5 * 1024 * 1024;

        // Erreurs uniquement sur la version anglaise : on ouvre directement l'onglet English
        $articleErrorsOnlyEn = ($errors->has('nom_anglais') || $errors->has('contenu_anglais')) && !$errors->has('nom') && !$errors->has('contenu');
        $initialLang = $articleErrorsOnlyEn ? 'en' : 'fr';
        $englishDone = $articleToEdit && $hasEnglish($articleToEdit);

        // Textes du site traduits : [champ => [libellé, type, aide]]
        $infoGroups = [
            'Profil' => [
                'hint' => 'Affiché au-dessus du titre d’accueil et dans le pied de page.',
                'fields' => [
                    'status_pro' => ['Statut professionnel', 'input', 'Ex. : Étudiante en droit'],
                    'ecole' => ['Université / école', 'input', 'Pied de page'],
                ],
            ],
            'Accueil' => [
                'hint' => 'Le premier écran du site.',
                'fields' => [
                    'hero_h1' => ['Titre principal', 'input', 'HTML simple accepté (ex. <em>). Obligatoire en français.'],
                    'hero_slogan' => ['Slogan', 'input', 'Sous le titre'],
                    'hero_tag1' => ['Étiquette 1', 'input', null],
                    'hero_tag2' => ['Étiquette 2', 'input', null],
                    'hero_tag3' => ['Étiquette 3', 'input', null],
                    'hero_tag4' => ['Étiquette 4', 'input', 'Laissez vide pour masquer'],
                ],
            ],
            'À propos' => [
                'hint' => 'Présentation et les trois piliers à droite du texte.',
                'fields' => [
                    'about_title' => ['Titre de la section', 'input', null],
                    'about_text' => ['Texte de présentation', 'html', 'HTML accepté : <p>, <strong>, <em>…'],
                    'about_pillar_title1' => ['Pilier 1 · titre', 'input', null],
                    'about_pillar_content1' => ['Pilier 1 · texte', 'textarea', null],
                    'about_pillar_title2' => ['Pilier 2 · titre', 'input', null],
                    'about_pillar_content2' => ['Pilier 2 · texte', 'textarea', null],
                    'about_pillar_title3' => ['Pilier 3 · titre', 'input', null],
                    'about_pillar_content3' => ['Pilier 3 · texte', 'textarea', null],
                ],
            ],
            'Articles & contact' => [
                'hint' => 'Titres des rubriques en bas de page.',
                'fields' => [
                    'section_article_title' => ['Titre de la rubrique Articles', 'input', null],
                    'section_contact_title' => ['Titre de la rubrique Contact', 'input', null],
                    'section_contact_text' => ['Texte d’introduction Contact', 'textarea', null],
                ],
            ],
        ];
    @endphp

    <div class="adm">

        {{-- En-tête --}}
        <header class="adm-header">
            <div>
                <p class="adm-eyebrow">Espace administration</p>
                <h1 class="adm-title">Bonjour {{ $info->prenom ?? '' }}</h1>
            </div>
            <div class="adm-header-actions">
                <a href="{{ url('/') }}" class="adm-btn adm-btn-ghost" target="_blank" rel="noopener">Voir le site ↗</a>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="adm-btn adm-btn-danger-ghost">Se déconnecter</button>
                </form>
            </div>
        </header>

        @if(session('success'))
            <div class="adm-alert is-success" role="status">
                <span aria-hidden="true">✓</span> {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="adm-alert is-error" role="alert">
                <span aria-hidden="true">!</span> Certains champs sont à corriger, ils sont signalés en rouge ci-dessous.
            </div>
        @endif

        {{-- Onglets principaux --}}
        <div class="adm-tabs" role="tablist" aria-label="Sections de l’administration">
            <button type="button" class="adm-tab" role="tab" id="tab-articles" data-tab="articles"
                aria-controls="articles" aria-selected="{{ $initialTab === 'articles' ? 'true' : 'false' }}">
                Articles <span class="adm-count">{{ $articles->count() }}</span>
            </button>
            <button type="button" class="adm-tab" role="tab" id="tab-textes" data-tab="textes"
                aria-controls="textes" aria-selected="{{ $initialTab === 'textes' ? 'true' : 'false' }}">
                Textes du site
            </button>
            <button type="button" class="adm-tab" role="tab" id="tab-suggestions" data-tab="suggestions"
                aria-controls="suggestions" aria-selected="{{ $initialTab === 'suggestions' ? 'true' : 'false' }}">
                Suggestions <span class="adm-count">{{ $suggestions->count() }}</span>
            </button>
        </div>

        {{-- ═══════════════ ARTICLES ═══════════════ --}}
        <section id="articles" class="adm-panel" role="tabpanel" aria-labelledby="tab-articles" data-panel="articles" @if($initialTab !== 'articles') hidden @endif>
            <div class="adm-articles">

                {{-- Liste des articles --}}
                <aside class="adm-card adm-sidebar">
                    <div class="adm-sidebar-head">
                        <h2 class="adm-card-title">Tous les articles</h2>
                        <a href="{{ route('admin.dashboard') }}#editeur" class="adm-btn adm-btn-primary adm-btn-sm">+ Nouveau</a>
                    </div>
                    <p class="adm-sidebar-summary">
                        @foreach($statuses as $value => [$label, $class])
                            <span><i class="adm-dot {{ $class }}"></i>{{ $statusCounts[$value] ?? 0 }} {{ mb_strtolower($label) }}</span>
                        @endforeach
                    </p>

                    <ul class="adm-article-list">
                        @forelse($articles as $art)
                            @php [$statusLabel, $statusClass] = $statuses[(int) $art->is_published] ?? $statuses[0]; @endphp
                            <li class="adm-article {{ $articleToEdit?->id === $art->id ? 'is-current' : '' }}">
                                <a href="{{ route('admin.dashboard', ['edit' => $art->id]) }}#editeur" class="adm-article-link"
                                    @if($articleToEdit?->id === $art->id) aria-current="true" @endif>
                                    <span class="adm-article-title">{{ $art->nom }}</span>
                                    <span class="adm-article-meta">
                                        <span class="adm-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                        <span class="adm-badge {{ $hasEnglish($art) ? 'is-ok' : 'is-muted' }}" title="{{ $hasEnglish($art) ? 'Version anglaise rédigée' : 'Version anglaise manquante : la version française s’affiche' }}">EN {{ $hasEnglish($art) ? '✓' : '—' }}</span>
                                        <span class="adm-article-date">{{ \Carbon\Carbon::parse($art->created_at)->locale('fr')->isoFormat('D MMM YYYY') }}</span>
                                    </span>
                                </a>
                                <form action="{{ route('admin.articles.delete', $art->id) }}" method="POST" class="adm-article-delete"
                                    onsubmit="return confirm(@js('Supprimer définitivement « ' . $art->nom . ' » ? Cette action est irréversible.'));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Supprimer cet article" aria-label="Supprimer « {{ $art->nom }} »">✕</button>
                                </form>
                            </li>
                        @empty
                            <li class="adm-empty">Aucun article pour le moment. Rédigez le premier dans l’éditeur.</li>
                        @endforelse
                    </ul>
                </aside>

                {{-- Éditeur d'article --}}
                <form action="{{ route('admin.articles.store') }}" method="POST" enctype="multipart/form-data"
                    id="editeur" class="adm-editor" data-track-changes>
                    @csrf
                    <input type="hidden" name="_form" value="article">
                    <input type="hidden" id="article_id" name="id" value="{{ $articleToEdit?->id }}">

                    <div class="adm-editor-head">
                        <div>
                            <p class="adm-eyebrow">{{ $articleToEdit ? 'Modification' : 'Nouvel article' }}</p>
                            <h2 class="adm-editor-title">{{ $articleToEdit ? $articleToEdit->nom : 'Rédiger un nouvel article' }}</h2>
                            @if($articleToEdit)
                                <p class="adm-editor-meta">
                                    Créé le {{ \Carbon\Carbon::parse($articleToEdit->created_at)->locale('fr')->isoFormat('D MMMM YYYY') }}
                                    @if($articleToEdit->updated_at)
                                        · modifié le {{ \Carbon\Carbon::parse($articleToEdit->updated_at)->locale('fr')->isoFormat('D MMMM YYYY [à] HH:mm') }}
                                    @endif
                                </p>
                            @endif
                        </div>
                        @if($articleToEdit && $articleToEdit->is_published)
                            <a href="{{ \App\Support\Seo::articleUrl($articleToEdit, 'fr') }}" target="_blank" rel="noopener" class="adm-btn adm-btn-ghost adm-btn-sm">Voir sur le site ↗</a>
                        @endif
                    </div>

                    {{-- 1. Contenu FR / EN --}}
                    <div class="adm-card">
                        <div class="adm-card-head">
                            <h3 class="adm-card-title"><span class="adm-step">1</span> Contenu</h3>
                            <div class="adm-langs" role="tablist" aria-label="Langue de l’article">
                                <button type="button" role="tab" class="adm-lang" data-lang="fr" aria-controls="lang-fr"
                                    aria-selected="{{ $initialLang === 'fr' ? 'true' : 'false' }}">
                                    <span class="adm-lang-code">FR</span> Français
                                </button>
                                <button type="button" role="tab" class="adm-lang" data-lang="en" aria-controls="lang-en"
                                    aria-selected="{{ $initialLang === 'en' ? 'true' : 'false' }}">
                                    <span class="adm-lang-code">EN</span> English
                                    <span class="adm-badge {{ $englishDone ? 'is-ok' : 'is-muted' }}">{{ $englishDone ? 'traduit' : 'à traduire' }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="adm-lang-panel" id="lang-fr" role="tabpanel" data-lang-panel="fr" @if($initialLang !== 'fr') hidden @endif>
                            <div class="adm-field">
                                <label for="nom">Titre <span class="adm-required">obligatoire</span></label>
                                <input type="text" id="nom" name="nom" required maxlength="255" class="adm-input adm-input-lg"
                                    value="{{ $articleValue('nom', $articleToEdit?->nom) }}" placeholder="Ex. : À qui appartient la Lune ?"
                                    @error('nom') aria-invalid="true" @enderror>
                                @error('nom') <span class="adm-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="adm-field">
                                <label for="contenu">Texte de l’article <span class="adm-required">obligatoire</span></label>
                                <div id="editor-container" class="adm-quill"></div>
                                <textarea id="contenu" name="contenu" class="html-source" spellcheck="false" hidden>{{ $articleValue('contenu', $articleToEdit?->contenu) }}</textarea>
                                @error('contenu') <span class="adm-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="adm-lang-panel" id="lang-en" role="tabpanel" data-lang-panel="en" @if($initialLang !== 'en') hidden @endif>
                            <p class="adm-hint-box">Facultatif. Tant que la version anglaise est vide, les visiteurs anglophones voient la version française.</p>
                            <div class="adm-field">
                                <label for="nom_anglais">English title</label>
                                <input type="text" id="nom_anglais" name="nom_anglais" maxlength="255" class="adm-input adm-input-lg"
                                    value="{{ $articleValue('nom_anglais', $articleToEdit?->nom_anglais) }}" placeholder="Ex. : Who owns the Moon?"
                                    @error('nom_anglais') aria-invalid="true" @enderror>
                                @error('nom_anglais') <span class="adm-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="adm-field">
                                <label for="contenu_anglais">Article text</label>
                                <div id="editor-container-en" class="adm-quill"></div>
                                <textarea id="contenu_anglais" name="contenu_anglais" class="html-source" spellcheck="false" hidden>{{ $articleValue('contenu_anglais', $articleToEdit?->contenu_anglais) }}</textarea>
                                @error('contenu_anglais') <span class="adm-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- 2. Astre 3D --}}
                    <fieldset class="adm-card space-editor {{ $selectedVisualModel === 'none' ? 'is-none' : '' }}" data-article-id="{{ $articleToEdit?->id ?? 1 }}">
                        <legend class="adm-card-title"><span class="adm-step">2</span> Astre dans la visualisation 3D</legend>

                        <div class="space-editor-grid">
                            <div>
                                <span class="space-editor-label">Type d’astre</span>
                                <div class="space-type-list" role="radiogroup" aria-label="Type d’astre">
                                    @foreach($visualModels as $value => [$icon, $label])
                                        <label class="space-type">
                                            <input type="radio" name="visual_model" value="{{ $value }}" {{ $selectedVisualModel === $value ? 'checked' : '' }}>
                                            <span><b aria-hidden="true">{{ $icon }}</b>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <p class="space-none-note">Cet article ne sera pas affiché dans la visualisation 3D, mais reste visible normalement sur le site.</p>

                                <div class="space-color-block">
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
                                @error('visual_color') <span class="adm-error">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="space-preview">
                                <div id="space-preview-canvas"></div>
                                <small>Aperçu en direct</small>
                            </div>
                        </div>

                        <details class="space-advanced" {{ $articleToEdit?->model_3d_path || $errors->has('model_3d') ? 'open' : '' }}>
                            <summary>Avancé : utiliser mon propre modèle .glb</summary>
                            <p>Facultatif et déconseillé : les astres intégrés s’affichent instantanément. Si vous utilisez votre propre modèle, compressez-le d’abord sur <a href="https://gltf.report" target="_blank" rel="noopener">gltf.report</a> (Draco ou Meshopt). <strong>15 Mo maximum</strong>, idéalement moins de 3 Mo.</p>
                            <input type="file" id="model_3d" name="model_3d" accept=".glb,model/gltf-binary" data-max-size="{{ 15 * 1024 * 1024 }}">
                            <small id="model_3d_feedback" class="space-file-feedback"></small>
                            @error('model_3d') <span class="adm-error">{{ $message }}</span> @enderror
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

                    {{-- 3. Publication (barre collante) --}}
                    <div class="adm-savebar">
                        <div class="adm-savebar-status">
                            <span class="adm-savebar-label"><span class="adm-step">3</span> Statut</span>
                            <div class="adm-status" role="radiogroup" aria-label="Statut de l’article">
                                @foreach($statuses as $value => [$label, $class, $help])
                                    <label class="adm-status-option" title="{{ $help }}">
                                        <input type="radio" name="is_published" value="{{ $value }}" {{ $selectedStatus === $value ? 'checked' : '' }}>
                                        <span><i class="adm-dot {{ $class }}"></i>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="adm-savebar-actions">
                            <span class="adm-unsaved" hidden>Modifications non enregistrées</span>
                            @if($articleToEdit)
                                <a href="{{ route('admin.dashboard') }}#articles" class="adm-btn adm-btn-ghost">Fermer</a>
                            @endif
                            <button type="submit" class="adm-btn adm-btn-primary">
                                {{ $articleToEdit ? 'Enregistrer' : 'Créer l’article' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        {{-- ═══════════════ TEXTES DU SITE ═══════════════ --}}
        <section id="textes" class="adm-panel" role="tabpanel" aria-labelledby="tab-textes" data-panel="textes" @if($initialTab !== 'textes') hidden @endif>
            <form action="{{ route('admin.infos.update') }}" method="POST" class="adm-texts" data-track-changes>
                @csrf
                <input type="hidden" name="_form" value="infos">

                {{-- Identité : commune aux deux langues --}}
                <div class="adm-card">
                    <div class="adm-card-head">
                        <div>
                            <h2 class="adm-card-title">Identité & contact</h2>
                            <p class="adm-card-hint">Identique en français et en anglais.</p>
                        </div>
                    </div>
                    <div class="adm-grid-2">
                        <div class="adm-field">
                            <label for="info-prenom">Prénom</label>
                            <input type="text" id="info-prenom" name="prenom" value="{{ $infoValue('prenom', false) }}" class="adm-input">
                        </div>
                        <div class="adm-field">
                            <label for="info-nom">Nom</label>
                            <input type="text" id="info-nom" name="nom" value="{{ $infoValue('nom', false) }}" class="adm-input">
                        </div>
                        <div class="adm-field">
                            <label for="info-mail">E-mail affiché sur le site</label>
                            <input type="email" id="info-mail" name="mail" value="{{ $infoValue('mail', false) }}" class="adm-input" @error('mail') aria-invalid="true" @enderror>
                            @error('mail') <span class="adm-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="adm-field">
                            <label for="info-linkedin">Lien LinkedIn</label>
                            <input type="url" id="info-linkedin" name="linkedin" value="{{ $infoValue('linkedin', false) }}" class="adm-input">
                        </div>
                    </div>
                    <p class="adm-hint-box">
                        Formulaire de contact : les messages sont envoyés à
                        <strong>{{ config('mail.contact_to') ?: 'aucune adresse' }}</strong>
                        @unless(config('mail.contact_to')) — à renseigner dans le fichier .env (<code>CONTACT_MAIL_TO</code>) @endunless.
                    </p>
                </div>

                {{-- Textes traduits : français et anglais côte à côte --}}
                @foreach($infoGroups as $groupTitle => $group)
                    <div class="adm-card">
                        <div class="adm-card-head">
                            <div>
                                <h2 class="adm-card-title">{{ $groupTitle }}</h2>
                                <p class="adm-card-hint">{{ $group['hint'] }}</p>
                            </div>
                        </div>

                        <div class="adm-trow adm-thead" aria-hidden="true">
                            <span></span>
                            <span><span class="adm-lang-code">FR</span> Français</span>
                            <span><span class="adm-lang-code">EN</span> English <small>— vide = texte français</small></span>
                        </div>

                        @foreach($group['fields'] as $field => [$label, $type, $hint])
                            <div class="adm-trow">
                                <div class="adm-trow-label">
                                    <span>{{ $label }}</span>
                                    @if($hint) <small>{{ $hint }}</small> @endif
                                </div>
                                @foreach([false, true] as $isEn)
                                    @php
                                        $name = ($isEn ? 'en_' : '') . $field;
                                        $value = $infoValue($field, $isEn);
                                        $placeholder = $isEn ? Str::limit(strip_tags($infoValue($field, false)), 70) : '';
                                        $aria = $label . ($isEn ? ' (anglais)' : ' (français)');
                                    @endphp
                                    <div class="adm-trow-field">
                                        <span class="adm-lang-code adm-lang-code-mobile">{{ $isEn ? 'EN' : 'FR' }}</span>
                                        @if($type === 'input')
                                            <input type="text" name="{{ $name }}" value="{{ $value }}" class="adm-input" aria-label="{{ $aria }}"
                                                placeholder="{{ $placeholder }}" @error($name) aria-invalid="true" @enderror>
                                        @else
                                            <textarea name="{{ $name }}" rows="{{ $type === 'html' ? 7 : 3 }}" class="adm-input {{ $type === 'html' ? 'is-code' : '' }}"
                                                aria-label="{{ $aria }}" placeholder="{{ $placeholder }}">{{ $value }}</textarea>
                                        @endif
                                        @error($name) <span class="adm-error">{{ $message }}</span> @enderror
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="adm-savebar">
                    <p class="adm-savebar-label">Textes affichés sur la page d’accueil</p>
                    <div class="adm-savebar-actions">
                        <span class="adm-unsaved" hidden>Modifications non enregistrées</span>
                        <button type="submit" class="adm-btn adm-btn-primary">Enregistrer les textes</button>
                    </div>
                </div>
            </form>
        </section>

        {{-- ═══════════════ SUGGESTIONS ═══════════════ --}}
        <section id="suggestions" class="adm-panel" role="tabpanel" aria-labelledby="tab-suggestions" data-panel="suggestions" @if($initialTab !== 'suggestions') hidden @endif>
            <div class="adm-suggestions">

                {{-- Liste des suggestions --}}
                <div class="adm-card adm-suggestion-board">
                    <div class="adm-card-head">
                        <div>
                            <h2 class="adm-card-title">Idées reçues</h2>
                            <p class="adm-card-hint">Visibles uniquement ici, jamais sur le site.</p>
                        </div>
                        <a href="{{ route('admin.dashboard') }}#suggestion-editor" class="adm-btn adm-btn-primary adm-btn-sm">+ Ajouter</a>
                    </div>

                    <ul class="adm-suggestion-list">
                        @forelse($suggestions as $suggestion)
                            <li class="adm-suggestion {{ $suggestionToEdit?->id === $suggestion->id ? 'is-current' : '' }}">
                                <p class="adm-suggestion-idea">{{ $suggestion->idea }}</p>
                                <div class="adm-suggestion-foot">
                                    <p class="adm-suggestion-meta">
                                        @if($suggestion->email)
                                            <a href="mailto:{{ $suggestion->email }}">{{ $suggestion->email }}</a>
                                        @else
                                            <span>Sans e-mail</span>
                                        @endif
                                        <span>{{ \Carbon\Carbon::parse($suggestion->created_at)->locale('fr')->isoFormat('D MMM YYYY') }}</span>
                                    </p>
                                    <div class="adm-suggestion-actions">
                                        <a href="{{ route('admin.dashboard', ['edit_suggestion' => $suggestion->id]) }}#suggestion-editor" class="adm-btn adm-btn-ghost adm-btn-sm"
                                            @if($suggestionToEdit?->id === $suggestion->id) aria-current="true" @endif>Modifier</a>
                                        <form action="{{ route('admin.suggestions.delete', $suggestion->id) }}" method="POST"
                                            onsubmit="return confirm(@js('Supprimer définitivement cette suggestion ? Cette action est irréversible.'));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="adm-btn adm-btn-danger-ghost adm-btn-sm">Supprimer</button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="adm-empty">Aucune suggestion pour le moment. Celles des visiteurs apparaîtront ici.</li>
                        @endforelse
                    </ul>
                </div>

                {{-- Ajout / modification --}}
                <form action="{{ route('admin.suggestions.store') }}" method="POST" id="suggestion-editor" class="adm-card adm-suggestion-editor" data-track-changes>
                    @csrf
                    <input type="hidden" name="_form" value="suggestion">
                    <input type="hidden" name="id" value="{{ $suggestionToEdit?->id }}">

                    <p class="adm-eyebrow">{{ $suggestionToEdit ? 'Modification' : 'Nouvelle suggestion' }}</p>
                    <h2 class="adm-card-title adm-suggestion-editor-title">{{ $suggestionToEdit ? 'Modifier la suggestion' : 'Ajouter une suggestion' }}</h2>

                    <div class="adm-field">
                        <label for="suggestion-idea">Idée <span class="adm-required">obligatoire</span></label>
                        <textarea id="suggestion-idea" name="idea" rows="6" required maxlength="2000" class="adm-input"
                            placeholder="Ex. : qui est responsable des débris spatiaux ?" @error('idea') aria-invalid="true" @enderror>{{ $suggestionValue('idea') }}</textarea>
                        @error('idea') <span class="adm-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="adm-field">
                        <label for="suggestion-email">Adresse e-mail <span class="adm-required">facultative ici</span></label>
                        <input type="email" id="suggestion-email" name="email" maxlength="255" class="adm-input"
                            value="{{ $suggestionValue('email') }}" placeholder="nom@exemple.fr" @error('email') aria-invalid="true" @enderror>
                        @error('email') <span class="adm-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="adm-suggestion-editor-actions">
                        <span class="adm-unsaved" hidden>Modifications non enregistrées</span>
                        @if($suggestionToEdit)
                            <a href="{{ route('admin.dashboard') }}#suggestions" class="adm-btn adm-btn-ghost">Annuler</a>
                        @endif
                        <button type="submit" class="adm-btn adm-btn-primary">{{ $suggestionToEdit ? 'Enregistrer' : 'Ajouter' }}</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet" />
    @vite(['resources/js/admin3dpreview.js'])

    <style>
        .adm {
            --adm-surface: rgba(11, 14, 58, 0.78);
            --adm-field: rgba(0, 0, 0, 0.3);
            --adm-line: rgba(201, 168, 76, 0.22);
            --adm-line-soft: rgba(255, 255, 255, 0.08);
            --adm-text: #f0eeea;
            --adm-muted: #9ba3c2;
            --adm-gold: var(--gold, #c9a84c);
            --adm-gold-lt: var(--gold-lt, #e8c97a);
            --adm-ok: #68d391;
            --adm-info: #87cefa;
            --adm-danger: #fc8181;
            max-width: 1320px;
            margin: 100px auto 60px;
            padding: 0 20px;
            color: var(--adm-text);
            font-size: 0.92rem;
        }

        .adm [hidden] { display: none !important; }

        /* En-tête */
        .adm-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; }
        .adm-eyebrow { margin: 0 0 4px; color: var(--adm-gold); font-size: 0.68rem; letter-spacing: 0.16em; text-transform: uppercase; }
        .adm-title { margin: 0; font: 400 2.4rem/1.1 'Cormorant Garamond', serif; color: #fff; }
        .adm-header-actions { display: flex; gap: 10px; align-items: center; }
        .adm-header-actions form { margin: 0; }

        /* Boutons */
        .adm-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 18px; border: 1px solid transparent; border-radius: 4px; font: inherit; font-size: 0.85rem; font-weight: 500; text-decoration: none; cursor: pointer; transition: background 0.2s, border-color 0.2s, color 0.2s; white-space: nowrap; }
        .adm-btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .adm-btn-primary { background: var(--adm-gold); color: #0a0a1f; }
        .adm-btn-primary:hover { background: var(--adm-gold-lt); }
        .adm-btn-ghost { border-color: var(--adm-line); color: var(--adm-text); background: transparent; }
        .adm-btn-ghost:hover { border-color: var(--adm-gold); color: var(--adm-gold-lt); }
        .adm-btn-danger-ghost { border-color: rgba(252, 129, 129, 0.4); color: var(--adm-danger); background: transparent; }
        .adm-btn-danger-ghost:hover { background: var(--adm-danger); color: #000; }
        .adm-btn:focus-visible, .adm-tab:focus-visible, .adm-lang:focus-visible { outline: 2px solid var(--adm-gold); outline-offset: 2px; }

        /* Alertes */
        .adm-alert { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; padding: 12px 16px; border-radius: 4px; border: 1px solid; }
        .adm-alert.is-success { background: rgba(104, 211, 145, 0.12); border-color: rgba(104, 211, 145, 0.5); color: var(--adm-ok); }
        .adm-alert.is-error { background: rgba(252, 129, 129, 0.1); border-color: rgba(252, 129, 129, 0.5); color: var(--adm-danger); }

        /* Onglets principaux */
        .adm-tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 1px solid var(--adm-line); }
        .adm-tab { display: inline-flex; align-items: center; gap: 8px; padding: 12px 18px; margin-bottom: -1px; border: none; border-bottom: 2px solid transparent; background: none; color: var(--adm-muted); font: 500 0.95rem 'Inter', sans-serif; cursor: pointer; }
        .adm-tab:hover { color: var(--adm-text); }
        .adm-tab[aria-selected="true"] { color: var(--adm-gold-lt); border-bottom-color: var(--adm-gold); }
        .adm-count { padding: 1px 8px; border-radius: 10px; background: rgba(201, 168, 76, 0.15); color: var(--adm-gold); font-size: 0.75rem; }
        .adm-panel { scroll-margin-top: 90px; }

        /* Cartes */
        .adm-card { margin: 0 0 20px; padding: 22px; border: 1px solid var(--adm-line); border-radius: 8px; background: var(--adm-surface); backdrop-filter: blur(6px); min-width: 0; }
        .adm-card-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
        .adm-card-title { display: flex; align-items: center; gap: 10px; margin: 0; padding: 0; color: #fff; font: 600 1.35rem 'Cormorant Garamond', serif; }
        .adm-card-hint { margin: 4px 0 0; color: var(--adm-muted); font-size: 0.82rem; }
        .adm-step { display: inline-grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; border: 1px solid var(--adm-gold); color: var(--adm-gold); font: 600 0.72rem 'Inter', sans-serif; }
        .adm-hint-box { margin: 0 0 18px; padding: 10px 14px; border-left: 2px solid var(--adm-info); background: rgba(135, 206, 250, 0.07); color: #c9d6ea; font-size: 0.83rem; line-height: 1.5; }
        .adm-card > .adm-hint-box:last-child { margin: 8px 0 0; }
        .adm-hint-box code { color: var(--adm-gold-lt); }

        /* Champs */
        .adm-field { margin-bottom: 18px; }
        .adm-field:last-child { margin-bottom: 0; }
        .adm-field > label { display: flex; align-items: baseline; gap: 8px; margin-bottom: 8px; color: var(--adm-text); font-weight: 500; font-size: 0.85rem; }
        .adm-required { color: var(--adm-muted); font-weight: 400; font-size: 0.72rem; }
        .adm-input { width: 100%; padding: 10px 12px; border: 1px solid #4a5568; border-radius: 4px; background: var(--adm-field); color: #fff; font: inherit; font-size: 0.9rem; transition: border-color 0.2s; }
        .adm-input:focus { outline: none; border-color: var(--adm-gold); }
        .adm-input::placeholder { color: rgba(155, 163, 194, 0.55); }
        .adm-input-lg { padding: 12px 14px; font-size: 1.05rem; }
        .adm-input.is-code { font: 0.82rem/1.5 Consolas, 'Courier New', monospace; }
        textarea.adm-input { resize: vertical; line-height: 1.5; }
        .adm [aria-invalid="true"] { border-color: var(--adm-danger) !important; }
        .adm-error { display: block; margin-top: 6px; color: var(--adm-danger); font-size: 0.8rem; }
        .adm-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px; }
        .adm-grid-2 .adm-field { margin-bottom: 0; }

        /* Badges et pastilles */
        .adm-badge { display: inline-block; padding: 1px 7px; border-radius: 3px; font-size: 0.68rem; font-weight: 500; letter-spacing: 0.02em; }
        .adm-badge.is-live { background: rgba(104, 211, 145, 0.15); color: var(--adm-ok); }
        .adm-badge.is-soon { background: rgba(135, 206, 250, 0.15); color: var(--adm-info); }
        .adm-badge.is-draft { background: rgba(160, 174, 192, 0.15); color: #a0aec0; }
        .adm-badge.is-ok { background: rgba(104, 211, 145, 0.12); color: var(--adm-ok); }
        .adm-badge.is-muted { background: rgba(255, 255, 255, 0.06); color: var(--adm-muted); }
        .adm-dot { display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; vertical-align: 1px; }
        .adm-dot.is-live { background: var(--adm-ok); }
        .adm-dot.is-soon { background: var(--adm-info); }
        .adm-dot.is-draft { background: #a0aec0; }
        .adm-lang-code { display: inline-block; padding: 1px 5px; border: 1px solid currentColor; border-radius: 3px; font-size: 0.65rem; font-weight: 600; letter-spacing: 0.06em; opacity: 0.85; }

        /* Mise en page Articles : liste + éditeur */
        .adm-articles { display: grid; grid-template-columns: 330px minmax(0, 1fr); gap: 24px; align-items: start; }
        .adm-sidebar { position: sticky; top: 90px; padding: 18px 0 8px; }
        .adm-sidebar-head { display: flex; justify-content: space-between; align-items: center; padding: 0 18px; }
        .adm-sidebar-summary { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 10px 0 12px; padding: 0 18px 12px; border-bottom: 1px solid var(--adm-line-soft); color: var(--adm-muted); font-size: 0.75rem; }
        .adm-article-list { list-style: none; margin: 0; padding: 0; max-height: calc(100vh - 260px); overflow-y: auto; }
        .adm-article { position: relative; display: flex; align-items: stretch; border-left: 3px solid transparent; }
        .adm-article:hover { background: rgba(255, 255, 255, 0.03); }
        .adm-article.is-current { border-left-color: var(--adm-gold); background: rgba(201, 168, 76, 0.1); }
        .adm-article-link { flex: 1; min-width: 0; padding: 11px 8px 11px 15px; color: inherit; text-decoration: none; }
        .adm-article-title { display: block; margin-bottom: 6px; color: #fff; font-size: 0.88rem; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .adm-article-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .adm-article-date { color: var(--adm-muted); font-size: 0.72rem; }
        .adm-article-delete { display: flex; margin: 0; }
        .adm-article-delete button { padding: 0 14px; border: none; background: none; color: var(--adm-muted); cursor: pointer; opacity: 0; transition: opacity 0.2s, color 0.2s; }
        .adm-article:hover .adm-article-delete button, .adm-article-delete button:focus-visible { opacity: 1; }
        .adm-article-delete button:hover { color: var(--adm-danger); }
        .adm-empty { padding: 18px; color: var(--adm-muted); font-size: 0.85rem; }

        /* Éditeur */
        .adm-editor { min-width: 0; scroll-margin-top: 90px; }
        .adm-editor-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 16px; }
        .adm-editor-title { margin: 0; color: #fff; font: 400 1.9rem/1.2 'Cormorant Garamond', serif; }
        .adm-editor-meta { margin: 6px 0 0; color: var(--adm-muted); font-size: 0.8rem; }

        /* Sélecteur de langue */
        .adm-langs { display: inline-flex; padding: 3px; border: 1px solid var(--adm-line); border-radius: 6px; background: rgba(0, 0, 0, 0.3); }
        .adm-lang { display: inline-flex; align-items: center; gap: 7px; padding: 7px 14px; border: none; border-radius: 4px; background: none; color: var(--adm-muted); font: 500 0.85rem 'Inter', sans-serif; cursor: pointer; }
        .adm-lang:hover { color: var(--adm-text); }
        .adm-lang[aria-selected="true"] { background: rgba(201, 168, 76, 0.18); color: var(--adm-gold-lt); }

        /* Barre d'enregistrement collante */
        .adm-savebar { position: sticky; bottom: 12px; z-index: 5; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 14px 18px; border: 1px solid var(--adm-gold); border-radius: 8px; background: rgba(7, 9, 46, 0.94); backdrop-filter: blur(8px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45); }
        .adm-savebar-status { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .adm-savebar-label { display: inline-flex; align-items: center; gap: 8px; margin: 0; color: var(--adm-muted); font-size: 0.85rem; }
        .adm-savebar-actions { display: flex; align-items: center; gap: 10px; margin-left: auto; }
        .adm-unsaved { color: var(--adm-gold-lt); font-size: 0.78rem; }
        .adm-unsaved::before { content: '●'; margin-right: 6px; font-size: 0.6rem; vertical-align: 2px; }
        .adm-status { display: inline-flex; flex-wrap: wrap; gap: 6px; }
        .adm-status-option input { position: absolute; opacity: 0; pointer-events: none; }
        .adm-status-option span { display: inline-flex; align-items: center; padding: 6px 12px; border: 1px solid #4a5568; border-radius: 20px; color: var(--adm-muted); font-size: 0.82rem; cursor: pointer; transition: border-color 0.2s, color 0.2s; }
        .adm-status-option input:checked + span { border-color: var(--adm-gold); color: #fff; background: rgba(201, 168, 76, 0.12); }
        .adm-status-option input:focus-visible + span { outline: 2px solid var(--adm-gold); outline-offset: 2px; }

        /* Textes du site : FR et EN côte à côte */
        .adm-trow { display: grid; grid-template-columns: 210px minmax(0, 1fr) minmax(0, 1fr); gap: 16px; padding: 14px 0; border-top: 1px solid var(--adm-line-soft); }
        .adm-thead { padding: 0 0 10px; border-top: none; color: var(--adm-gold); font-size: 0.8rem; font-weight: 500; }
        .adm-thead small { color: var(--adm-muted); font-weight: 400; }
        .adm-trow-label { padding-top: 9px; }
        .adm-trow-label span { display: block; font-weight: 500; font-size: 0.85rem; }
        .adm-trow-label small { display: block; margin-top: 3px; color: var(--adm-muted); font-size: 0.75rem; line-height: 1.4; }
        .adm-lang-code-mobile { display: none; }

        /* Suggestions : liste + formulaire */
        .adm-suggestions { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 24px; align-items: start; }
        .adm-suggestion-editor { position: sticky; top: 90px; scroll-margin-top: 90px; }
        .adm-suggestion-editor-title { margin-bottom: 18px; }
        .adm-suggestion-editor-actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 10px; }
        .adm-suggestion-editor-actions .adm-unsaved { margin-right: auto; }
        .adm-suggestion-list { list-style: none; margin: 0; padding: 0; }
        .adm-suggestion { padding: 16px 14px; border-top: 1px solid var(--adm-line-soft); border-left: 3px solid transparent; }
        .adm-suggestion.is-current { border-left-color: var(--adm-gold); background: rgba(201, 168, 76, 0.1); }
        .adm-suggestion-idea { margin: 0 0 12px; color: #fff; line-height: 1.55; white-space: pre-line; overflow-wrap: anywhere; }
        .adm-suggestion-foot { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .adm-suggestion-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 0; color: var(--adm-muted); font-size: 0.78rem; overflow-wrap: anywhere; }
        .adm-suggestion-meta a { color: var(--adm-info); }
        .adm-suggestion-actions { display: flex; gap: 8px; }
        .adm-suggestion-actions form { margin: 0; }

        /* Astre 3D (classes utilisées par admin3dpreview.js) */
        /* Légende flottante : elle reste dans la carte au lieu de chevaucher la bordure du fieldset */
        .space-editor > legend { float: left; width: 100%; margin-bottom: 18px; }
        .space-editor > legend + * { clear: both; }
        .space-editor-grid { display: grid; grid-template-columns: minmax(0, 1fr) 220px; gap: 22px; align-items: start; }
        .space-type-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; margin-bottom: 18px; }
        .space-type { position: relative; }
        .space-type input { position: absolute; opacity: 0; pointer-events: none; }
        .space-type span { display: flex; align-items: center; gap: 8px; padding: 9px 10px; border: 1px solid #4a5568; border-radius: 4px; background: rgba(0, 0, 0, 0.35); color: #e2e8f0; font-size: 0.85rem; cursor: pointer; transition: border-color 0.2s, background 0.2s; }
        .space-type span b { font-weight: normal; font-size: 1.1rem; }
        .space-type input:checked + span { border-color: var(--adm-gold); background: rgba(201, 168, 76, 0.14); color: #fff; }
        .space-type input:focus-visible + span { outline: 2px solid var(--adm-gold); outline-offset: 2px; }
        .space-editor-label { display: block; margin-bottom: 8px; color: var(--adm-text); font-weight: 500; font-size: 0.85rem; }
        .space-colors { display: flex; flex-wrap: wrap; gap: 8px; }
        .space-color { position: relative; width: 30px; height: 30px; padding: 0; border: 2px solid transparent; border-radius: 50%; background: var(--swatch, #333); cursor: pointer; box-shadow: 0 0 0 1px #4a5568; }
        .space-color.is-auto { width: auto; padding: 0 10px; border-radius: 15px; background: rgba(0, 0, 0, 0.4); color: #e2e8f0; font-size: 0.75rem; }
        .space-color.is-custom { overflow: hidden; background: conic-gradient(#f56565, #ecc94b, #48bb78, #4299e1, #9f7aea, #f56565); }
        .space-color.is-custom input { position: absolute; inset: -6px; width: 44px; height: 44px; opacity: 0; cursor: pointer; }
        .space-color.is-active { border-color: #fff; box-shadow: 0 0 0 2px var(--adm-gold); }
        .space-preview { text-align: center; }
        #space-preview-canvas { width: 220px; height: 220px; border-radius: 6px; overflow: hidden; background: #02030f; }
        .space-preview small { display: block; margin-top: 6px; color: var(--adm-muted); font-size: 0.72rem; }
        .space-advanced { margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--adm-line-soft); color: var(--adm-muted); font-size: 0.85rem; }
        .space-advanced summary { color: #e2e8f0; cursor: pointer; }
        .space-advanced p { line-height: 1.5; }
        .space-advanced a { color: var(--adm-info); }
        .space-advanced input[type="file"] { width: 100%; padding: 9px; background: rgba(0, 0, 0, 0.4); border: 1px solid #4a5568; color: #fff; border-radius: 4px; }
        .space-file-feedback { display: block; margin-top: 6px; }
        .space-none-note { display: none; margin: 0; color: var(--adm-muted); font-size: 0.85rem; line-height: 1.5; }
        .space-editor.is-none .space-none-note { display: block; }
        .space-editor.is-none .space-color-block,
        .space-editor.is-none .space-preview,
        .space-editor.is-none .space-advanced { display: none; }
        .space-editor.is-none .space-editor-grid { grid-template-columns: minmax(0, 1fr); }
        .space-file-feedback.is-error { color: var(--adm-danger); font-size: 0.85rem; }
        .space-current-model { margin-top: 10px; padding: 10px; border: 1px solid #4a5568; border-radius: 4px; }
        .space-current-model.is-heavy { border-color: #ed8936; color: #fbd38d; }
        .space-current-model label { display: block; margin-top: 6px; color: #e2e8f0; cursor: pointer; }

        /* Éditeur de texte Quill */
        .adm-quill { height: 420px; background: var(--adm-field); color: #fff; font-size: 1rem; }
        .ql-toolbar.ql-snow { background: #1a202c; border-color: #4a5568 !important; border-radius: 4px 4px 0 0; }
        .ql-container.ql-snow { border-color: #4a5568 !important; border-radius: 0 0 4px 4px; }
        .ql-toolbar .ql-stroke { stroke: #a0aec0 !important; }
        .ql-toolbar .ql-fill { fill: #a0aec0 !important; }
        .ql-toolbar .ql-picker { color: #a0aec0 !important; }
        .ql-snow .ql-picker-options { background: #1a202c !important; border-color: #4a5568 !important; }
        .ql-snow .ql-tooltip { background: #1a202c; border-color: #4a5568; color: #e2e8f0; box-shadow: none; }
        .ql-snow .ql-tooltip input[type=text] { background: #0b0e3a; border-color: #4a5568; color: #fff; }
        .ql-editor.ql-blank::before { color: rgba(155, 163, 194, 0.6); }
        /* Tailles de police façon Word : le libellé affiche la valeur (ex. 12pt) */
        .ql-snow .ql-picker.ql-size { width: 70px; }
        .ql-snow .ql-picker.ql-size .ql-picker-label[data-value]::before,
        .ql-snow .ql-picker.ql-size .ql-picker-item[data-value]::before { content: attr(data-value); }
        .ql-snow .ql-picker.ql-size .ql-picker-options { max-height: 260px; overflow-y: auto; }
        .ql-toolbar button.ql-alinea, .ql-toolbar button.ql-html { width: auto; color: #a0aec0; font-size: 0.8rem; font-weight: 600; }
        .ql-toolbar button.ql-alinea.ql-active, .ql-toolbar button.ql-html.ql-active { color: var(--adm-gold); }
        /* En mode HTML, seuls les boutons de bascule restent utilisables */
        .ql-toolbar.is-html-mode > .ql-formats > :not(.ql-html) { opacity: 0.3; pointer-events: none; }
        .html-source { display: block; width: 100%; height: 420px; padding: 12px; background: rgba(0, 0, 0, 0.6); border: 1px solid #4a5568; border-top: none; border-radius: 0 0 4px 4px; color: #e2e8f0; font: 0.85rem/1.5 Consolas, 'Courier New', monospace; resize: vertical; }

        /* Responsive */
        @media (max-width: 1000px) {
            .adm-articles { grid-template-columns: 1fr; }
            .adm-suggestions { grid-template-columns: 1fr; }
            .adm-suggestion-editor { position: static; }
            .adm-sidebar { position: static; }
            .adm-article-list { max-height: 320px; }
            .adm-article-delete button { opacity: 1; }
            .adm-trow { grid-template-columns: 1fr 1fr; }
            .adm-trow-label { grid-column: 1 / -1; padding-top: 0; }
            .adm-thead > span:first-child { display: none; }
        }

        @media (max-width: 640px) {
            .adm { padding: 0 12px; }
            .adm-title { font-size: 1.9rem; }
            .adm-tabs { overflow-x: auto; }
            .adm-tab { flex: none; margin-bottom: 0; padding: 10px; font-size: 0.85rem; white-space: nowrap; }
            .adm-card { padding: 16px; }
            .adm-grid-2 { grid-template-columns: 1fr; }
            .adm-trow { grid-template-columns: 1fr; gap: 10px; }
            .adm-thead { display: none; }
            .adm-trow-field { display: flex; gap: 8px; align-items: flex-start; flex-wrap: wrap; }
            .adm-trow-field > .adm-input { flex: 1; min-width: 0; }
            .adm-trow-field > .adm-error { flex-basis: 100%; }
            .adm-lang-code-mobile { display: inline-block; margin-top: 11px; color: var(--adm-gold); }
            .space-editor-grid { grid-template-columns: 1fr; }
            #space-preview-canvas { width: 100%; }
            .adm-editor-head { flex-direction: column; }
            .adm-quill, .html-source { height: 320px; }
            .adm-savebar { bottom: 6px; padding: 12px; }
            .adm-savebar-actions { width: 100%; }
            .adm-savebar-actions .adm-btn-primary { flex: 1; }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            /* ── Onglets principaux (Articles / Textes du site / Suggestions) ── */
            const tabs = document.querySelectorAll('.adm-tab');
            function showTab(name, updateHash = true) {
                tabs.forEach(tab => tab.setAttribute('aria-selected', String(tab.dataset.tab === name)));
                document.querySelectorAll('[data-panel]').forEach(panel => panel.hidden = panel.dataset.panel !== name);
                if (updateHash) history.replaceState(null, '', '#' + name);
            }
            tabs.forEach(tab => tab.addEventListener('click', () => showTab(tab.dataset.tab)));
            if (location.hash === '#textes') showTab('textes', false);
            else if (location.hash === '#suggestions' || location.hash === '#suggestion-editor') showTab('suggestions', false);
            else if (location.hash === '#articles' || location.hash === '#editeur') showTab('articles', false);

            /* ── Onglets de langue de l'article (FR / EN) ── */
            const langButtons = document.querySelectorAll('.adm-lang');
            function showLang(lang) {
                langButtons.forEach(btn => btn.setAttribute('aria-selected', String(btn.dataset.lang === lang)));
                document.querySelectorAll('[data-lang-panel]').forEach(panel => panel.hidden = panel.dataset.langPanel !== lang);
            }
            langButtons.forEach(btn => btn.addEventListener('click', () => showLang(btn.dataset.lang)));

            // Un champ obligatoire vide dans un onglet masqué : on affiche cet onglet pour que le navigateur puisse le signaler
            document.addEventListener('invalid', event => {
                const langPanel = event.target.closest('[data-lang-panel]');
                if (langPanel?.hidden) showLang(langPanel.dataset.langPanel);
                const panel = event.target.closest('[data-panel]');
                if (panel?.hidden) showTab(panel.dataset.panel);
            }, true);

            /* ── Avertissement en cas de modifications non enregistrées ── */
            let submitting = false;
            function markDirty(form) {
                if (form.dataset.dirty) return;
                form.dataset.dirty = '1';
                form.querySelectorAll('.adm-unsaved').forEach(el => el.hidden = false);
            }
            document.querySelectorAll('form[data-track-changes]').forEach(form => {
                form.addEventListener('input', () => markDirty(form));
                form.addEventListener('change', () => markDirty(form));
            });
            window.addEventListener('beforeunload', event => {
                if (!submitting && document.querySelector('form[data-dirty]')) event.preventDefault();
            });

            /* ── Éditeurs de texte (Quill) ── */
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

            function setupEditor(containerSelector, source, placeholder, onUserChange) {
                let htmlMode = false;

                const quill = new Quill(containerSelector, {
                    placeholder,
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

                quill.on('text-change', (delta, old, origin) => {
                    if (origin === 'user') onUserChange();
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
                    if (fromVisual) (enabled ? source : quill).focus();
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

            const articleForm = document.getElementById('editeur');
            const syncEditors = [
                setupEditor('#editor-container', document.getElementById('contenu'), 'Rédigez votre article ici…', () => markDirty(articleForm)),
                setupEditor('#editor-container-en', document.getElementById('contenu_anglais'), 'Write the English version here…', () => markDirty(articleForm))
            ];

            articleForm.addEventListener('submit', function () {
                syncEditors.forEach(sync => sync());
            });
            // Un envoi annulé (ex. confirmation de suppression refusée) ne désactive pas l'avertissement
            document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
                if (!event.defaultPrevented) submitting = true;
            }));
        });
    </script>
@endsection
