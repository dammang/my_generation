<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Depth and node budget are capped here, not in the controller, so no endpoint
 * can accidentally ask for an unbounded graph. Exceeding the cap is a 422 with
 * the limit stated, rather than a silent clamp that leaves the client thinking
 * it received everything.
 */
class TreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxDepth = (int) config('genealogy.tree.max_depth');
        $maxNodes = (int) config('genealogy.tree.max_nodes');

        return [
            'ancestors' => ['sometimes', 'integer', 'min:0', "max:{$maxDepth}"],
            'descendants' => ['sometimes', 'integer', 'min:0', "max:{$maxDepth}"],
            'budget' => ['sometimes', 'integer', 'min:1', "max:{$maxNodes}"],
            'include' => ['sometimes', 'string', 'max:120'],
        ];
    }

    public function ancestors(): int
    {
        return $this->integer('ancestors', (int) config('genealogy.tree.default_ancestors'));
    }

    public function descendants(): int
    {
        return $this->integer('descendants', (int) config('genealogy.tree.default_descendants'));
    }

    public function budget(): int
    {
        return $this->integer('budget', (int) config('genealogy.tree.default_nodes'));
    }

    public function includes(string $key): bool
    {
        if (! $this->filled('include')) {
            return $key === 'spouses';   // spouses are on by default
        }

        return in_array($key, explode(',', $this->string('include')->toString()), true);
    }
}
