<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Construit automatiquement les balises SEO à partir du contenu du site :
 * rien à saisir dans l'administration, un nouvel article est optimisé dès sa publication.
 */
class Seo
{
    public const LANGUAGES = ['fr', 'en'];

    private const DESCRIPTION_LENGTH = 160;
    private const TITLE_LENGTH = 65;
    private const SLUG_LENGTH = 80;

    public static function homeUrl(string $lang): string
    {
        return route("$lang.home");
    }

    public static function articleUrl(object $article, string $lang): string
    {
        return route("$lang.articles.show", self::articleKey($article, $lang));
    }

    /**
     * Segment d'URL d'un article, ex. « qui-possede-la-lune ».
     * L'identifiant ne sert que si l'article n'a pas de slug (insertion directe en base, seeder).
     */
    public static function articleKey(object $article, string $lang): string
    {
        $slug = $lang === 'en' && !empty($article->slug_anglais) ? $article->slug_anglais : ($article->slug ?? null);

        return $slug ?: (string) $article->id;
    }

    /**
     * Slugs à enregistrer pour un article. Ils suivent le titre jusqu'à la publication,
     * puis ne bougent plus : une URL publiée doit rester valable même si le titre change.
     */
    public static function articleSlugs(string $title, ?string $englishTitle, ?object $current, ?int $id = null): array
    {
        $id ??= $current?->id;
        $frozen = $current && (int) $current->is_published === 1;

        return [
            'slug' => $frozen && $current->slug ? $current->slug : self::uniqueSlug($title, $id),
            'slug_anglais' => match (true) {
                blank($englishTitle) => null,
                $frozen && (bool) $current->slug_anglais => $current->slug_anglais,
                default => self::uniqueSlug($englishTitle, $id),
            },
        ];
    }

    private static function uniqueSlug(string $title, ?int $ignoreId): string
    {
        // Str::slug supprime les apostrophes : « l'espace » donnerait « lespace »
        $base = Str::slug(str_replace(["'", '’'], ' ', $title));

        if (strlen($base) > self::SLUG_LENGTH) {
            $base = preg_replace('/-[^-]*$/', '', substr($base, 0, self::SLUG_LENGTH));
        }

        $base = $base ?: 'article';
        $slug = $base;

        for ($suffix = 2; self::slugIsTaken($slug, $ignoreId); $suffix++) {
            $slug = "$base-$suffix";
        }

        return $slug;
    }

    private static function slugIsTaken(string $slug, ?int $ignoreId): bool
    {
        return DB::table('articles')
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->where(fn($query) => $query->where('slug', $slug)->orWhere('slug_anglais', $slug))
            ->exists();
    }

    public static function articleTitle(object $article, string $lang): string
    {
        return $lang === 'en' && $article->nom_anglais ? $article->nom_anglais : $article->nom;
    }

    public static function articleContent(object $article, string $lang): string
    {
        return $lang === 'en' && $article->contenu_anglais ? $article->contenu_anglais : $article->contenu;
    }

