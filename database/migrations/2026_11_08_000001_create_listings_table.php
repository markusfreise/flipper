<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            // photos | identifying | identified | researching | questions | writing | ready | failed
            $t->string('status', 32)->default('photos');
            // Zustand vor einem Fehler, damit "Nochmal versuchen" dort weitermacht.
            $t->string('status_before_failure', 32)->nullable();
            $t->jsonb('product')->nullable();
            $t->jsonb('photo_check')->nullable();
            $t->jsonb('research')->nullable();
            $t->jsonb('answers')->nullable();
            $t->jsonb('pricing')->nullable();
            $t->jsonb('text')->nullable();
            $t->string('zip_path')->nullable();
            $t->unsignedSmallInteger('text_revisions')->default(0);
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
