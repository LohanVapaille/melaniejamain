<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('articles')->insert([
            [
                'nom' => 'La place des femmes dans le droit spatial : une invisibilisation structurelle ?',
                'contenu' => 'De Valentina Terechkova aux juristes de l\'ESA, retour sur les pionnières que l\'histoire spatiale a trop souvent oubliées — et sur ce que le droit peut faire pour y remédier.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Qui possède la Lune ? L\'exploitation des ressources extraterrestres face au droit international',
                'contenu' => 'Le Traité de l\'Espace de 1967 interdit-il vraiment l\'appropriation des ressources lunaires ? Une question qui divise les États et agite les juristes du monde entier.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Cecilia Payne-Gaposchkin : la femme qui a découvert la composition du Soleil, et qu\'on a effacée',
                'contenu' => 'En 1925, elle prouve que le Soleil est composé d\'hydrogène. Son directeur de thèse lui demande de retirer cette conclusion. Retour sur l\'une des injustices les plus flagrantes de l\'histoire des sciences.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}