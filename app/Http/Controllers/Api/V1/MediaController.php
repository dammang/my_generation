<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaCollection;
use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreMediaRequest;
use App\Http\Resources\V1\MediaResource;
use App\Models\Media;
use App\Models\Person;
use App\Services\Privacy\PersonVisibilityResolver;
use App\Services\Privacy\ViewerScope;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Photographs, held on R2 with only the pointer in MySQL.
 *
 * The living-person mask governs these exactly as it governs a timeline. A
 * photograph is more identifying than a birth year, so a viewer who may not
 * see somebody's details does not get their face either.
 */
class MediaController extends Controller
{
    public function __construct(
        private readonly ViewerScope $viewer,
        private readonly PersonVisibilityResolver $visibility,
    ) {}

    /** Photographs attached to one person. */
    public function forPerson(Request $request, Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        // FieldMask already carries a `media` flag — the privacy model
        // anticipated this before anything could serve a photograph.
        if (! $this->visibility->mask($this->viewer, $person)->media) {
            return ApiResponse::success([], meta: ['withheld' => true]);
        }

        $media = Media::query()
            ->where('mediable_type', $person->getMorphClass())
            ->where('mediable_id', $person->getKey())
            ->where('status', MediaStatus::Ready)
            ->with('uploader:id,name')
            ->latest('id')
            ->get();

        return ApiResponse::success(
            MediaResource::collection($media),
            meta: ['withheld' => false, 'count' => $media->count()],
        );
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $data = $request->validated();

        $person = Person::where('ulid', $data['person_ulid'])
            ->visibleTo($this->viewer)
            ->firstOrFail();

        $this->authorize('update', $person);

        $file = $request->file('file');
        $disk = config('filesystems.disks.r2') !== null ? 'r2' : 'local';

        // Content-addressed by checksum: the same photograph uploaded twice by
        // two relatives is one object, and the path carries nothing about who
        // is in it.
        $checksum = hash_file('sha256', $file->getRealPath());
        $extension = $file->extension() ?: 'bin';
        $path = "media/{$person->tribe_id}/".substr($checksum, 0, 2)."/{$checksum}.{$extension}";

        $stored = $file->storeAs(
            dirname($path),
            basename($path),
            ['disk' => $disk, 'visibility' => 'private'],
        );

        if ($stored === false) {
            return ApiResponse::error('That photograph could not be stored.', 500, code: 'MEDIA_STORE_FAILED');
        }

        $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];

        $media = Media::create([
            'mediable_type' => $person->getMorphClass(),
            'mediable_id' => $person->getKey(),
            'collection' => $data['collection'] ?? MediaCollection::Gallery,
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            'checksum_sha256' => $checksum,
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'is_private' => $data['is_private'] ?? true,
            'caption' => $data['caption'] ?? null,
            'uploaded_by' => $request->user()->getKey(),
            // No conversion pipeline yet, so what was uploaded is what is
            // served. Marked Ready rather than Processing so it is visible
            // now, and honestly so: nothing is pending.
            'status' => MediaStatus::Ready,
        ]);

        return ApiResponse::created(MediaResource::make($media->load('uploader:id,name')));
    }
}
