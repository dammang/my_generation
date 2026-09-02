<?php

use App\Enums\DevicePlatform;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** FCM registrations. Notifications ship to the database channel in MVP; this
 *  table means turning on push in v2 needs no schema change. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->enum('platform', DevicePlatform::values());
            $table->string('app_version', 30)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_device_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
