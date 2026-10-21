<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('infos', function (Blueprint $table) {
            $table->string('langue', 2)->default('fr');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('nom_anglais')->nullable();
            $table->longText('contenu_anglais')->nullable();
        });

        $frenchInfo = DB::table('infos')->where('id', 1)->first();
        if ($frenchInfo && !DB::table('infos')->where('langue', 'en')->exists()) {
            $englishInfo = (array) $frenchInfo;
            unset($englishInfo['id']);
            $englishInfo['langue'] = 'en';
            foreach ($englishInfo as $key => $value) {
                if (!in_array($key, ['langue', 'created_at', 'updated_at', 'nom', 'prenom', 'mail', 'linkedin', 'telephone', 'adresse'], true)) {
                    $englishInfo[$key] = null;
                }
            }
            $englishInfo['created_at'] = now();
            $englishInfo['updated_at'] = now();
            DB::table('infos')->insert($englishInfo);
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['nom_anglais', 'contenu_anglais']);
        });
        Schema::table('infos', function (Blueprint $table) {
            $table->dropColumn('langue');
        });
    }
};
