<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClanRegistrationStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A request to start a clan.
 *
 * Held apart from `clans` so that nothing half-real is ever queryable: the
 * clan is created when somebody approves, and until then this is the only
 * record that the request was made.
 */
class ClanRegistration extends Model
{
    use HasUlid, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'tribe_id',
        'parent_clan_id',
        'name',
        'native_name',
        'description',
        'ancestor_person_id',
        'requested_by',
        'statement',
    ];

    /**
     * In-memory defaults matching the column defaults. Without these a
     * just-created request reports a null status until it is reloaded, and
     * every rule that asks whether it is still pending answers no.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ClanRegistrationStatus::Pending->value,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ClanRegistrationStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function tribe(): BelongsTo
    {
        return $this->belongsTo(Tribe::class);
    }

    public function parentClan(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'parent_clan_id');
    }

    /** The person the clan descends from, where the requester knows. */
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'ancestor_person_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** The clan this became, once it became one. */
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function isPending(): bool
    {
        return $this->status === ClanRegistrationStatus::Pending;
    }
}
