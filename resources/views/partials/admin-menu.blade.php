{{-- Admin sidebar. Items come from App\Support\Navigation\AdminNavigation (shared by AdminDataComposer). --}}
@php
    $adminNavGroups = $adminNav ?? \App\Support\Navigation\AdminNavigation::forRequest(request());
@endphp
<div class="admin-sidebar-menu flex flex-col gap-0.5 px-3 py-2">
    @foreach($adminNavGroups as $group)
        @if(isset($group['url']))
            <x-nav-link :href="$group['url']" :icon="'fa-solid '.$group['icon']" :active="$group['active']" title="{{ $group['label'] }}">{{ $group['label'] }}</x-nav-link>
        @else
            <div class="admin-nav-group" x-data="adminSubmenu" data-open="{{ $group['open'] ? 'true' : 'false' }}">
                <button type="button" @click="toggle()" :aria-expanded="open" aria-controls="admin-nav-{{ $loop->index }}" title="{{ $group['label'] }}"
                        @class(['nav-link admin-nav-group__toggle w-full justify-between', 'admin-nav-group__toggle--current' => $group['open']])>
                    <span class="flex min-w-0 items-center gap-3">
                        <i aria-hidden="true" class="fa-solid {{ $group['icon'] }} fa-fw shrink-0"></i>
                        <span class="admin-nav-group__label min-w-0 truncate">{{ $group['label'] }}</span>
                    </span>
                    <i aria-hidden="true" class="admin-nav-group__chevron fas fa-chevron-down text-xs transition-transform" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div id="admin-nav-{{ $loop->index }}" x-show="open" x-cloak class="admin-nav-group__children ml-4 mt-0.5 flex flex-col gap-0.5 border-l border-white/10 pl-2">
                    @foreach($group['children'] as $child)
                        <x-nav-link :href="$child['url']" :icon="'fa-solid '.$child['icon']" :active="$child['active']" title="{{ $child['label'] }}">{{ $child['label'] }}</x-nav-link>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
