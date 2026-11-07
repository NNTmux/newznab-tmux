{{--
    Add/edit form for a watchlist item (My movies, My shows): cover, category chips and actions.
    Expects: $action, $hidden (name => value), $itemTitle, $kind ("movie"/"show"), $coverUrl, $fallbackUrl,
    $rssUrl, $backUrl, $backLabel, $type ("add"/"edit"), $cat_ids, $cat_names, $cat_selected, $from.
--}}
@php
    $isEdit = ($type ?? 'add') === 'edit';
    $selected = array_map('strval', $cat_selected ?? []);
@endphp
<section class="surface-panel rounded-xl p-5 shadow-sm sm:p-6" aria-labelledby="watchlist-form-title">
    <div class="flex flex-col gap-6 sm:flex-row">
        <img width="112" height="168" class="mx-auto aspect-[2/3] w-28 shrink-0 rounded-lg object-cover shadow-md sm:mx-0"
             src="{{ $coverUrl }}" data-fallback-src="{{ $fallbackUrl }}" loading="lazy" decoding="async"
             alt="{{ $itemTitle }} cover" />

        <div class="min-w-0 flex-1">
            <h2 id="watchlist-form-title" class="text-lg font-semibold text-fg">{{ $itemTitle }}</h2>
            <p class="mt-1 text-sm text-fg-muted">
                Choose the categories you want releases from. Leave them all unticked to get every category.
            </p>
            <p class="mt-1 text-xs text-fg-muted">
                <i aria-hidden="true" class="fa-solid fa-rss mr-1"></i>New releases are added to your
                <a href="{{ $rssUrl }}" class="font-medium text-link hover:underline">personal RSS feed</a>.
            </p>

            <form method="POST" action="{{ $action }}" class="mt-5">
                @csrf
                @foreach($hidden as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}"/>
                @endforeach
                @if(!empty($from))
                    <input type="hidden" name="from" value="{{ $from }}" />
                @endif

                <fieldset>
                    <legend class="mb-2 text-sm font-semibold text-fg">Categories</legend>
                    <div class="flex flex-wrap gap-2" id="category-container">
                        @foreach($cat_ids ?? [] as $cat_id)
                            <label class="inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-control border border-line bg-surface px-3 text-sm font-medium text-fg transition hover:bg-surface-hover has-checked:border-primary-500 has-checked:bg-primary-50 has-checked:text-link dark:has-checked:bg-primary-950/50">
                                <x-checkbox id="category_{{ $cat_id }}" name="category[]" value="{{ $cat_id }}" :checked="in_array((string) $cat_id, $selected, true)" />
                                <span>{{ $cat_names[$cat_id] ?? '' }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="mt-5 flex flex-wrap gap-2 border-t border-line pt-4">
                    <x-button type="submit" name="{{ $type ?? 'add' }}" :icon="$isEdit ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus'">
                        {{ $isEdit ? 'Save categories' : 'Add '.$kind }}
                    </x-button>
                    <x-button variant="secondary" :href="$backUrl" icon="fa-solid fa-arrow-left">{{ $backLabel }}</x-button>
                </div>
            </form>
        </div>
    </div>
</section>
