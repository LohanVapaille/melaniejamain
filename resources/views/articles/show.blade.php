@extends('layouts.app')

@section('title', $seo['title'])

@section('content')



    <!-- 2. Tes styles personnalisés et adaptés au thème sombre -->
    <style>
        .article-container {
            max-width: 1200px;
            padding: 20px;
            padding: 0 20px;
            color: #e2e8f0;
            /* Blanc cassé pour le confort de lecture */
        }

        .article-unique {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            margin-top: 100px;
        }

        .article-header {
            margin-bottom: 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 30px;
        }

        .article-meta {
            font-size: 0.9rem;
            color: var(--gold, #d4af37);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 15px;
            display: block;
        }

        .article-main-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3rem;
            line-height: 1.2;
            color: #ffffff;
            margin: 0;
        }

        /* 3. Surcharges de la classe .ql-editor pour matcher ton thème */
        .ql-editor {
            padding: 0 !important;
            /* Reset du padding par défaut de Quill */
            font-size: 1.1rem;
            line-height: 1.8;
            font-family: inherit;
        }

        /* Retraits de paragraphe ajoutés depuis l'éditeur (boutons ⇤ / ⇥) */
        .ql-editor .ql-indent-1 { padding-left: 3em; }
        .ql-editor .ql-indent-2 { padding-left: 6em; }
        .ql-editor .ql-indent-3 { padding-left: 9em; }
        .ql-editor .ql-indent-4 { padding-left: 12em; }
        .ql-editor .ql-indent-5 { padding-left: 15em; }
        .ql-editor .ql-indent-6 { padding-left: 18em; }
        .ql-editor .ql-indent-7 { padding-left: 21em; }
        .ql-editor .ql-indent-8 { padding-left: 24em; }

        /* Style des paragraphes */
        .ql-editor p {
            margin-bottom: 24px;
        }

        /* Titres générés à l'intérieur du texte (H2, H3) */
        .ql-editor h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            color: #ffffff;
            margin-top: 40px;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .ql-editor h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.6rem;
            color: #ffffff;
            margin-top: 30px;
            margin-bottom: 12px;
            font-weight: 500;
        }

        /* Liens hypertexte (Bordure dorée fine, transition propre) */
        .ql-editor a {
            color: var(--gold, #d4af37) !important;
            text-decoration: none;
            border-bottom: 1px dashed rgba(214, 175, 55, 0.5);
            transition: border-color 0.2s;
        }

        .ql-editor a:hover {
            border-bottom-style: solid;
            border-bottom-color: var(--gold, #d4af37);
        }

        /* Mises en gras et italique */
        .ql-editor strong {
            color: #ffffff;
            font-weight: 700;
        }

        /* Bloc de citation (Blockquote) */
        .ql-editor blockquote {
            border-left: 3px solid var(--gold, #d4af37) !important;
            padding-left: 20px !important;
            margin: 30px 0 !important;
            font-style: italic;
            color: #a0aec0;
            background: rgba(135, 206, 250, 0.03);
            padding: 15px 20px;
            border-radius: 0 4px 4px 0;
        }

        /* Listes à puces et numérotées */
        .ql-editor ul,
        .ql-editor ol {
            padding-left: 25px !important;
            margin-bottom: 24px;
        }

        .ql-editor li {
            margin-bottom: 8px;
        }

        /* Bouton Retour */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #a0aec0;
            text-decoration: none;
            font-size: 0.95rem;
            margin-bottom: 40px;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--gold, #d4af37);
        }

        .related-articles {
            margin-top: 60px;
        }
    </style>
    <section class="article-unique">

        <!-- Bouton Retour -->
        <a href="{{ \App\Support\Seo::homeUrl($lang) }}#articles"
            style="color: #a0aec0; text-decoration: none; font-size: 0.9rem; display: inline-block; mb-4: 20px;">
            {{ $lang === 'en' ? '← Back to articles' : '← Retour aux articles' }}
        </a>

        <article lang="{{ $seo['contentLang'] }}">
            <!-- Titre de l'article -->
            <h1 class="hero-title" style="font-size: 2.5rem; margin-top: 20px; line-height: 1.2;">
                {{ \App\Support\Seo::articleTitle($article, $lang) }}
            </h1>

            <p class="article-date" style="color: #718096; font-style: italic; margin: 15px 0 40px 0; width: auto;">
                @if((int) $article->is_published === 2)
                    {{ $lang === 'en' ? 'Coming soon' : 'À venir' }}
                @else
                    {{ $lang === 'en' ? 'Published on' : 'Publié le' }}
                    <time datetime="{{ $seo['published'] }}">{{ \Carbon\Carbon::parse($article->created_at)->format($lang === 'en' ? 'm/d/Y' : 'd/m/Y') }}</time>
                    {{ $lang === 'en' ? 'by' : 'par' }} <span>{{ $seo['author'] }}</span>
                @endif
            </p>

            <!-- Corps de l'article enveloppé dans la classe Quill -->
            <div class="ql-editor">
                {!! \App\Support\Seo::articleContent($article, $lang) !!}
            </div>
        </article>

        @if($relatedArticles->isNotEmpty())
            <div class="related-articles">
                <h2 class="section-title">{{ $lang === 'en' ? 'Keep reading' : 'À lire aussi' }}</h2>
                <div class="articles-list">
                    @foreach($relatedArticles as $related)
                        <a href="{{ \App\Support\Seo::articleUrl($related, $lang) }}" class="article-item"
                            style="text-decoration: none; color: inherit; display: flex;">
                            <span class="article-date">{{ \Carbon\Carbon::parse($related->created_at)->locale($lang)->isoFormat('DD MMM YYYY') }}</span>
                            <div>
                                <h3 class="article-title">{{ \App\Support\Seo::articleTitle($related, $lang) }}</h3>
                                <p class="article-excerpt">{{ Str::limit(\App\Support\Seo::plainText(\App\Support\Seo::articleContent($related, $lang)), 150, '...') }}</p>
                                <span class="article-tag">{{ $lang === 'en' ? 'Read more' : 'Lire la suite' }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Un article « À venir » n'a pas encore été lu : on garde le texte général --}}
        @include('partials.suggestion', ['afterArticle' => (int) $article->is_published === 1])

    </section>
@endsection