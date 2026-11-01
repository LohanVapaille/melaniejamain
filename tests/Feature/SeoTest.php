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
            'contenu' => '<p>Depuis 1967, le droit international encadre l’espace.</p><p>Deuxième paragraphe.</p>',
            'is_published' => 1,
            'created_at' => '2026-06-26 12:00:00',
            'updated_at' => '2026-10-28 12:00:00',
        ]);
    }

    private function translateHome(): void
    {
        DB::table('infos')->where('langue', 'en')->update([
            'hero_h1' => 'From law to the stars',
            'hero_slogan' => 'I shed light on what is rarely seen.',
            'about_text' => '<p>Law student.</p>',
        ]);
    }

    public function test_article_page_builds_its_seo_from_title_and_content(): void
    {
        $id = $this->article();

        $this->get("/articles/$id-qui-possede-l-espace")
            ->assertOk()
            ->assertSee("<title>Qui possède l&#039;espace ? — Mélanie Jamain</title>", false)
            ->assertSee('content="Depuis 1967, le droit international encadre l’espace. Deuxième paragraphe."', false)
            ->assertSee('rel="canonical" href="' . url("/articles/$id-qui-possede-l-espace") . '"', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"headline":"Qui possède l\'espace ?"', false)
            ->assertSee('property="og:type" content="article"', false);
    }

    public function test_old_and_outdated_article_urls_redirect_permanently(): void
    {
        $id = $this->article();
        $target = url("/articles/$id-qui-possede-l-espace");

        $this->get("/articles/$id")->assertStatus(301)->assertRedirect($target);
        $this->get("/articles/$id-ancien-titre")->assertStatus(301)->assertRedirect($target);
    }

    public function test_drafts_are_hidden_and_upcoming_articles_are_not_indexed(): void
    {
        $this->get('/articles/' . $this->article(['is_published' => 0]))->assertNotFound();

        $upcoming = $this->article(['nom' => 'Bientot', 'is_published' => 2]);
        $this->get("/articles/$upcoming-bientot")
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);
    }

    public function test_translated_article_declares_both_languages(): void
    {
        $id = $this->article(['nom_anglais' => 'Who owns space?', 'contenu_anglais' => '<p>Since 1967.</p>']);

        $this->get("/en/articles/$id-who-owns-space")
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('rel="canonical" href="' . url("/en/articles/$id-who-owns-space") . '"', false)
            ->assertSee('hreflang="fr" href="' . url("/articles/$id-qui-possede-l-espace") . '"', false)
            ->assertSee('hreflang="x-default"', false);
    }

    public function test_untranslated_article_points_google_to_the_french_version(): void
    {
        $id = $this->article();

        $this->get("/en/articles/$id-qui-possede-l-espace")
            ->assertOk()
            ->assertSee('rel="canonical" href="' . url("/articles/$id-qui-possede-l-espace") . '"', false)
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
        $published = $this->article(['nom_anglais' => 'Who owns space?', 'contenu_anglais' => '<p>Since 1967.</p>']);
        $draft = $this->article(['nom' => 'Brouillon', 'is_published' => 0]);
        $upcoming = $this->article(['nom' => 'Bientot', 'is_published' => 2]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertNotFalse(simplexml_load_string($response->getContent()));

        $response->assertSee(url("/articles/$published-qui-possede-l-espace"))
            ->assertSee(url("/en/articles/$published-who-owns-space"))
            ->assertDontSee("/articles/$draft-")
            ->assertDontSee("/articles/$upcoming-")
            // Accueil anglais pas encore traduit
            ->assertDontSee('<loc>' . url('/en') . '</loc>', false);
    }

    public function test_robots_points_to_the_sitemap_and_admin_is_not_indexed(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: ' . url('/sitemap.xml'), false);
        $this->get('/admin/login')->assertOk()->assertSee('name="robots" content="noindex,nofollow"', false);
    }
}
