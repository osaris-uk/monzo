<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One table for every OAuth connection the app holds -- Monzo, and anything
 * else that hands out access and refresh tokens. Copy it into
 * database/migrations and adjust to taste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_integrations', function (Blueprint $table) {
            $table->id();

            // Which service, e.g. "monzo", and which connection to it, so an
            // app can hold more than one (a business and a personal account).
            $table->string('provider', 50);
            $table->string('account', 100)->default('default');

            // Encrypted by the model's casts; text, because ciphertext is long.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->string('token_type', 20)->default('Bearer');
            $table->timestamp('expires_at')->nullable();

            // Who the provider says the token belongs to, and which client issued it.
            $table->string('provider_user_id')->nullable();
            $table->string('client_id')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'account']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_integrations');
    }
};
