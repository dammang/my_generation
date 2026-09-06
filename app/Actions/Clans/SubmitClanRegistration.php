<?php

declare(strict_types=1);

namespace App\Actions\Clans;

use App\Enums\ClanRegistrationStatus;
use App\Exceptions\GenealogyRuleException;
use App\Models\ClanRegistration;
use App\Models\Tribe;
use App\Models\User;

/**
 * "I would like to start the Guite clan here."
 *
 * Recorded, not created. A clan is a claim about how a tribe is organised, and
 * everybody who later joins it inherits that claim — so somebody who already
 * administers the tribe decides, not the person asking.
 */
class SubmitClanRegistration
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(User $requester, Tribe $tribe, array $attributes): ClanRegistration
    {
        // One at a time, per name, per tribe. Without this a tap that looks
        // like it did nothing produces four identical requests, and whoever
        // reviews them has to work out that they are the same one.
        $existing = ClanRegistration::where('tribe_id', $tribe->getKey())
            ->where('requested_by', $requester->getKey())
            ->where('name', $attributes['name'])
            ->where('status', ClanRegistrationStatus::Pending)
            ->exists();

        if ($existing) {
            throw new GenealogyRuleException(
                'You have already asked to start a clan by that name here.',
                'CLAN_REGISTRATION_PENDING',
            );
        }

        return ClanRegistration::create([
            ...$attributes,
            'tribe_id' => $tribe->getKey(),
            'requested_by' => $requester->getKey(),
        ]);
    }
}
