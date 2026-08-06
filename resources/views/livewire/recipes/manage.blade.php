<?php

use function Livewire\Volt\{state, mount, rules};
use App\Actions\SyncRecipeTags;
use App\Enums\RecipeDifficulty;
use App\Models\Recipe;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

state([
    'recipeId' => null,
    'title' => '',
    'description' => '',
    'servings' => '',
    'prep_minutes' => '',
    'cook_minutes' => '',
    'difficulty' => '',
    'cuisine' => '',
    'notes' => '',
    'ingredients' => [],
    'steps' => [],
    'tags' => '',
]);

mount(function (?Recipe $recipe = null) {
    if ($recipe && $recipe->exists) {
        Gate::authorize('update', $recipe);

        $this->recipeId = $recipe->id;
        $this->title = $recipe->title;
        $this->description = (string) $recipe->description;
        $this->servings = (string) $recipe->servings;
        $this->prep_minutes = (string) $recipe->prep_minutes;
        $this->cook_minutes = (string) $recipe->cook_minutes;
        $this->difficulty = $recipe->difficulty?->value ?? '';
        $this->cuisine = (string) $recipe->cuisine;
        $this->notes = (string) $recipe->notes;

        $this->ingredients = $recipe->ingredients->map(fn ($i) => [
            'group' => (string) $i->group,
            'quantity' => (string) $i->quantity,
            'unit' => (string) $i->unit,
            'name' => $i->name,
            'note' => (string) $i->note,
        ])->all();

        $this->steps = $recipe->steps->map(fn ($s) => [
            'instruction' => $s->instruction,
            'minutes' => (string) $s->minutes,
        ])->all();

        $this->tags = $recipe->tags->pluck('name')->implode(', ');
    } else {
        Gate::authorize('create', Recipe::class);
    }

    if ($this->ingredients === []) {
        $this->ingredients = [['group' => '', 'quantity' => '', 'unit' => '', 'name' => '', 'note' => '']];
    }

    if ($this->steps === []) {
        $this->steps = [['instruction' => '', 'minutes' => '']];
    }
});

$addIngredient = function () {
    $this->ingredients[] = ['group' => '', 'quantity' => '', 'unit' => '', 'name' => '', 'note' => ''];
};

$removeIngredient = function (int $index) {
    unset($this->ingredients[$index]);
    $this->ingredients = array_values($this->ingredients);
};

$addStep = function () {
    $this->steps[] = ['instruction' => '', 'minutes' => ''];
};

$removeStep = function (int $index) {
    unset($this->steps[$index]);
    $this->steps = array_values($this->steps);
};

$save = function () {
    $this->prep_minutes = $this->prep_minutes === '' ? null : $this->prep_minutes;
    $this->cook_minutes = $this->cook_minutes === '' ? null : $this->cook_minutes;

    $validated = $this->validate([
        'title' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'servings' => ['nullable', 'string', 'max:50'],
        'prep_minutes' => ['nullable', 'integer', 'min:0', 'max:10000'],
        'cook_minutes' => ['nullable', 'integer', 'min:0', 'max:10000'],
        'difficulty' => ['nullable', Rule::enum(RecipeDifficulty::class)],
        'cuisine' => ['nullable', 'string', 'max:100'],
        'notes' => ['nullable', 'string'],
        'ingredients' => ['array'],
        'ingredients.*.name' => ['nullable', 'string', 'max:255'],
        'steps' => ['array'],
        'steps.*.instruction' => ['nullable', 'string', 'max:2000'],
    ]);

    $recipe = $this->recipeId
        ? Recipe::findOrFail($this->recipeId)
        : new Recipe;

    if ($recipe->exists) {
        Gate::authorize('update', $recipe);
    } else {
        Gate::authorize('create', Recipe::class);
    }

    $total = null;
    if ($validated['prep_minutes'] !== null || $validated['cook_minutes'] !== null) {
        $total = (int) $validated['prep_minutes'] + (int) $validated['cook_minutes'];
    }

    $recipe->fill([
        'user_id' => auth()->id(),
        'title' => $validated['title'],
        'description' => $this->description ?: null,
        'servings' => $this->servings ?: null,
        'prep_minutes' => $validated['prep_minutes'],
        'cook_minutes' => $validated['cook_minutes'],
        'total_minutes' => $total,
        'difficulty' => $this->difficulty ?: null,
        'cuisine' => $this->cuisine ?: null,
        'notes' => $this->notes ?: null,
        'source_type' => $recipe->source_type ?? 'manual',
        'status' => 'ready',
    ])->save();

    $recipe->ingredients()->delete();
    $position = 0;
    foreach ($this->ingredients as $row) {
        if (trim((string) $row['name']) === '') {
            continue;
        }

        $recipe->ingredients()->create([
            'group' => $row['group'] ?: null,
            'position' => $position++,
            'quantity' => $row['quantity'] ?: null,
            'unit' => $row['unit'] ?: null,
            'name' => $row['name'],
            'note' => $row['note'] ?: null,
        ]);
    }

    $recipe->steps()->delete();
    $position = 0;
    foreach ($this->steps as $row) {
        if (trim((string) $row['instruction']) === '') {
            continue;
        }

        $recipe->steps()->create([
            'position' => $position++,
            'instruction' => $row['instruction'],
            'minutes' => $row['minutes'] !== '' ? (int) $row['minutes'] : null,
        ]);
    }

    app(SyncRecipeTags::class)(auth()->user(), $recipe, explode(',', $this->tags));

    $this->redirect(route('recipes.show', $recipe), navigate: true);
};

