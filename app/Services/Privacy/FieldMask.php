<?php

declare(strict_types=1);

namespace App\Services\Privacy;

/**
 * Which fields of one record one viewer may see.
 *
 * Serialisation asks this object, never the client. Every path that renders a
 * person — the API, search results, tree nodes, share links, exports,
 * notifications and Filament — goes through the same mask, so there is exactly
 * one place where person field visibility is decided.
 */
final readonly class FieldMask
{
    public function __construct(
        /** May the record be seen at all? A false here means 404 on a direct fetch. */
        public bool $visible = true,
        public bool $name = true,
        public bool $nativeName = true,
        /** The full date. False still permits the year, if $years allows. */
        public bool $exactDates = true,
        public bool $years = true,
        public bool $places = true,
        public bool $biography = true,
        public bool $events = true,
        public bool $media = true,
        /** Email, phone and address of a linked account. */
        public bool $contact = false,
        /** Whether anything at all was withheld — drives the UI's privacy badge. */
        public bool $redacted = false,
    ) {}

    public static function full(): self
    {
        return new self(contact: true);
    }

    /**
     * The record exists and its position in the graph is shown, but its
     * content is withheld. A tree must still render the node: hiding the shape
     * of the graph would silently misrepresent everyone's lineage.
     */
    public static function livingLimited(): self
    {
        return new self(
            exactDates: false,
            years: false,
            places: false,
            biography: false,
            events: false,
            media: false,
            redacted: true,
        );
    }

    /** Living, but the viewer is close enough for approximate dates. */
    public static function livingSummary(): self
    {
        return new self(
            exactDates: false,
            biography: false,
            events: false,
            redacted: true,
        );
    }

    /** A `private` person, or one outside the viewer's reach entirely. */
    public static function hidden(): self
    {
        return new self(
            visible: false,
            name: false,
            nativeName: false,
            exactDates: false,
            years: false,
            places: false,
            biography: false,
            events: false,
            media: false,
            redacted: true,
        );
    }
}
