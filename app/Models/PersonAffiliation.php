<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A secondary tribe/clan affiliation, for mixed-marriage lineages.
 * people.tribe_id stays the primary affiliation on the scoping hot path.
 */
class PersonAffiliation extends Model
{
    protected $table = 'person_affiliations';

    /** @var list<string> */
    protected $fillable = [
        'person_id',
        'tribe_id',
        'clan_id',
        'note',
        'created_by',
    ];
}