?>

@php($inputClass = 'rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800')

<div>
    <div class="mb-6">
        <a href="{{ $recipeId ? route('recipes.show', $recipeId) : route('dashboard') }}" wire:navigate
            class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
            &larr; Cancel
        </a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">
            {{ $recipeId ? 'Edit recipe' : 'New recipe' }}
        </h1>
    </div>

    <form wire:submit="save" class="space-y-8">
        <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-base font-medium">Details</h2>

            <div class="space-y-4">
                <div>
                    <label for="title" class="mb-1 block text-sm font-medium">Title</label>
                    <input wire:model="title" id="title" type="text" class="{{ $inputClass }} w-full">
                    @error('title')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="mb-1 block text-sm font-medium">Description</label>
                    <textarea wire:model="description" id="description" rows="2" class="{{ $inputClass }} w-full"></textarea>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="servings" class="mb-1 block text-sm font-medium">Servings</label>
                        <input wire:model="servings" id="servings" type="text" class="{{ $inputClass }} w-full">
                    </div>
                    <div>
                        <label for="prep_minutes" class="mb-1 block text-sm font-medium">Prep (min)</label>
                        <input wire:model="prep_minutes" id="prep_minutes" type="number" min="0" class="{{ $inputClass }} w-full">
                        @error('prep_minutes')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="cook_minutes" class="mb-1 block text-sm font-medium">Cook (min)</label>
                        <input wire:model="cook_minutes" id="cook_minutes" type="number" min="0" class="{{ $inputClass }} w-full">
                        @error('cook_minutes')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="difficulty" class="mb-1 block text-sm font-medium">Difficulty</label>
                        <select wire:model="difficulty" id="difficulty" class="{{ $inputClass }} w-full">
                            <option value="">—</option>
                            @foreach (App\Enums\RecipeDifficulty::cases() as $level)
                                <option value="{{ $level->value }}">{{ $level->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="cuisine" class="mb-1 block text-sm font-medium">Cuisine</label>
                    <input wire:model="cuisine" id="cuisine" type="text" class="{{ $inputClass }} w-full">
                </div>

                <div>
                    <label for="tags" class="mb-1 block text-sm font-medium">Tags</label>
                    <input wire:model="tags" id="tags" type="text" placeholder="e.g. vegetarian, soup, italian"
                        class="{{ $inputClass }} w-full">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Comma-separated.</p>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-medium">Ingredients</h2>
                <button type="button" wire:click="addIngredient"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                    + Add
                </button>
            </div>

            <div class="space-y-3">
                @foreach ($ingredients as $index => $ingredient)
                    <div wire:key="ingredient-{{ $index }}" class="flex flex-wrap items-start gap-2 sm:flex-nowrap">
                        <input wire:model="ingredients.{{ $index }}.quantity" type="text" placeholder="Qty"
                            class="{{ $inputClass }} sm:w-20">
                        <input wire:model="ingredients.{{ $index }}.unit" type="text" placeholder="Unit"
                            class="{{ $inputClass }} sm:w-24">
                        <input wire:model="ingredients.{{ $index }}.name" type="text" placeholder="Ingredient"
                            class="{{ $inputClass }} min-w-0 flex-1">
                        <input wire:model="ingredients.{{ $index }}.group" type="text" placeholder="Section (optional)"
                            class="{{ $inputClass }} sm:w-40">
                        <button type="button" wire:click="removeIngredient({{ $index }})"
                            class="rounded-md p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30"
                            title="Remove">
                            &times;
                        </button>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-medium">Method</h2>
                <button type="button" wire:click="addStep"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                    + Add step
                </button>
            </div>

            <div class="space-y-3">
                @foreach ($steps as $index => $step)
                    <div wire:key="step-{{ $index }}" class="flex items-center gap-2">
                        <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-gray-100 text-sm font-medium dark:bg-gray-800">
                            {{ $index + 1 }}
                        </span>
                        <textarea wire:model="steps.{{ $index }}.instruction" rows="1" placeholder="Describe this step"
                            class="{{ $inputClass }} min-w-0 flex-1"></textarea>
                        <input wire:model="steps.{{ $index }}.minutes" type="number" min="0" placeholder="Min"
                            class="{{ $inputClass }} w-20">
                        <button type="button" wire:click="removeStep({{ $index }})"
                            class="rounded-md p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30"
                            title="Remove">
                            &times;
                        </button>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <label for="notes" class="mb-1 block text-base font-medium">Notes</label>
            <textarea wire:model="notes" id="notes" rows="3" class="{{ $inputClass }} w-full"></textarea>
        </section>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $recipeId ? route('recipes.show', $recipeId) : route('dashboard') }}" wire:navigate
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                Cancel
            </a>
            <button type="submit"
                class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                {{ $recipeId ? 'Save changes' : 'Create recipe' }}
            </button>
        </div>
    </form>
</div>
