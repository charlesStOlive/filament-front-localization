<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les textes du front modifiés depuis le B.O. : une ligne par clé, une valeur par langue. Les fichiers de langue restent
 * la référence ; une langue absente de `text` garde la valeur du fichier. Le journal garde chaque changement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('front_text_overrides', function (Blueprint $table): void {
            $table->id();
            // `*` : un texte de l'application (sans namespace, comme le traducteur de Laravel).
            $table->string('namespace', 100)->default('*');
            $table->string('group', 150);
            $table->string('key', 191);
            $table->json('text');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['namespace', 'group', 'key']);
        });

        Schema::create('front_text_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('namespace', 100)->default('*');
            $table->string('group', 150);
            $table->string('key', 191);
            $table->string('locale', 10);
            // null : la valeur du fichier (pas de surcharge).
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['namespace', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_text_changes');
        Schema::dropIfExists('front_text_overrides');
    }
};
