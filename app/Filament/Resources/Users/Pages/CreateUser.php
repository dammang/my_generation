<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateUser extends CreateRecord
{
    /**
     * Guarded on the model so no request body can grant them, and therefore
     * dropped by CreateRecord's mass assignment — an account created here with
     * the super admin toggle on came out as an ordinary user, silently.
     *
     * @var list<string>
     */
    private const ADMINISTERED = [
        'is_super_admin',
        'status',
        'email_verified_at',
        'person_id',
    ];

    protected static string $resource = UserResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User(Arr::except($data, self::ADMINISTERED));
        $user->forceFill(Arr::only($data, self::ADMINISTERED));
        $user->save();

        if ($user->is_super_admin) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'user.super_admin_granted',
                'auditable_type' => $user->getMorphClass(),
                'auditable_id' => $user->getKey(),
                'context' => ['email' => $user->email, 'granted_via' => 'creation'],
            ]);
        }

        return $user;
    }
}
