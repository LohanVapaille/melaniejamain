<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function article(array $fields = []): int
    {
        return DB::table('articles')->insertGetId($fields + [
            'nom' => "Qui possède l'espace ?",
            'slug' => 'qui-possede-l-espace',
            'contenu' => '<p>Depuis 1967, le droit international encadre l’espace.</p><p>Deuxième paragraphe.</p>',
            'is_published' => 1,
            'created_at' => '2026-06-26 12:00:00',
            'updated_at' => '2026-10-28 12:00:00',
        ]);
    }

    private function translated(): array
    {
        return ['nom_anglais' => 'Who owns space?', 'slug_anglais' => 'who-owns-space', 'contenu_anglais' => '<p>Since 1967.</p>'];
    }

    private function translateHome(): void
    {
        DB::table('infos')->where('langue', 'en')->update([
            'hero_h1' => 'From law to the stars',
            'hero_slogan' => 'I shed light on what is rarely seen.',
            'about_text' => '<p>Law student.</p>',
        ]);
    }

    private function save(array $fields): object
    {
        $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.articles.store'), $fields + ['contenu' => '<p>Texte.</p>', 'visual_model' => 'planet', 'is_published' => 1])
            ->assertRedirect();

        return DB::table('articles')->orderBy('id', 'desc')->first();
    }

    public function test_article_page_builds_its_seo_from_title_and_content(): void
    {
        $this->article();

        $this->get('/articles/qui-possede-l-espace')
            ->assertOk()
            ->assertSee("<title>Qui possède l&#039;espace ? — Mélanie Jamain</title>", false)
            ->assertSee('content="Depuis 1967, le droit international encadre l’espace. Deuxième paragraphe."', false)
            ->assertSee('rel="canonical" href="' . url('/articles/qui-possede-l-espace') . '"', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"headline":"Qui possède l\'espace ?"', false)
            ->assertSee('property="og:type" content="article"', false);
    }

    public function test_old_id_urls_and_other_language_slugs_redirect_permanently(): void
    {
        $id = $this->article($this->translated());

        $this->get("/articles/$id")->assertStatus(301)->assertRedirect(url('/articles/qui-possede-l-espace'));
        $this->get("/en/articles/$id")->assertStatus(301)->assertRedirect(url('/en/articles/who-owns-space'));
        $this->get('/articles/who-owns-space')->assertStatus(301)->assertRedirect(url('/articles/qui-possede-l-espace'));
        $this->get('/articles/inconnu')->assertNotFound();
    }

    public function test_new_article_gets_a_clean_and_unique_slug(): void
    {
        $this->assertSame('a-qui-appartient-la-lune', $this->save(['nom' => 'À qui appartient la Lune ?'])->slug);
        $this->assertSame('a-qui-appartient-la-lune-2', $this->save(['nom' => 'À qui appartient la Lune ?'])->slug);
        $this->assertSame('l-espace', $this->save(['nom' => "L'espace"])->slug);

        $this->get('/articles/a-qui-appartient-la-lune-2')->assertOk();
    }

    public function test_slug_follows_the_title_until_publication_then_stays_fixed(): void
    {
        $draft = $this->save(['nom' => 'Premier titre', 'is_published' => 0]);
        $renamed = $this->save(['id' => $draft->id, 'nom' => 'Second titre', 'is_published' => 1]);
        $this->assertSame('second-titre', $renamed->slug);

        $published = $this->save(['id' => $draft->id, 'nom' => 'Troisième titre', 'nom_anglais' => 'English title', 'is_published' => 1]);
        $this->assertSame('second-titre', $published->slug);
        $this->assertSame('english-title', $published->slug_anglais);

        $this->assertSame('english-title', $this->save(['id' => $draft->id, 'nom' => 'Troisième titre', 'nom_anglais' => 'Other title', 'is_published' => 1])->slug_anglais);
    }

    public function test_drafts_are_hidden_and_upcoming_articles_are_not_indexed(): void
    {
        $this->article(['is_published' => 0]);
        $this->get('/articles/qui-possede-l-espace')->assertNotFound();

        $this->article(['nom' => 'Bientot', 'slug' => 'bientot', 'is_published' => 2]);
        $this->get('/articles/bientot')
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);
    }

    public function test_article_without_slug_stays_reachable_by_id(): void
    {
        $id = $this->article(['slug' => null]);

        $this->get('/')->assertOk()->assertSee(url("/articles/$id"), false);
        $this->get("/articles/$id")->assertOk();
    }

    public function test_translated_article_declares_both_languages(): void
    {
        $this->article($this->translated());

        $this->get('/en/articles/who-owns-space')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('rel="canonical" href="' . url('/en/articles/who-owns-space') . '"', false)
            ->assertSee('hreflang="fr" href="' . url('/articles/qui-possede-l-espace') . '"', false)
            ->assertSee('hreflang="x-default"', false);
    }

    public function test_untranslated_article_points_google_to_the_french_version(): void
    {
        $this->article();

        $this->get('/en/articles/qui-possede-l-espace')
            ->assertOk()
            ->assertSee('rel="canonical" href="' . url('/articles/qui-possede-l-espace') . '"', false)
            ->assertDontSee('rel="alternate" hreflang', false);
    }

    public function test_home_has_person_data_and_language_comes_from_the_url(): void
    {
        $this->translateHome();

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertSee('<title>Droit spatial, astronomie, place des femmes | Mélanie Jamain</title>', false)
            ->assertSee('"@type":"Person"', false);

        $this->get('/en')->assertOk()->assertSee('<html lang="en">', false)->assertSessionHas('lang', 'en');
        $this->get('/')->assertSee('<html lang="fr">', false)->assertSessionHas('lang', 'fr');
    }

    public function test_sitemap_lists_only_indexable_pages(): void
    {
        $this->article($this->translated());
        $this->article(['nom' => 'Brouillon', 'slug' => 'brouillon', 'is_published' => 0]);
        $this->article(['nom' => 'Bientot', 'slug' => 'bientot', 'is_published' => 2]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertNotFalse(simplexml_load_string($response->getContent()));

        $response->assertSee(url('/articles/qui-possede-l-espace'))
            ->assertSee(url('/en/articles/who-owns-space'))
            ->assertDontSee('/articles/brouillon')
            ->assertDontSee('/articles/bientot')
            // Accueil anglais pas encore traduit
            ->assertDontSee('<loc>' . url('/en') . '</loc>', false);
    }

    public function test_robots_points_to_the_sitemap_and_admin_is_not_indexed(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: ' . url('/sitemap.xml'), false);
        $this->get('/admin/login')->assertOk()->assertSee('name="robots" content="noindex,nofollow"', false);
    }
}
