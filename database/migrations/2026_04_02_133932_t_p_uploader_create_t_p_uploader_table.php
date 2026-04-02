<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('t_p_uploaders')) {
            Schema::create('t_p_uploaders', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('status', 60)->default('published');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('t_p_uploaders_translations')) {
            Schema::create('t_p_uploaders_translations', function (Blueprint $table) {
                $table->string('lang_code');
                $table->foreignId('t_p_uploaders_id');
                $table->string('name', 255)->nullable();

                $table->primary(['lang_code', 't_p_uploaders_id'], 't_p_uploaders_translations_primary');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_p_uploaders');
        Schema::dropIfExists('t_p_uploaders_translations');
    }
};
