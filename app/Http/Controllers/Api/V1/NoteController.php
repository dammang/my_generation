<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\NoteAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreNoteRequest;
use App\Http\Resources\V1\NoteResource;
use App\Models\Note;
use App\Models\Person;
use App\Services\Notes\NoteVisibility;
use App\Services\Privacy\ViewerScope;
use App\Services\Tree\LineageDepthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notes on a person.
 *
 * Any member may write one; who may read it is the writer's choice and is
 * enforced here, in SQL, so a listing never returns a note it would then have
 * to hide.
 */
class NoteController extends Controller
{
    public function __construct(
        private readonly ViewerScope $viewer,
        private readonly NoteVisibility $visibility,
    ) {}

    public function index(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        $notes = Note::query()
            ->where('person_id', $person->getKey())
            ->tap(fn ($query) => $this->visibility->scope($query, $this->viewer))
            ->with('author:id,ulid,name')
            ->latest('id')
            ->get();

        return ApiResponse::success(NoteResource::collection($notes));
    }

    public function store(StoreNoteRequest $request, Person $person): JsonResponse
    {
        // Readable to write about: a note is attached to a record, and one
        // nobody may see is not a record this account may annotate.
        $this->authorize('view', $person);

        $author = $request->user();

        $note = Note::create([
            'person_id' => $person->getKey(),
            'author_id' => $author->getKey(),
            'author_person_id' => $author->person_id,
            'body' => $request->string('body')->toString(),
            'audience' => $request->string('audience')->toString(),
        ]);

        if ($note->audience === NoteAudience::Descendants && $author->person_id !== null) {
            // "My descendants" is answered from lineage_depths, and the author
            // is not a root anybody has counted from until now. Computing it
            // here also enrols them: every later change to the graph refreshes
            // the roots that already exist, so the note stays correct as the
            // family grows under them.
            app(LineageDepthService::class)->recomputeFor(
                Person::findOrFail($author->person_id),
            );
        }

        return ApiResponse::created(
            NoteResource::make($note->load('author:id,ulid,name')),
        );
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        // The writer alone. Somebody who cannot read a note has no business
        // deciding it should not exist, and an administrator override here
        // would make "Only me" untrue in a way nobody could see.
        if ($request->user()?->getKey() !== $note->author_id) {
            return ApiResponse::error('This note is not yours.', 403, [], 'FORBIDDEN');
        }

        $note->delete();

        return ApiResponse::noContent();
    }
}
