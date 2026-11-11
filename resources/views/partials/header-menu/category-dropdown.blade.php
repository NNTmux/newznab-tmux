{{-- One desktop category dropdown. Expects $entry from App\Support\Navigation\CategoryMenu::build(). --}}
@php
    $menuLink = 'block rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white focus-visible:bg-white/10 focus-visible:text-white focus-visible:outline-none';
    $menuPanel = 'rounded-xl bg-gray-900 p-1.5 shadow-overlay ring-1 ring-white/10 dark:bg-gray-950';

    // Highlight the section the visitor is in: the current URL is one of this entry's links or sits below one.
    $currentUrl = url()->current();
    $isCurrentSection = false;
    foreach (array_merge(array_column($entry['links'], 'url'), array_column($entry['children'], 'url')) as $entryUrl) {
        $entryUrl = rtrim(strtok((string) $entryUrl, '?'), '/');
        if ($currentUrl === $entryUrl || str_starts_with($currentUrl, $entryUrl.'/')) {
            $isCurrentSection = true;
            break;
        }
    }
@endphp
<div class="relative dropdown-container shrink-0">
    <button type="button" @if($isCurrentSection) data-current-section="true" @endif class="dropdown-toggle flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm font-medium whitespace-nowrap {{ $isCurrentSection ? 'bg-white/10 text-white' : 'text-gray-300' }} transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400" title="{{ $entry['title'] }}">
        <i aria-hidden="true" class="fa-solid {{ $entry['icon'] }} fa-fw"></i>
        <span class="sr-only xl:not-sr-only">{{ $entry['title'] }}</span>
        <i aria-hidden="true" class="fas fa-chevron-down hidden text-[0.65rem] opacity-70 xl:inline"></i>
    </button>
    <div class="dropdown-menu absolute left-0 top-full z-50 mt-1 w-56 {{ $menuPanel }}">
        @foreach($entry['links'] as $link)
            <a href="{{ $link['url'] }}" @if($currentUrl === rtrim(strtok((string) $link['url'], '?'), '/')) aria-current="page" @endif class="{{ $menuLink }} font-medium">{{ $link['label'] }}</a>
        @endforeach
        @if($entry['children'] !== [])
            <div class="my-1.5 border-t border-white/10"></div>
            @foreach($entry['children'] as $child)
                @if($child['languages'] !== [])
                    <div class="relative submenu-container">
                        <button type="button" class="submenu-toggle {{ $menuLink }} flex w-full items-center justify-between text-left">
                            <span>{{ $child['label'] }}</span>
                            <i aria-hidden="true" class="fas fa-chevron-right text-xs opacity-70"></i>
                        </button>
                        <div class="submenu absolute left-full top-0 z-50 ml-1 hidden w-44 {{ $menuPanel }}">
                            <a href="{{ $child['url'] }}" class="{{ $menuLink }} font-medium">{{ __('All :category', ['category' => $child['label']]) }}</a>
                            <div class="my-1.5 border-t border-white/10"></div>
                            @foreach($child['languages'] as $language)
                                <a href="{{ $language['url'] }}" class="{{ $menuLink }}">{{ $language['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ $child['url'] }}" class="{{ $menuLink }}">{{ $child['label'] }}</a>
                @endif
            @endforeach
        @endif
    </div>
</div>
