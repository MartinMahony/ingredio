<?php

use function Livewire\Volt\{state, mount, rules, computed};
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
    'calories' => '',
    'protein_grams' => '',
    'carbs_grams' => '',
    'fat_grams' => '',
    'notes' => '',
    'ingredients' => [],
    'steps' => [],
    'tags' => '',
]);

$existingTags = computed(function () {
    return auth()->user()->tags()->orderBy('name')->pluck('name');
});

mount(function (?Recipe $recipe = null) {
    if ($recipe && $recipe->exists) {
        Gate::authorize('update', $recipe);

        $recipe->load(['ingredients', 'steps', 'tags']);

        $this->recipeId = $recipe->id;
        $this->title = $recipe->title;
        $this->description = (string) $recipe->description;
        $this->servings = (string) $recipe->servings;
        $this->prep_minutes = (string) $recipe->prep_minutes;
        $this->cook_minutes = (string) $recipe->cook_minutes;
        $this->difficulty = $recipe->difficulty?->value ?? '';
        $this->cuisine = (string) $recipe->cuisine;
        $this->calories = (string) $recipe->calories;
        $this->protein_grams = (string) $recipe->protein_grams;
        $this->carbs_grams = (string) $recipe->carbs_grams;
        $this->fat_grams = (string) $recipe->fat_grams;
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
    $this->calories = $this->calories === '' ? null : $this->calories;
    $this->protein_grams = $this->protein_grams === '' ? null : $this->protein_grams;
    $this->carbs_grams = $this->carbs_grams === '' ? null : $this->carbs_grams;
    $this->fat_grams = $this->fat_grams === '' ? null : $this->fat_grams;

    $validated = $this->validate([
        'title' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:10000'],
        'servings' => ['nullable', 'string', 'max:50'],
        'prep_minutes' => ['nullable', 'integer', 'min:0', 'max:10000'],
        'cook_minutes' => ['nullable', 'integer', 'min:0', 'max:10000'],
        'difficulty' => ['nullable', Rule::enum(RecipeDifficulty::class)],
        'cuisine' => ['nullable', 'string', 'max:100'],
        'calories' => ['nullable', 'integer', 'min:0', 'max:20000'],
        'protein_grams' => ['nullable', 'numeric', 'min:0', 'max:2000'],
        'carbs_grams' => ['nullable', 'numeric', 'min:0', 'max:2000'],
        'fat_grams' => ['nullable', 'numeric', 'min:0', 'max:2000'],
        'notes' => ['nullable', 'string', 'max:10000'],
        'ingredients' => ['array', 'max:200'],
        'ingredients.*.name' => ['nullable', 'string', 'max:255'],
        'steps' => ['array', 'max:200'],
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
        'calories' => $validated['calories'],
        'protein_grams' => $validated['protein_grams'],
        'carbs_grams' => $validated['carbs_grams'],
        'fat_grams' => $validated['fat_grams'],
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

<div x-data="{ dirty: false }" x-on:beforeunload.window="if (dirty) $event.returnValue = ''">
    <div class="mb-6">
        <a href="{{ $recipeId ? route('recipes.show', $recipeId) : route('dashboard') }}"
            x-on:click.prevent="
                if (dirty && ! confirm('You have unsaved changes. Discard them?')) return;
                Livewire.navigate($el.href);
            "
            class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
            &larr; Cancel
        </a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">
            {{ $recipeId ? 'Edit recipe' : 'New recipe' }}
        </h1>
    </div>

    <form wire:submit="save" x-on:input="dirty = true" class="space-y-8">
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
                    <span class="mb-1 block text-sm font-medium">Nutrition (per serving)</span>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="calories" class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Calories
                                (kcal)</label>
                            <input wire:model="calories" id="calories" type="number" min="0"
                                class="{{ $inputClass }} w-full">
                            @error('calories')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="protein_grams" class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Protein
                                (g)</label>
                            <input wire:model="protein_grams" id="protein_grams" type="number" min="0" step="0.1"
                                class="{{ $inputClass }} w-full">
                            @error('protein_grams')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="carbs_grams" class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Carbs
                                (g)</label>
                            <input wire:model="carbs_grams" id="carbs_grams" type="number" min="0" step="0.1"
                                class="{{ $inputClass }} w-full">
                            @error('carbs_grams')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="fat_grams" class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Fat
                                (g)</label>
                            <input wire:model="fat_grams" id="fat_grams" type="number" min="0" step="0.1"
                                class="{{ $inputClass }} w-full">
                            @error('fat_grams')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div>
                    <label for="tags" class="mb-1 block text-sm font-medium">Tags</label>
                    <input wire:model="tags" id="tags" type="text" placeholder="e.g. vegetarian, soup, italian"
                        list="existing-tags" class="{{ $inputClass }} w-full">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Comma-separated.</p>
                    <datalist id="existing-tags">
                        @foreach ($this->existingTags as $existingTag)
                            <option value="{{ $existingTag }}"></option>
                        @endforeach
                    </datalist>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-medium">Ingredients</h2>
                <button type="button" wire:click="addIngredient"
                    wire:loading.attr="disabled" wire:target="addIngredient"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800 disabled:opacity-75">
                    + Add
                </button>
            </div>

            <div class="space-y-3">
                @foreach ($ingredients as $index => $ingredient)
                    <div wire:key="ingredient-{{ $index }}" class="flex flex-wrap items-start gap-2 sm:flex-nowrap">
                        <input wire:model="ingredients.{{ $index }}.quantity" type="text" placeholder="Qty"
                            aria-label="Ingredient {{ $index + 1 }} quantity" class="{{ $inputClass }} sm:w-20">
                        <input wire:model="ingredients.{{ $index }}.unit" type="text" placeholder="Unit"
                            aria-label="Ingredient {{ $index + 1 }} unit" class="{{ $inputClass }} sm:w-24">
                        <input wire:model="ingredients.{{ $index }}.name" type="text" placeholder="Ingredient"
                            aria-label="Ingredient {{ $index + 1 }} name" class="{{ $inputClass }} min-w-0 flex-1">
                        <input wire:model="ingredients.{{ $index }}.group" type="text" placeholder="Section (optional)"
                            aria-label="Ingredient {{ $index + 1 }} section" class="{{ $inputClass }} sm:w-40">
                        <button type="button" wire:click="removeIngredient({{ $index }})"
                            wire:loading.attr="disabled" wire:target="removeIngredient"
                            class="rounded-md p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-75 dark:hover:bg-red-900/30"
                            title="Remove" aria-label="Remove ingredient {{ $index + 1 }}">
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
                    wire:loading.attr="disabled" wire:target="addStep"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800 disabled:opacity-75">
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
                            aria-label="Step {{ $index + 1 }} instruction" class="{{ $inputClass }} min-w-0 flex-1"></textarea>
                        <input wire:model="steps.{{ $index }}.minutes" type="number" min="0" placeholder="Min"
                            aria-label="Step {{ $index + 1 }} minutes" class="{{ $inputClass }} w-20">
                        <button type="button" wire:click="removeStep({{ $index }})"
                            wire:loading.attr="disabled" wire:target="removeStep"
                            class="rounded-md p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-75 dark:hover:bg-red-900/30"
                            title="Remove" aria-label="Remove step {{ $index + 1 }}">
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
            <a href="{{ $recipeId ? route('recipes.show', $recipeId) : route('dashboard') }}"
                x-on:click.prevent="
                    if (dirty && ! confirm('You have unsaved changes. Discard them?')) return;
                    Livewire.navigate($el.href);
                "
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                Cancel
            </a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-75">
                <span wire:loading.remove wire:target="save">{{ $recipeId ? 'Save changes' : 'Create recipe' }}</span>
                <span wire:loading wire:target="save">Saving&hellip;</span>
            </button>
        </div>
    </form>
</div>
