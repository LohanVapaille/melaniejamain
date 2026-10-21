@php
    $lang = session('lang', 'fr');
    $en = $lang === 'en';
    $visualisationArticles = isset($articles)
        ? $articles
        : DB::table('articles')->whereIn('is_published', [1, 2])->orderBy('created_at', 'desc')->get();
    $visualisationData = $visualisationArticles->map(function ($article) use ($en) {
        $title = $en ? ($article->nom_anglais ?: $article->nom) : $article->nom;
        $content = $en ? ($article->contenu_anglais ?: $article->contenu) : $article->contenu;
        $content = preg_replace('/<\s*br\s*\/?>|<\s*\/\s*(p|div|li|h[1-6])\s*>/i', ' ', $content);

        return [
            'id' => $article->id,
            'title' => $title,
            'excerpt' => \Illuminate\Support\Str::limit(
                trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                180
            ),
            'url' => route('articles.show', $article->id),
            'upcoming' => (int) $article->is_published === 2,
            'visualModel' => $article->visual_model ?? 'planet',
            'modelUrl' => !empty($article->model_3d_path) ? asset('storage/' . $article->model_3d_path) : null,
        ];
    })->values()->all();
@endphp
<!DOCTYPE html>
<html lang="{{ session('lang', 'fr') }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="draco-decoder-path" content="{{ asset('draco') }}/" />
    <title>Mélanie Jamain </title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.svg') }}" />
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Inter:wght@300;400;500&display=swap"
        rel="stylesheet" />

    <!-- Inclusion de ton SCSS via Vite -->
    @vite(['resources/css/app.scss'])

    <style>
        /* --- STYLE MENU PC : Soulignage Gold au Hover --- */
        .nav-links a {
            position: relative;
            text-decoration: none;
            color: inherit;
            padding-bottom: 4px;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 100%;
            transform: scaleX(0);
            height: 1px;
            bottom: 0;
            left: 0;
            background-color: var(--gold, #d4af37);
            /* Utilise ta variable ou fallback gold */
            transform-origin: bottom right;
            transition: transform 0.3s ease-out;
        }

        .nav-links a:hover::after {
            transform: scaleX(1);
            transform-origin: bottom left;
        }

        .language-toggle {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-left: 28px;
            white-space: nowrap;
        }

        .language-toggle a {
            position: relative;
            padding-bottom: 4px;
            color: var(--muted);
            text-decoration: none;
            font-size: .72rem;
            letter-spacing: .12em;
        }

        .language-toggle a::after {
            content: '';
            position: absolute;
            width: 100%;
            transform: scaleX(0);
            height: 1px;
            bottom: 0;
            left: 0;
            background: var(--gold);
            transform-origin: right;
            transition: transform .3s ease-out;
        }

        .language-toggle a:hover::after,
        .language-toggle a.active::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .language-toggle a.active {
            color: var(--gold-lt);
        }

        .language-toggle span {
            width: 1px;
            height: 12px;
            background: var(--border);
        }

        /* --- BURGER BUTTON --- */
        .burger-menu {
            display: none;
            flex-direction: column;
            justify-content: space-between;
            width: 24px;
            height: 18px;
            background: transparent;
            border: none;
            cursor: pointer;
            z-index: 100;
            padding: 0;
        }

        .burger-menu span {
            width: 100%;
            height: 2px;
            background-color: currentColor;
            transition: all 0.3s ease;
        }

        /* Animation du burger en croix quand actif */
        .burger-menu.active span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }

        .burger-menu.active span:nth-child(2) {
            opacity: 0;
        }

        .burger-menu.active span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }



        /* --- RESPONSIVE MOBILE --- */
        @media (max-width: 768px) {

            .burger-menu {
                display: flex;

                span {
                    background-color: #f7f4ef;

                }

            }

            .nav-links {
                position: fixed;
                top: 0;
                right: -100%;
                width: 70%;
                height: 100vh;
                background: background: rgba(7, 9, 46, 0.90);

                backdrop-filter: blur(10px);
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 2rem;
                transition: right 0.4s ease;
                margin: 0;
                padding: 0;
                box-shadow: -5px 0 15px rgba(0, 0, 0, 0.5);

                li {
                    color: #c9a84c;
                }



            }

            .nav-links.active {
                right: 0;
            }


        }

        .nav-menu {
            display: flex;
            align-items: center;
        }
    </style>
</head>

