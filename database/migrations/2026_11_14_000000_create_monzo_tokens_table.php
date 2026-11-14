<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monzo_tokens', function (Blueprint $table) {
            $table->id();

            // One row per connected account; "default" unless you configure otherwise.
            $table->string('key')->unique();

            // The token pair, encrypted with APP_KEY.
            $table->text('payload');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monzo_tokens');
    }
};
