<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MediaCollection;
use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Media> */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = Str::random(20).'.jpg';

        return [
            'collection' => MediaCollection::Gallery,
            'disk' => config('genealogy.media.private_disk'),
            'path' => 'demo/'.date('Y/m').'/'.$name,
            'original_filename' => $name,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => fake()->numberBetween(40_000, 4_000_000),
            'checksum_sha256' => hash('sha256', $name),
            'width' => 1200,
            'height' => 1600,
            'is_private' => true,
            'status' => MediaStatus::Ready,
        ];
    }

    public function publicImage(): static
    {
        return $this->state(fn () => [
            'disk' => config('genealogy.media.public_disk'),
            'is_private' => false,
        ]);
    }
}
