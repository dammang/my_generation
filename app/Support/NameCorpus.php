<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Name pools for factories and the local demo seeder ONLY.
 *
 * This is test scaffolding, not product data. Nothing in the application reads
 * it at runtime — every screen reads real records from the real schema. The
 * pool is Zomi/Tedim-flavoured so that development and duplicate-matching tests
 * exercise the orthography the platform is actually being tuned for, rather
 * than Latin names that would hide transliteration bugs.
 */
final class NameCorpus
{
    /** @var list<string> */
    public const MALE_GIVEN = [
        'Thawng', 'Pau', 'Khai', 'Tun', 'Hau', 'Kham', 'Suan', 'Za', 'Kap', 'Lian',
        'Ngul', 'Thang', 'Cin', 'Mang', 'Dal', 'Gin', 'Vum', 'Sian', 'Do', 'Nang',
    ];

    /** @var list<string> */
    public const FEMALE_GIVEN = [
        'Cing', 'Niang', 'Dim', 'Man', 'Vung', 'Nem', 'Ciin', 'Zam', 'Nu', 'Tuang',
        'Lun', 'Kim', 'Huai', 'Iang', 'Nengh', 'Zen', 'Dawn', 'Lang',
    ];

    /** @var list<string> */
    public const SECOND_ELEMENT = [
        'Dam', 'Zam', 'Khua', 'Kham', 'Vum', 'Neng', 'Khoi', 'Hau', 'Tun', 'Dai',
        'Zel', 'Cing', 'Lian', 'Muan', 'Pau', 'Sang', 'Thang', 'Nang', 'Suan', 'Kap',
    ];

    /** @var list<string> */
    public const FAMILY = [
        'Guite', 'Ngaihte', 'Sukte', 'Zahau', 'Hatlang', 'Tedim', 'Khuangli',
        'Simte', 'Vaiphei', 'Thado', 'Gangte', 'Paite',
    ];

    /** @var list<string> Villages and towns, for the demo place tree. */
    public const PLACES = [
        'Tedim', 'Tonzang', 'Cikha', 'Saizang', 'Suangpi', 'Lamzang', 'Mualbem',
        'Khuasak', 'Buanman', 'Vangteh', 'Thuklai', 'Lailui',
    ];

    /**
     * Spelling variants of the same name, used to seed duplicate-detection
     * fixtures: "Thawng Dam" / "Thawngdam" / "Thawng Dham" must all resolve to
     * one ancestor.
     *
     * @return list<string>
     */
    public static function variantsOf(string $name): array
    {
        $collapsed = str_replace(' ', '', $name);
        $aspirated = str_replace(['Th', 'Kh'], ['Thh', 'Khh'], $name);
        $deaspirated = str_replace(['Th', 'Kh'], ['T', 'K'], $name);

        return array_values(array_unique([$collapsed, $aspirated, $deaspirated]));
    }
}
