<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\DatePrecision;
use App\Enums\PrivacyLevel;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

/**
 * Blueprint macros for the column patterns that repeat across the schema.
 *
 * These exist to make forty migrations readable and, more importantly, to make
 * the patterns impossible to get subtly wrong in one table out of forty.
 * See docs/02-database-architecture.md §1.
 */
class DatabaseMacroServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** Public identifier. Internal bigint ids never leave the server. */
        Blueprint::macro('publicUlid', function (string $column = 'ulid') {
            /** @var Blueprint $this */
            return $this->char($column, 26)->unique();
        });

        /**
         * The four-column uncertain-date pattern plus an indexed year.
         * prefix 'birth' yields birth_date, birth_date_end, birth_date_precision,
         * birth_date_text and birth_year.
         */
        Blueprint::macro('uncertainDate', function (string $prefix, bool $indexYear = true) {
            /** @var Blueprint $this */
            $this->date("{$prefix}_date")->nullable();
            $this->date("{$prefix}_date_end")->nullable();
            $this->enum("{$prefix}_date_precision", DatePrecision::values())
                ->default(DatePrecision::Unknown->value);
            $this->string("{$prefix}_date_text", 120)->nullable();
            $this->smallInteger("{$prefix}_year")->nullable();

            if ($indexYear) {
                $this->index("{$prefix}_year");
            }

            return $this;
        });

        /** created_by / updated_by / verified_by / verified_at. FKs added by the caller. */
        Blueprint::macro('contributable', function () {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $this->timestamp('verified_at')->nullable();

            return $this;
        });

        /**
         * Soft deletes that do not break unique indexes.
         * MySQL treats NULLs as distinct, so a unique key containing deleted_at
         * would still permit duplicate live rows. deleted_token is 0 while the
         * row is live and the row id once it is deleted.
         */
        Blueprint::macro('softDeletesWithToken', function () {
            /** @var Blueprint $this */
            $this->unsignedBigInteger('deleted_token')->default(0);
            $this->softDeletes();

            return $this;
        });

        Blueprint::macro('verificationStatus', function (string $column = 'verification_status') {
            /** @var Blueprint $this */
            return $this->enum($column, VerificationStatus::values())
                ->default(VerificationStatus::Unverified->value)
                ->index();
        });

        Blueprint::macro('privacyLevel', function (string $column = 'privacy_level', ?string $default = null) {
            /** @var Blueprint $this */
            $column = $this->enum($column, PrivacyLevel::values());

            return $default === null
                ? $column->nullable()
                : $column->default($default);
        });

        Blueprint::macro('recordStatus', function (string $column = 'status') {
            /** @var Blueprint $this */
            return $this->enum($column, RecordStatus::values())
                ->default(RecordStatus::Active->value)
                ->index();
        });
    }
}
