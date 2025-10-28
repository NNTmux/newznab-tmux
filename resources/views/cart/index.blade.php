@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                <li><a href="{{ url($site['home_link'] ?? '/') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li class="text-gray-500 dark:text-gray-400">Download Basket</li>
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">

        <!-- RSS Feed Alert -->
        <div class="bg-blue-50 dark:bg-gray-700 border border-blue-200 dark:border-gray-600 rounded-lg p-4 mb-6 flex items-start">
            <i class="fa fa-rss-square text-blue-600 dark:text-blue-400 text-2xl mr-4 mt-1"></i>
            <div>
                <strong class="text-blue-900 dark:text-gray-100">RSS Feed</strong>
                <p class="text-blue-800 dark:text-gray-300 mt-1">
                    Your download basket can also be accessed via an
                    <a href="{{ url('/rss/cart?dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token . '&del=1') }}"
                       class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 underline">RSS feed</a>.
                    Some NZB downloaders can read this feed and automatically start downloading.
                </p>
            </div>
        </div>

        @if(count($results) > 0)
            <!-- Cart Items -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h5 class="text-lg font-semibold text-gray-800 dark:text-gray-200">My Download Basket</h5>
                    <div class="flex items-center gap-2">
                        <small class="text-gray-600 dark:text-gray-400">With Selected:</small>
                        <div class="flex gap-1">
                            <button type="button" class="nzb_multi_operations_download_cart px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 text-sm" title="Download NZBs">
                                <i class="fa fa-cloud-download"></i>
                            </button>
                            <button type="button" class="nzb_multi_operations_cartdelete px-3 py-1 bg-red-600 dark:bg-red-700 text-white rounded hover:bg-red-700 dark:hover:bg-red-800 text-sm" title="Delete from cart">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left" style="width: 30px">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input id="check-all" type="checkbox" class="form-checkbox h-4 w-4 text-blue-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:focus:ring-blue-600 focus:ring-2">
                                    </label>
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Name</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Added</th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                            @foreach($results as $result)
                                <tr id="guid{{ $result->release->guid }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3">
                                        <input id="chk{{ substr($result->release->guid, 0, 7) }}"
                                               type="checkbox"
                                               name="table_records"
                                               class="cart-checkbox form-checkbox h-4 w-4 text-blue-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:focus:ring-blue-600 focus:ring-2 cursor-pointer"
                                               value="{{ $result->release->guid }}">
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/details/' . $result->release->guid) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-semibold break-words break-all">
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
                                            <a href="{{ url('/getnzb?id=' . $result->release->guid) }}"
                                               class="px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-sm"
                                               title="Download NZB">
                                                <i class="fa fa-cloud-download"></i>
                                            </a>
                                            <a href="{{ url('/details/' . $result->release->guid) }}"
                                               class="px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-sm"
                                               title="View details">
                                                <i class="fa fa-info-circle"></i>
                                            </a>
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

                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <span class="text-gray-600 dark:text-gray-400">Found {{ count($results) }} items in your basket</span>
                    <div class="flex items-center gap-2">
                        <small class="text-gray-600 dark:text-gray-400">With Selected:</small>
                        <div class="flex gap-1">
                            <button type="button" class="nzb_multi_operations_download_cart px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 text-sm" title="Download NZBs">
                                <i class="fa fa-cloud-download"></i>
                            </button>
                            <button type="button" class="nzb_multi_operations_cartdelete px-3 py-1 bg-red-600 dark:bg-red-700 text-white rounded hover:bg-red-700 dark:hover:bg-red-800 text-sm" title="Delete from cart">
                                <i class="fa fa-trash"></i>
                            </button>
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
                <a href="{{ url('/browse/All') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fa fa-search mr-2"></i> Browse Releases
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
