<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Living-person inference
    |--------------------------------------------------------------------------
    | A person is treated as living (the strictest privacy handling) unless the
    | server can prove otherwise. Someone with no dates at all is treated as
    | living: fail closed.
    */
    'living' => [
        'max_age' => (int) env('GENEALOGY_LIVING_MAX_AGE', 110),
        'minor_age' => (int) env('GENEALOGY_MINOR_AGE', 18),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tree traversal limits
    |--------------------------------------------------------------------------
    | No endpoint may return an unbounded graph. Depth AND node budget are
    | always capped; truncation drops the furthest generations first.
    */
    'tree' => [
        'max_depth' => (int) env('GENEALOGY_TREE_MAX_DEPTH', 8),
        'default_ancestors' => (int) env('GENEALOGY_TREE_DEFAULT_ANCESTORS', 3),
        'default_descendants' => (int) env('GENEALOGY_TREE_DEFAULT_DESCENDANTS', 2),
        'max_nodes' => (int) env('GENEALOGY_TREE_MAX_NODES', 800),
        'default_nodes' => (int) env('GENEALOGY_TREE_DEFAULT_NODES', 400),
        'cache_ttl' => (int) env('GENEALOGY_TREE_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy defaults
    |--------------------------------------------------------------------------
    */
    'privacy' => [
        'default_person_level' => env('GENEALOGY_DEFAULT_PRIVACY', 'family'),
        // Deceased longer than this many years relax to the tribe default.
        'historical_after_years' => 100,
        // How far kinship extends when resolving the "family" scope.
        'kin_generations_up' => 2,
        'kin_generations_down' => 2,
        'kin_max_people' => 400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Contribution trust ramp
    |--------------------------------------------------------------------------
    | A contributor with fewer than this many approved contributions is routed
    | through change requests even for unverified records. Set to 0 to disable.
    */
    'trust_ramp' => (int) env('GENEALOGY_TRUST_RAMP', 3),

    /*
    |--------------------------------------------------------------------------
    | Multi-tribe affiliation
    |--------------------------------------------------------------------------
    | people.tribe_id is always the primary affiliation used for scoping and
    | indexing. When enabled, person_affiliations records additional tribes and
    | clans (mixed-marriage lineages) without widening the hot path.
    */
    'multi_tribe' => (bool) env('GENEALOGY_MULTI_TRIBE', true),

    /*
    |--------------------------------------------------------------------------
    | Duplicate detection
    |--------------------------------------------------------------------------
    | Candidates are found by blocking on shared match keys, then scored.
    | Nothing is ever merged automatically, at any score.
    */
    'matching' => [
        'threshold' => (float) env('GENEALOGY_DUPLICATE_THRESHOLD', 0.82),
        'birth_year_tolerance' => 2,
        'weights' => [
            'name_similarity' => 0.30,
            'name_phonetic' => 0.15,
            'birth_year' => 0.20,
            'death_year' => 0.10,
            'birth_place' => 0.10,
            'shared_parent' => 0.10,
            'shared_spouse' => 0.05,
        ],

        /*
        | Transliteration applied before phonetic encoding. Metaphone alone is
        | tuned for English; these rules normalise Tedim/Zomi orthography so
        | "Thawng Dam", "Thawngdam" and "Thawng Dham" collapse to one key.
        | Applied in order, on the lowercased, whitespace-stripped name.
        */
        'transliteration' => [
            'default' => [
                'ph' => 'f',
                'th' => 't',
                'kh' => 'k',
                'ch' => 'c',
                'zh' => 'z',
                'gh' => 'g',
                'dh' => 'd',
                'bh' => 'b',
                'aa' => 'a',
                'ee' => 'i',
                'oo' => 'u',
                'ii' => 'i',
                'uu' => 'u',
                'w' => 'v',
                'y' => 'i',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrity warnings
    |--------------------------------------------------------------------------
    | Thresholds for the warnings returned alongside a successful write.
    | These never block a write — historical records are legitimately strange,
    | and blocking a contributor loses the data forever.
    */
    'warnings' => [
        'min_parent_age' => 12,
        'max_mother_age' => 55,
        'max_father_age' => 80,
        'max_lifespan' => 120,
        'min_marriage_age' => 12,
        'posthumous_birth_months' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */
    'media' => [
        'public_disk' => env('MEDIA_PUBLIC_DISK', 'media_public'),
        'private_disk' => env('MEDIA_PRIVATE_DISK', 'media_private'),
        'max_image_kb' => 12288,
        'max_audio_kb' => 102400,
        'max_video_kb' => 512000,
        'max_doc_kb' => 25600,
        'image_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/tiff'],
        'audio_mimes' => ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/wav', 'audio/ogg'],
        'video_mimes' => ['video/mp4', 'video/quicktime', 'video/webm'],
        'doc_mimes' => ['application/pdf'],
        'signed_url_ttl' => 300,
        'conversions' => [
            'thumb' => 240,
            'medium' => 960,
        ],
    ],
];
