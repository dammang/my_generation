<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NoteAudience;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something one member wants said about a person, to a chosen audience.
 *
 * Who may read it is decided by NoteVisibility, never here and never by the
 * client: this model holds the audience, not the answer.
 */
class Note extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'person_id',
        'author_id',
        'author_person_id',
        'body',
        'audience',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['audience' => NoteAudience::class];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** The author's own record, as it stood when the note was written. */
    public function authorPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'author_person_id');
    }
}
