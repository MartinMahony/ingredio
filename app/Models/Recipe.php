<?php

namespace App\Models;

use App\Enums\RecipeDifficulty;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'title',
    'description',
    'servings',
    'prep_minutes',
    'cook_minutes',
    'total_minutes',
    'difficulty',
    'cuisine',
    'calories',
    'protein_grams',
    'carbs_grams',
    'fat_grams',
    'source_type',
    'source_url',
    'notes',
    'status',
    'extracted_at',
])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => RecipeDifficulty::class,
            'prep_minutes' => 'integer',
            'cook_minutes' => 'integer',
            'total_minutes' => 'integer',
            'calories' => 'integer',
            'protein_grams' => 'decimal:1',
            'carbs_grams' => 'decimal:1',
            'fat_grams' => 'decimal:1',
            'extracted_at' => 'datetime',
            'shared_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)->orderBy('position');
    }

    /**
     * @return HasMany<RecipeStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /**
     * @return BelongsToMany<Collection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class);
    }

    public function isShared(): bool
    {
        return $this->share_token !== null;
    }

    public function hasNutrition(): bool
    {
        return $this->calories !== null
            || $this->protein_grams !== null
            || $this->carbs_grams !== null
            || $this->fat_grams !== null;
    }

    /**
     * Enable (or regenerate) the public read-only share link for this recipe.
     * Intentionally not mass-fillable: only ever set through this method.
     */
    public function enableSharing(): void
    {
        do {
            $token = Str::random(40);
        } while (self::where('share_token', $token)->exists());

        $this->forceFill([
            'share_token' => $token,
            'shared_at' => now(),
        ])->save();
    }

    public function disableSharing(): void
    {
        $this->forceFill([
            'share_token' => null,
            'shared_at' => null,
        ])->save();
    }
}
