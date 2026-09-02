<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();
            $table->string('name', 150);
            $table->string('email', 191);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Linked only by an approved profile claim — a user is not a person.
            // FK added in the deferred pass once `people` exists.
            $table->unsignedBigInteger('person_id')->nullable();

            $table->string('locale', 10)->default('en');
            $table->unsignedBigInteger('avatar_media_id')->nullable();
            $table->boolean('is_super_admin')->default(false);
            $table->enum('status', UserStatus::values())->default(UserStatus::Active->value);
            $table->timestamp('last_active_at')->nullable();

            $table->rememberToken();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->unique(['email', 'deleted_token'], 'uq_users_email');
            $table->unique(['person_id', 'deleted_token'], 'uq_users_person');
            $table->index('status', 'idx_users_status');
            $table->index('last_active_at', 'idx_users_last_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
