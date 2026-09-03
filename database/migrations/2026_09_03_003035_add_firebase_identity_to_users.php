<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firebase as the identity provider.
 *
 * The local user remains the authority on what somebody may do — every policy,
 * scope and membership is keyed on it. Firebase only answers who they are.
 *
 * `password` stays nullable-compatible: an account created through Google or
 * Apple has no password here, and the admin panel still signs in with one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Firebase's uid, stable for the life of the account and unchanged
            // when somebody edits their email. Matching on email instead would
            // hand over an account to anybody who could claim the address.
            $table->string('firebase_uid', 128)->nullable()->unique()->after('email');

            // How they last signed in — google.com, apple.com, password.
            // Kept for support: "I can't get in" is usually "I signed up with
            // Google and I'm typing a password".
            $table->string('auth_provider', 32)->nullable()->after('firebase_uid');

            $table->string('avatar_url', 500)->nullable()->after('avatar_media_id');
        });

        // An account that has only ever signed in through Firebase has no
        // password to check.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firebase_uid', 'auth_provider', 'avatar_url']);
        });
    }
};
