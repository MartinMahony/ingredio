<?php

namespace App\Actions;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Str;

class SyncRecipeTags
{
    /**
     * Normalize, find-or-create, and sync tags onto a recipe for its owner.
     *
     * @param  list<string>  $names
     */
    public function __invoke(User $user, Recipe $recipe, array $names): void
    {
        $tagIds = collect($names)
            ->map(fn (string $name): string => Str::of($name)->trim()->lower()->limit(50, '')->value())
            ->filter()
            ->unique()
            ->map(fn (string $name) => $user->tags()->firstOrCreate(['name' => $name])->id)
            ->all();

        $recipe->tags()->sync($tagIds);
    }
}
