<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_photos', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('listing_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            // overview | detail  (detail = Mangel/Nahaufnahme, wird nicht freigestellt)
            $t->string('kind', 16)->default('overview');
            $t->string('original_path');
            $t->string('upload_path')->nullable();
            $t->string('cutout_path')->nullable();
            $t->string('final_path')->nullable();
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            // uploaded | cutting | cut | cutout_failed | composed
            $t->string('status', 24)->default('uploaded');
            $t->boolean('is_cover')->default(false);
            $t->timestamps();
            $t->unique(['listing_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_photos');
    }
};
