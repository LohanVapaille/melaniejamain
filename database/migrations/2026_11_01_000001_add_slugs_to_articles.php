<?php

use App\Support\Seo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('slug')->nullable();
            $table->string('slug_anglais')->nullable();
        });

        foreach (DB::table('articles')->orderBy('id')->get() as $article) {
            DB::table('articles')->where('id', $article->id)->update(Seo::articleSlugs($article->nom, $article->nom_anglais, null, $article->id));
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->unique('slug');
            $table->unique('slug_anglais');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropUnique(['slug_anglais']);
            $table->dropColumn(['slug', 'slug_anglais']);
        });
    }
};
