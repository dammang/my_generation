<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NoteAudience;
use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Note> */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'body' => fake()->sentence(),
            'audience' => NoteAudience::Clan,
        ];
    }
}
