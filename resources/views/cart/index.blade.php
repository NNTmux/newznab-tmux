@extends('layouts.main')

@section('content')
<x-page-header title="Download basket" eyebrow="Your saved releases" description="Review saved NZBs, download selected releases, or use the private RSS feed with your downloader." icon="fas fa-basket-shopping">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-box-archive" aria-hidden="true"></i>{{ number_format(count($results)) }} saved</span>
    </x-slot:stats>
</x-page-header>

<div class="surface-panel rounded-xl shadow-sm">

    <div class="px-6 py-4">

        <!-- RSS Feed Alert -->
        <div class="surface-panel-alt border rounded-lg p-4 mb-6 flex items-start">
            <i aria-hidden="true" class="fa-solid fa-rss-square text-link dark:text-primary-400 text-2xl mr-4 mt-1"></i>
            <div>
                <strong class="text-gray-900 dark:text-gray-100">RSS Feed</strong>
                <p class="text-gray-700 dark:text-gray-300 mt-1">
                    Your download basket can also be accessed via an
                    <a href="{{ url('/rss/cart?dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token . '&del=1') }}"
                       class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 underline">RSS feed</a>.
                    Some NZB downloaders can read this feed and automatically start downloading.
                </p>
            </div>
        </div>

        @if(count($results) > 0)
            <!-- Cart Items -->
            <div class="surface-panel-alt border rounded-xl shadow-sm" x-data="cartPage">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <h2 class="text-lg font-semibold text-fg">My Download Basket</h2>
                    <div class="release-bulk-actions" role="group" aria-label="Actions for selected releases">
                        <span class="release-bulk-actions__label">With selected</span>
                        <button type="button" class="nzb_multi_operations_download_cart release-bulk-actions__button release-bulk-actions__download" title="Download selected NZBs">
                            <i class="fa-solid fa-cloud-download" aria-hidden="true"></i>
                            <span>Download</span>
                        </button>
                        <button type="button" class="nzb_multi_operations_cartdelete release-bulk-actions__button release-bulk-actions__delete" title="Remove selected releases from the basket">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            <span>Remove</span>
                        </button>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left w-8">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input id="check-all" type="checkbox" aria-label="Select all releases" class="form-checkbox h-4 w-4 text-link bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:focus:ring-primary-600 focus:ring-2" x-model="allChecked" @change="toggleAll()">
                                    </label>
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Name</th>
                                <th scope="col" class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Added</th>
                                <th scope="col" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                            @foreach($results as $result)
                                @continue(!$result->release)
                                <tr id="guid{{ $result->release->guid }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3">
                                        <input id="chk{{ substr($result->release->guid, 0, 7) }}"
                                               type="checkbox"
                                               name="table_records"
                                               class="cart-checkbox form-checkbox h-4 w-4 text-link bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:focus:ring-primary-600 focus:ring-2 cursor-pointer"
                                               value="{{ $result->release->guid }}"
                                               @change="onCheckboxChange()">
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/details/' . $result->release->guid) }}"
                                           class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-semibold wrap-break-word break-all">
                                            {{ $result->release->searchname }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                            <i aria-hidden="true" class="fa-regular fa-clock mr-2"></i>
                                            <span title="{{ $result->created_at }}">{{ userDateDiffForHumans($result->created_at) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a aria-label="Download NZB" href="{{ route('getnzb.guid', $result->release->guid) }}"
                                               class="px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-sm"
                                               title="Download NZB">
                                                <i aria-hidden="true" class="fa-solid fa-cloud-download"></i>
                                            </a>
                                            <a aria-label="View details" href="{{ url('/details/' . $result->release->guid) }}"
                                               class="px-2 py-1 bg-accent dark:bg-accent-hover text-white rounded hover:bg-accent-hover dark:hover:bg-primary-800 text-sm"
                                               title="View details">
                                                <i aria-hidden="true" class="fa-solid fa-info-circle"></i>
                                            </a>
                                            <button aria-label="Delete from cart" type="button"
                                               class="cart-delete-link px-2 py-1 bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-400 rounded hover:bg-danger-200 dark:hover:bg-danger-900/50 text-sm"
                                               title="Delete from cart"
                                               data-delete-url="{{ url('/cart/delete/' . $result->release->guid) }}"
                                               data-release-name="{{ Str::limit($result->release->searchname, 50) }}">
                                                <i aria-hidden="true" class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($results as $result)
                        @continue(!$result->release)
                        <div id="m-guid{{ $result->release->guid }}" class="p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <input aria-label="Select item" type="checkbox"
                                       name="table_records"
                                       class="cart-checkbox form-checkbox h-4 w-4 mt-1 text-link bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:focus:ring-primary-600 focus:ring-2 cursor-pointer shrink-0"
                                       value="{{ $result->release->guid }}"
                                       @change="onCheckboxChange()">
                                <a href="{{ url('/details/' . $result->release->guid) }}"
                                   class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-semibold text-sm break-all flex-1">
                                    {{ $result->release->searchname }}
                                </a>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    <i aria-hidden="true" class="fa-regular fa-clock mr-1"></i>{{ userDateDiffForHumans($result->created_at) }}
                                </span>
                                <div class="flex gap-2">
                                    <a aria-label="Download NZB" href="{{ route('getnzb.guid', $result->release->guid) }}" class="px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-sm" title="Download NZB">
                                        <i aria-hidden="true" class="fa-solid fa-cloud-download"></i>
                                    </a>
                                    <a aria-label="View details" href="{{ url('/details/' . $result->release->guid) }}" class="px-2 py-1 bg-accent dark:bg-accent-hover text-white rounded text-sm" title="View details">
                                        <i aria-hidden="true" class="fa-solid fa-info-circle"></i>
                                    </a>
                                    <button aria-label="Delete" type="button" class="cart-delete-link px-2 py-1 bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-400 rounded text-sm" title="Delete"
                                            data-delete-url="{{ url('/cart/delete/' . $result->release->guid) }}"
                                            data-release-name="{{ Str::limit($result->release->searchname, 50) }}">
                                        <i aria-hidden="true" class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-4 py-3 surface-panel-alt border-t flex justify-between items-center">
                    <span class="text-gray-600 dark:text-gray-400">Found {{ count($results) }} items in your basket</span>
                    <div class="flex items-center gap-2">
                        <small class="text-gray-600 dark:text-gray-400">With Selected:</small>
                        <div class="flex gap-1">
                            <button aria-label="Download NZBs" type="button" class="nzb_multi_operations_download_cart px-3 py-1 bg-success-600 dark:bg-success-700 text-white rounded hover:bg-success-700 dark:hover:bg-success-800 text-sm" title="Download NZBs">
                                <i aria-hidden="true" class="fa-solid fa-cloud-download"></i>
                            </button>
                            <button aria-label="Delete from cart" type="button" class="nzb_multi_operations_cartdelete px-3 py-1 bg-danger-600 dark:bg-danger-700 text-white rounded hover:bg-danger-700 dark:hover:bg-danger-800 text-sm" title="Delete from cart">
                                <i aria-hidden="true" class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <x-empty-state
                icon="fa-solid fa-shopping-basket"
                title="Your basket is empty"
                message="Add some releases to your download basket to get started."
                :action-url="url('/browse/All')"
                action-label="Browse Releases"
                action-icon="fa-solid fa-search"
            />
        @endif
    </div>
</div>
@endsection
