<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $blueprint) {
            // Par défaut, un article créé est publié (true), ou non (false) selon ton choix.
            // Mettons true par défaut pour ne pas masquer tes articles existants.
            $blueprint->boolean('is_published')->default(true)->after('contenu');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $blueprint) {
            $blueprint->dropColumn('is_published');
        });
    }
};