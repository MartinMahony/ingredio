<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Recipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * @return HasMany<Collection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * @return HasMany<RecipeScan, $this>
     */
    public function recipeScans(): HasMany
    {
        return $this->hasMany(RecipeScan::class);
    }

    /**
     * Recipes this user has bookmarked from other users (not copies — a
     * lightweight reference that disappears if the original is unpublished
     * or deleted).
     *
     * @return BelongsToMany<Recipe, $this>
     */
    public function savedRecipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_saves')
            ->withTimestamps()
            ->orderByPivotDesc('created_at')
            ->orderByPivotDesc('id');
    }

    public function hasSaved(Recipe $recipe): bool
    {
        return $this->savedRecipes()->where('recipe_id', $recipe->id)->exists();
    }
}
