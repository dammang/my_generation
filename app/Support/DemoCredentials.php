<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The accounts DemoPresentationSeeder creates, in one place because both the
 * seeder and whoever is running the demo need the exact same values.
 *
 * A fixed, printed password rather than a generated one: these are fictional
 * people in a fictional family, made specifically so a room full of people can
 * type the same thing into their own phones during a live demo. Nothing about
 * them is a secret the way SUPER_ADMIN_PASSWORD is.
 */
final class DemoCredentials
{
    public const PASSWORD = 'DemoTeam2026';

    public const VIEWER_EMAIL = 'demo.viewer@khanggui.com';

    public const APPLICANT_EMAIL = 'demo.applicant@khanggui.com';

    public const ADMIN_EMAIL = 'demo.admin@khanggui.com';
}
