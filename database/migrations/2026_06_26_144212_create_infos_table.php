<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('infos', function (Blueprint $table) {
            $table->id();
            // Contact & Réseaux
            $table->string('nom')->nullable();
            $table->string('prenom')->nullable();
            $table->string('mail')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('telephone')->nullable();
            $table->string('adresse')->nullable();
            $table->string('status_pro')->nullable(); // Ex: Étudiante en Droit
            $table->string('ecole')->nullable();      // Ex: Université Paris Cité

            // Section HERO (Accueil)
            $table->string('hero_h1')->nullable();
            $table->string('hero_slogan')->nullable();
            $table->string('hero_tag1')->nullable();
            $table->string('hero_tag2')->nullable();
            $table->string('hero_tag3')->nullable();
            $table->string('hero_tag4')->nullable();

            // Section À PROPOS
            $table->string('about_title')->nullable();
            $table->text('about_text')->nullable(); // text pour les longs paragraphes

            // Piliers / Blocs À propos
            $table->string('about_pillar_title1')->nullable();
            $table->text('about_pillar_content1')->nullable();
            $table->string('about_pillar_title2')->nullable();
            $table->text('about_pillar_content2')->nullable();
            $table->string('about_pillar_title3')->nullable();
            $table->text('about_pillar_content3')->nullable();

            // Titres des autres sections
            $table->string('section_article_title')->nullable();
            $table->string('section_contact_title')->nullable();
            $table->text('section_contact_text')->nullable();

            $table->timestamps();
        });

        // Optionnel : On insère directement la ligne par défaut à la création de la table
        DB::table('infos')->insert([
            'nom' => 'Jamain',
            'prenom' => 'Mélanie',
            'mail' => 'lili.jamain@icloud.com',
            'linkedin' => 'https://linkedin.com/in/melanie-jamain',
            'status_pro' => 'Étudiante en Droit',
            'ecole' => 'Université Paris Cité',
            'hero_h1' => 'Du droit aux étoiles',
            'hero_slogan' => 'Je mets en lumière ce qui est trop peu visible.',
            'hero_tag1' => 'Droit spatial',
            'hero_tag2' => 'Astronomie',
            'hero_tag3' => 'Place des femmes',
            'hero_tag4' => 'Université Paris Cité',
            'about_title' => 'Entre le droit et les étoiles',
            'about_text' => "<p>Étudiante en <strong>Licence de Droit à l'Université Paris Cité</strong>, je suis convaincue que certains sujets méritent d'être éclairés davantage : la place des femmes dans les sciences, les enjeux juridiques de l'exploration spatiale, les silences du droit face aux grandes transformations du monde.</p><p>Passionnée d'astronomie depuis l'enfance, j'ai choisi le droit comme outil pour comprendre et faire bouger les lignes. Ce site est l'espace où ces deux univers se rencontrent.</p>",
            'about_pillar_title1' => 'Droit spatial',
            'about_pillar_content1' => 'Responsabilité, souveraineté, exploitation des ressources extraterrestres — un domaine en pleine construction.',
            'about_pillar_title2' => 'Place des femmes',
            'about_pillar_content2' => 'Dans les sciences, dans le droit, dans la société. Des pionnières trop souvent invisibilisées.',
            'about_pillar_title3' => 'Astronomie',
            'about_pillar_content3' => "Une passion qui nourrit ma curiosité intellectuelle et ma façon d'aborder le droit.",
            'section_article_title' => 'Réflexions & recherches',
            'section_contact_title' => 'Échangeons',
            'section_contact_text' => 'Vous souhaitez me proposer une collaboration, un stage, ou simplement échanger sur le droit spatial ou la place des femmes dans les sciences ? Je serai ravie de vous lire.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infos');
    }
};
