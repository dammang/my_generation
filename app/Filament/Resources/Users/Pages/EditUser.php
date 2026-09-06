<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Services\Privacy\ViewerScopeResolver;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditUser extends EditRecord
{
    /**
     * Attributes the model refuses to mass-assign.
     *
     * They are guarded on purpose: `User::create($request->all())` must never
     * be able to hand out administrator rights or mark an address verified.
     * But EditRecord saves with fill(), so every one of these was silently
     * dropped — the form accepted the change, reported success, and wrote
     * nothing. Toggling somebody to super admin did precisely nothing, with no
     * error to explain it.
     *
     * Set explicitly here because this form is an administrator deliberately
     * editing an account, which is exactly the case mass assignment cannot
     * distinguish from a hostile request body.
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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $wasSuperAdmin = (bool) $record->is_super_admin;
        $hadPerson = $record->person_id;

        $record->fill(Arr::except($data, self::ADMINISTERED));
        $record->forceFill(Arr::only($data, self::ADMINISTERED));
        $record->save();

        // Granting somebody full administrator rights is not an edit like
        // changing a display name, and it should not be possible to discover
        // only by noticing that it happened.
        if ($wasSuperAdmin !== (bool) $record->is_super_admin) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $record->is_super_admin
                    ? 'user.super_admin_granted'
                    : 'user.super_admin_revoked',
                'auditable_type' => $record->getMorphClass(),
                'auditable_id' => $record->getKey(),
                'context' => ['email' => $record->email],
            ]);
        }

        // Both of these change what the account may see, and the answer is
        // cached. Without this it keeps its old entitlements until the cache
        // happens to expire.
        if ($wasSuperAdmin !== (bool) $record->is_super_admin || $hadPerson !== $record->person_id) {
            app(ViewerScopeResolver::class)->forget($record);
        }

        return $record;
    }
}
