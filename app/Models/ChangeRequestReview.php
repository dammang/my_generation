<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reviewer's decision. Append-only.
 */
class ChangeRequestReview extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'change_request_reviews';

    /** @var list<string> */
    protected $fillable = [
        'change_request_id',
        'reviewer_id',
        'decision',
        'comment',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'created_at' => 'datetime',
        ];
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
