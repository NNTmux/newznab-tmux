@extends('layouts.main')

@section('content')
<div class="surface-panel rounded-xl shadow-sm">
    <!-- Breadcrumb -->
    <div class="surface-panel-alt px-6 py-4 border-b">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                <li><a href="{{ url($site['home_link'] ?? '/') }}" class="hover:text-primary-600 dark:hover:text-primary-400">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li class="text-gray-500 dark:text-gray-400">Download Basket</li>
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">

        <!-- RSS Feed Alert -->
        <div class="surface-panel-alt border rounded-lg p-4 mb-6 flex items-start">
            <i class="fa fa-rss-square text-primary-600 dark:text-primary-400 text-2xl mr-4 mt-1"></i>
            <div>
                <strong class="text-gray-900 dark:text-gray-100">RSS Feed</strong>
                <p class="text-gray-700 dark:text-gray-300 mt-1">
                    Your download basket can also be accessed via an
                    <a href="{{ url('/rss/cart?dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token . '&del=1') }}"
                       class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 underline">RSS feed</a>.
                    Some NZB downloaders can read this feed and automatically start downloading.
                </p>
            </div>
        </div>

        @if(count($results) > 0)
            <!-- Cart Items -->
            <div class="surface-panel-alt border rounded-xl shadow-sm" x-data="cartPage">
                <div class="px-4 py-3 surface-panel-alt border-b flex justify-between items-center">
                    <h5 class="text-lg font-semibold text-gray-800 dark:text-gray-200">My Download Basket</h5>
                    <div class="flex items-center gap-2">
                        <small class="text-gray-600 dark:text-gray-400">With Selected:</small>
                        <div class="flex gap-1">
                            <x-button variant="success" size="sm" class="nzb_multi_operations_download_cart" title="Download NZBs">
                                <i class="fa fa-cloud-download"></i>
                            </x-button>
                            <x-button variant="danger" size="sm" class="nzb_multi_operations_cartdelete" title="Delete from cart">
                                <i class="fa fa-trash"></i>
                            </x-button>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left w-8">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input id="check-all" type="checkbox" class="form-checkbox h-4 w-4 text-primary-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:focus:ring-primary-600 focus:ring-2" x-model="allChecked" @change="toggleAll()">
                                    </label>
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Name</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Added</th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Action</th>
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
                                               class="cart-checkbox form-checkbox h-4 w-4 text-primary-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:focus:ring-primary-600 focus:ring-2 cursor-pointer"
                                               value="{{ $result->release->guid }}"
                                               @change="onCheckboxChange()">
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/details/' . $result->release->guid) }}"
                                           class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-semibold wrap-break-word break-all">
                                            {{ $result->release->searchname }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                            <i class="fa fa-clock-o mr-2"></i>
                                            <span title="{{ $result->created_at }}">{{ \Carbon\Carbon::parse($result->created_at)->diffForHumans() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <x-button-link href="{{ url('/getnzb?id=' . $result->release->guid) }}"
                                               variant="muted"
                                               size="sm"
                                               title="Download NZB">
                                                <i class="fa fa-cloud-download"></i>
                                            </x-button-link>
                                            <x-button-link href="{{ url('/details/' . $result->release->guid) }}"
                                               size="sm"
                                               title="View details">
                                                <i class="fa fa-info-circle"></i>
                                            </x-button-link>
                                            <button type="button"
                                               class="cart-delete-link px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded hover:bg-red-200 dark:hover:bg-red-900/50 text-sm"
                                               title="Delete from cart"
                                               data-delete-url="{{ url('/cart/delete/' . $result->release->guid) }}"
                                               data-release-name="{{ Str::limit($result->release->searchname, 50) }}">
                                                <i class="fa fa-trash"></i>
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
                                <input type="checkbox"
                                       name="table_records"
                                       class="cart-checkbox form-checkbox h-4 w-4 mt-1 text-primary-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:focus:ring-primary-600 focus:ring-2 cursor-pointer shrink-0"
                                       value="{{ $result->release->guid }}"
                                       @change="onCheckboxChange()">
                                <a href="{{ url('/details/' . $result->release->guid) }}"
                                   class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-semibold text-sm break-all flex-1">
                                    {{ $result->release->searchname }}
                                </a>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    <i class="fa fa-clock-o mr-1"></i>{{ \Carbon\Carbon::parse($result->created_at)->diffForHumans() }}
                                </span>
                                <div class="flex gap-2">
                                    <x-button-link href="{{ url('/getnzb?id=' . $result->release->guid) }}" variant="muted" size="sm" title="Download NZB">
                                        <i class="fa fa-cloud-download"></i>
                                    </x-button-link>
                                    <x-button-link href="{{ url('/details/' . $result->release->guid) }}" size="sm" title="View details">
                                        <i class="fa fa-info-circle"></i>
                                    </x-button-link>
                                    <button type="button" class="cart-delete-link px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded text-sm" title="Delete"
                                            data-delete-url="{{ url('/cart/delete/' . $result->release->guid) }}"
                                            data-release-name="{{ Str::limit($result->release->searchname, 50) }}">
                                        <i class="fa fa-trash"></i>
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
                            <x-button variant="success" size="sm" class="nzb_multi_operations_download_cart" title="Download NZBs">
                                <i class="fa fa-cloud-download"></i>
                            </x-button>
                            <x-button variant="danger" size="sm" class="nzb_multi_operations_cartdelete" title="Delete from cart">
                                <i class="fa fa-trash"></i>
                            </x-button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Empty Cart -->
            <div class="bg-yellow-50 dark:bg-gray-700 border border-yellow-200 dark:border-gray-600 rounded-lg p-8 text-center">
                <i class="fa fa-shopping-basket text-yellow-600 dark:text-yellow-400 text-5xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Your basket is empty</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Add some releases to your download basket to get started.</p>
                <x-button-link href="{{ url('/browse/All') }}" icon="fa fa-search">
                    Browse Releases
                </x-button-link>
            </div>
        @endif
    </div>
</div>
@endsection
