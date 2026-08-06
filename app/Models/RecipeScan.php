<?php

namespace App\Models;

use App\Enums\ScanStatus;
use Database\Factories\RecipeScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'recipe_id',
    'status',
    'source_type',
    'source_url',
    'source_disk',
    'source_path',
    'original_filename',
    'provider',
    'model',
    'tokens_used',
    'source_kept',
    'error',
])]
class RecipeScan extends Model
{
    /** @use HasFactory<RecipeScanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScanStatus::class,
            'source_kept' => 'boolean',
            'tokens_used' => 'integer',
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
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => ScanStatus::Processing]);
    }

    public function markReady(Recipe $recipe): void
    {
        $this->update([
            'status' => ScanStatus::Ready,
            'recipe_id' => $recipe->id,
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => ScanStatus::Failed,
            'error' => Str::limit($error, 2000, ''),
        ]);
    }
}
