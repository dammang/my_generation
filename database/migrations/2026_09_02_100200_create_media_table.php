<?php

use App\Enums\MediaCollection;
use App\Enums\MediaStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic file records. Binaries live on an S3-compatible disk (Cloudflare
 * R2 in production); MySQL stores only the pointer, the provenance and the
 * checksum. Private files are never served directly — see MediaPolicy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->nullableMorphs('mediable');   // owner: person, story, source, tribe…
            $table->enum('collection', MediaCollection::values())
                ->default(MediaCollection::Gallery->value);

            $table->string('disk', 30);
            $table->string('path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);   // dedupe + tamper detection on archival scans

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('conversions')->nullable();   // {thumb: path, medium: path}

            $table->boolean('is_private')->default(true);
            $table->string('caption', 500)->nullable();
            $table->date('taken_at')->nullable();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', MediaStatus::values())->default(MediaStatus::Processing->value);

            $table->softDeletesWithToken();
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'collection'], 'idx_media_morph');
            $table->index('checksum_sha256', 'idx_media_checksum');
            $table->index('uploaded_by', 'idx_media_uploader');
            $table->index('status', 'idx_media_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