<body>

    <canvas id="stars-canvas"></canvas>

    <!-- La navigation commune -->
    <nav>
        <div class="nav-inner">
            <a href="{{ url('/') }}#accueil" class="nav-logo">
                <img class="logo" src="{{ asset('img/logo.svg') }}" alt="Retour à l'accueil">
            </a>

            <div class="mobile-menu-style">
                <div class="nav-menu">
                    <ul class="nav-links" id="nav-links">
                        <li><a href="{{ url('/') }}#apropos">{{ $en ? 'About' : 'À propos' }}</a></li>
                        <li><a href="{{ url('/') }}#articles">Articles</a></li>
                        <li><a href="{{ url('/') }}#contact">Contact</a></li>
                        <button class="visualisation-trigger visualisation-open only-phone" type="button">
                            <span aria-hidden="true">✦</span>
                            {{ $en ? '3D experience' : 'Visualisation 3D' }}
                        </button>
                    </ul>

                    <div class="language-toggle" aria-label="Choose language">
                        <a href="{{ route('language.switch', 'fr') }}"
                            class="{{ session('lang', 'fr') === 'fr' ? 'active' : '' }}">FR</a><span></span>
                        <a href="{{ route('language.switch', 'en') }}"
                            class="{{ session('lang', 'fr') === 'en' ? 'active' : '' }}">EN</a>
                    </div>
                    <button class="visualisation-trigger visualisation-open only-desktop" type="button">
                        <span aria-hidden="true">✦</span>
                        {{ $en ? '3D experience' : 'Visualisation 3D' }}
                    </button>
                </div>

                <!-- Le bouton Burger (visible uniquement sur mobile) -->
                <button class="burger-menu" id="burger-toggle" aria-label="Ouvrir le menu">
                    <span></span>
                    <span></span>
                    <span></span>

                </button>
            </div>
        </div>
    </nav>

    <div class="visualisation-modal" id="visualisation-modal" aria-hidden="true">
        <div class="visualisation-canvas-wrap">
            <div id="visualisation-canvas"
                aria-label="{{ $en ? 'Interactive article space' : 'Espace interactif des articles' }}"></div>
            <div class="visualisation-heading">
                <span class="section-label">{{ $en ? 'Article space' : 'Espace des articles' }}</span>
                <h2>{{ $en ? 'Wander among the articles' : 'Promenez-vous parmi les articles' }}</h2>
                <p>{{ $en ? 'Drag to look around, scroll to move, and click a planet to explore its articles.' : 'Glissez pour regarder, utilisez la molette ou vos doigts pour avancer et cliquez sur une planète pour découvrir son article' }}
                </p>
            </div>
            <button class="visualisation-close" id="close-visualisation" type="button"
                aria-label="{{ $en ? 'Close 3D experience' : 'Fermer la visualisation 3D' }}">×</button>
            <div class="visualisation-help only-desktop" id="visualisation-help">
                {{ $en ? 'Drag · Scroll · Click a planet' : 'Glisser · Molette · Cliquer sur une planète' }}
            </div>
            <aside class="visualisation-preview" id="visualisation-preview" aria-live="polite">
                <span
                    class="preview-label">{{ $en ? 'Come closer to a article' : 'Approchez-vous d’un article' }}</span>
                <h3 id="visualisation-preview-title"></h3>
                <p id="visualisation-preview-text"></p>
                <a id="visualisation-preview-link" href="#">{{ $en ? 'Read more →' : 'Lire plus →' }}</a>
            </aside>
        </div>
    </div>

    <script type="application/json" id="visualisation-articles">@json($visualisationData)</script>


    @yield('content')

    <!-- Le footer commun -->
    <footer>
        <p>© 2026 <span>Mélanie Jamain</span> </p>
        <p><a href="{{ url('/admin/dashboard') }}">{{ $en ? 'Admin panel' : 'Espace administrateur' }}</a></p>
        <p class="footer-separator"></p>
        <p class="credit">{{ $en ? 'This site was created by' : 'Ce site a été réalisé par' }} <a
                href="https://lovaweb.lohanvapaille.fr" target="_blank" rel="noopener noreferrer">Lohan
                Vapaille</a></p>
    </footer>


    <!-- Ton script JS global -->
    @vite(['resources/js/stars.js'])
    @vite(['resources/js/voirplus.js'])
    @vite(['resources/js/visualisation3d.js'])

    <!-- Script pour le comportement du Menu Burger -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const burgerToggle = document.getElementById('burger-toggle');
            const navLinks = document.getElementById('nav-links');
            const links = document.querySelectorAll('.nav-links a');

            // Toggle au clic sur le burger
            burgerToggle.addEventListener('click', () => {
                burgerToggle.classList.toggle('active');
                navLinks.classList.toggle('active');
            });

            // Ferme le menu dès qu'on clique sur un lien d'ancrage
            links.forEach(link => {
                link.addEventListener('click', () => {
                    burgerToggle.classList.remove('active');
                    navLinks.classList.remove('active');
                });
            });
        });
    </script>
</body>

</html>