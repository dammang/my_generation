<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Kind of evidence backing a genealogical fact.
 */
enum SourceType: string
{
    use HasLabel;

    case BirthCertificate = 'birth_certificate';
    case MarriageCertificate = 'marriage_certificate';
    case DeathCertificate = 'death_certificate';
    case ChurchRecord = 'church_record';
    case FamilyBible = 'family_bible';
    case GovernmentRecord = 'government_record';
    case Census = 'census';
    case Gravestone = 'gravestone';
    case Photograph = 'photograph';
    case FamilyDocument = 'family_document';
    case OralTestimony = 'oral_testimony';
    case Book = 'book';
    case Newspaper = 'newspaper';
    case HistoricalRecord = 'historical_record';
    case Website = 'website';
    case Dna = 'dna';
    case Other = 'other';
}
