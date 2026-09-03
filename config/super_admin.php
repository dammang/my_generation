<?php

declare(strict_types=1);

/*
 * The first administrator, read through config rather than env().
 *
 * A deployed app runs `config:cache`, and once it does Laravel never reads
 * .env again — env() then returns its default no matter what the server has
 * set. Reading these directly meant a production seeder reported the password
 * as missing when it was sitting right there in .env, and created no admin.
 */
return [
    'email' => env('SUPER_ADMIN_EMAIL', 'admin@mygeneration.test'),
    'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
    'password' => env('SUPER_ADMIN_PASSWORD'),
];
