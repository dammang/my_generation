<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Who may read a note.
 *
 * Its own scale rather than PrivacyLevel, because a note answers a question
 * that scale cannot: "my descendants". A record's privacy is about who may see
 * a person; a note's audience is about who the writer is speaking to.
 *
 * Descendants and Family are read from the *author* — it is their line and
 * their kin. Clan is read from the record the note sits on, because that is
 * the family whose page it appears on.
 */
enum NoteAudience: string
{
    use HasLabel;

    /** Everybody descended from the person who wrote it. */
    case Descendants = 'descendants';

    /** Anybody in the clan of the person the note is about. */
    case Clan = 'clan';

    /** The writer's close kin, on the same reach as the privacy model's. */
    case Family = 'family';

    /** Nobody but the writer. */
    case Private = 'private';
}