    public static function plainText(?string $html): string
    {
        // Le premier décodage couvre les contenus enregistrés avec leurs balises échappées (&lt;p&gt;)
        $html = html_entity_decode($html ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Preserve sentence boundaries between paragraphs before removing HTML tags.
        $html = preg_replace('/<\s*br\s*\/?>|<\s*\/\s*(p|div|li|h[1-6])\s*>/i', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{A0}", ' ', $text)));
    }

    public static function articleIsTranslated(object $article): bool
    {
        return filled($article->nom_anglais) && filled(self::plainText($article->contenu_anglais));
    }

    public static function homeIsTranslated(): bool
    {
        $english = DB::table('infos')->where('langue', 'en')->first();

        return $english && filled($english->hero_h1) && filled($english->hero_slogan) && filled(self::plainText($english->about_text));
    }

    public static function forHome(object $info, string $lang): array
    {
        $fallback = DB::table('infos')->where('langue', 'fr')->first();
        $text = fn(string $key) => ($info->$key ?? null) ?: ($fallback->$key ?? null);
        $name = self::authorName($info);

        // Le 4e tag est l'université : elle figure déjà dans la description
        $topics = array_values(array_filter([$text('hero_tag1'), $text('hero_tag2'), $text('hero_tag3')]));
        $lowerTopics = implode(', ', array_map(fn(string $topic) => mb_strtolower(mb_substr($topic, 0, 1)) . mb_substr($topic, 1), $topics));

        $title = $topics ? mb_strtoupper(mb_substr($lowerTopics, 0, 1)) . mb_substr($lowerTopics, 1) . " | $name" : $name;
        $description = Str::limit(trim(implode(' ', array_filter([
            self::sentence(self::plainText($text('hero_slogan'))),
            ($lang === 'en' ? 'Articles by ' : 'Articles de ') . implode(', ', array_filter([$name, $text('status_pro')]))
                . ($topics ? " : $lowerTopics." : '.'),
        ]))), self::DESCRIPTION_LENGTH, '…', preserveWords: true);

        $languages = ['fr' => self::homeUrl('fr'), 'en' => self::homeUrl('en')];
        $translated = self::homeIsTranslated();
        $personId = self::homeUrl('fr') . '#person';

        return [
            'title' => $title,
            'socialTitle' => $title,
            'description' => $description,
            'type' => 'website',
            'robots' => self::indexable(),
            'languages' => $languages,
            'alternates' => $translated ? $languages : [],
            'canonical' => $lang === 'en' && !$translated ? $languages['fr'] : $languages[$lang],
            'contentLang' => $lang === 'en' && !$translated ? 'fr' : $lang,
            'author' => $name,
            'image' => asset('img/og-default.png'),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebSite',
                        '@id' => self::homeUrl('fr') . '#website',
                        'url' => self::homeUrl('fr'),
                        'name' => $name,
                        'description' => $description,
                        'inLanguage' => $translated ? self::LANGUAGES : 'fr',
                        'publisher' => ['@id' => $personId],
                    ],
                    array_filter([
                        '@type' => 'Person',
                        '@id' => $personId,
                        'name' => $name,
                        'url' => self::homeUrl('fr'),
                        'image' => asset('img/og-default.png'),
                        'jobTitle' => $text('status_pro'),
                        'affiliation' => $text('ecole') ? ['@type' => 'CollegeOrUniversity', 'name' => $text('ecole')] : null,
                        'sameAs' => $info->linkedin ? [$info->linkedin] : null,
                        'knowsAbout' => $topics ?: null,
                    ]),
                ],
            ],
        ];
    }

    public static function forArticle(object $article, object $info, string $lang): array
    {
        $translated = self::articleIsTranslated($article);
        // Sans traduction, la page anglaise affiche le texte français : c'est la version française qui compte pour Google
        $contentLang = $lang === 'en' && $translated ? 'en' : 'fr';
        $headline = self::articleTitle($article, $contentLang);
        $text = self::plainText(self::articleContent($article, $contentLang));
        $description = Str::limit($text, self::DESCRIPTION_LENGTH, '…', preserveWords: true);
        $name = self::authorName($info);
        $suffixed = "$headline — $name";

        $languages = ['fr' => self::articleUrl($article, 'fr'), 'en' => self::articleUrl($article, 'en')];
        $canonical = $languages[$contentLang];
        $published = Carbon::parse($article->created_at)->toAtomString();
        $modified = Carbon::parse($article->updated_at ?? $article->created_at)->toAtomString();
        $author = array_filter([
            '@type' => 'Person',
            '@id' => self::homeUrl('fr') . '#person',
            'name' => $name,
            'url' => self::homeUrl('fr'),
            'sameAs' => $info->linkedin ? [$info->linkedin] : null,
        ]);

        return [
            'title' => mb_strlen($suffixed) <= self::TITLE_LENGTH ? $suffixed : $headline,
            'socialTitle' => $headline,
            'description' => $description,
            'type' => 'article',
            // Un article « À venir » reste visible des visiteurs mais n'entre dans Google qu'une fois publié
            'robots' => (int) $article->is_published === 1 ? self::indexable() : 'noindex,follow',
            'languages' => $languages,
            'alternates' => $translated ? $languages : [],
            'canonical' => $canonical,
            'contentLang' => $contentLang,
            'author' => $name,
            'image' => asset('img/og-default.png'),
            'published' => $published,
            'modified' => $modified,
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'BlogPosting',
                        '@id' => $canonical . '#article',
                        'mainEntityOfPage' => $canonical,
                        'headline' => $headline,
                        'description' => $description,
                        'inLanguage' => $contentLang,
                        'datePublished' => $published,
                        'dateModified' => $modified,
                        'image' => [asset('img/og-default.png')],
                        'wordCount' => str_word_count(Str::ascii($text)),
                        'author' => $author,
                        'publisher' => $author,
                    ],
                    [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => $name, 'item' => self::homeUrl($contentLang)],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => $headline, 'item' => $canonical],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Pages à déclarer dans le sitemap : uniquement celles que Google doit indexer.
     */
    public static function sitemapEntries(): array
    {
        $articles = DB::table('articles')->where('is_published', 1)->orderBy('created_at', 'desc')->get();
        $homeTranslated = self::homeIsTranslated();
        $homeLanguages = ['fr' => self::homeUrl('fr'), 'en' => self::homeUrl('en')];
        $homeUpdated = max(array_filter([$articles->max('updated_at'), DB::table('infos')->max('updated_at')]) ?: [null]);

        $entries = [];
        foreach (self::LANGUAGES as $lang) {
            if ($lang === 'fr' || $homeTranslated) {
                $entries[] = [
                    'url' => $homeLanguages[$lang],
                    'lastmod' => $homeUpdated ? Carbon::parse($homeUpdated)->toAtomString() : null,
                    'alternates' => $homeTranslated ? $homeLanguages : [],
                ];
            }
        }

        foreach ($articles as $article) {
            $translated = self::articleIsTranslated($article);
            $languages = ['fr' => self::articleUrl($article, 'fr'), 'en' => self::articleUrl($article, 'en')];

            foreach (self::LANGUAGES as $lang) {
                if ($lang === 'fr' || $translated) {
                    $entries[] = [
                        'url' => $languages[$lang],
                        'lastmod' => Carbon::parse($article->updated_at ?? $article->created_at)->toAtomString(),
                        'alternates' => $translated ? $languages : [],
                    ];
                }
            }
        }

        return $entries;
    }

    private static function authorName(object $info): string
    {
        return trim(($info->prenom ?? '') . ' ' . ($info->nom ?? '')) ?: config('app.name');
    }

    private static function indexable(): string
    {
        return 'index,follow,max-image-preview:large,max-snippet:-1';
    }

    private static function sentence(string $text): ?string
    {
        return $text === '' ? null : (preg_match('/[.!?…]$/u', $text) ? $text : "$text.");
    }
}
