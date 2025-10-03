@extends('layouts.main')

@section('content')
<div class="bg-white rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600">
                <li><a href="{{ url($site->home_link ?? '/') }}" class="hover:text-blue-600">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li class="text-gray-500">Download Basket</li>
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">
        <!-- RSS Feed Alert -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-start">
            <i class="fa fa-rss-square text-blue-600 text-2xl mr-4 mt-1"></i>
            <div>
                <strong class="text-blue-900">RSS Feed</strong>
                <p class="text-blue-800 mt-1">
                    Your download basket can also be accessed via an
                    <a href="{{ url('/rss/cart?dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token . '&del=1') }}"
                       class="text-blue-600 hover:text-blue-800 underline">RSS feed</a>.
                    Some NZB downloaders can read this feed and automatically start downloading.
                </p>
            </div>
        </div>

        @if(count($results) > 0)
            <!-- Cart Items -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h5 class="text-lg font-semibold text-gray-800">My Download Basket</h5>
                    <div class="flex items-center gap-2">
                        <small class="text-gray-600">With Selected:</small>
                        <div class="flex gap-1">
                            <button type="button" class="nzb_multi_operations_download_cart px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm" title="Download NZBs">
                                <i class="fa fa-cloud-download"></i>
                            </button>
                            <button type="button" class="nzb_multi_operations_cartdelete px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-sm" title="Delete from cart">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left" style="width: 30px">
                                    <input id="check-all" type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Name</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Added</th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($results as $result)
                                <tr id="guid{{ $result->release->guid }}" class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <input id="chk{{ substr($result->release->guid, 0, 7) }}"
                                               type="checkbox"
                                               name="table_records"
                                               class="cart-checkbox form-checkbox h-4 w-4 text-blue-600"
                                               value="{{ $result->release->guid }}">
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/details/' . $result->release->guid) }}"
                                           class="text-blue-600 hover:text-blue-800 font-semibold">
                                            {{ $result->release->searchname }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fa fa-clock-o mr-2"></i>
                                            <span title="{{ $result->created_at }}">{{ \Carbon\Carbon::parse($result->created_at)->diffForHumans() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ url('/getnzb?id=' . $result->release->guid) }}"
                                               class="px-2 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm"
                                               title="Download NZB">
                                                <i class="fa fa-cloud-download"></i>
                                            </a>
                                            <a href="{{ url('/details/' . $result->release->guid) }}"
                                               class="px-2 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm"
                                               title="View details">
                                                <i class="fa fa-info-circle"></i>
                                            </a>
                                            <a href="{{ url('/cart/delete/' . $result->release->guid) }}"
                                               class="px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm"
                                               title="Delete from cart">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                    <span class="text-gray-600">Found {{ count($results) }} items in your basket</span>
                    <div class="flex items-center gap-2">
                        <small class="text-gray-600">With Selected:</small>
                        <div class="flex gap-1">
                            <button type="button" class="nzb_multi_operations_download_cart px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm" title="Download NZBs">
                                <i class="fa fa-cloud-download"></i>
                            </button>
                            <button type="button" class="nzb_multi_operations_cartdelete px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-sm" title="Delete from cart">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Empty Cart -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-8 text-center">
                <i class="fa fa-shopping-basket text-yellow-600 text-5xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Your basket is empty</h3>
                <p class="text-gray-600 mb-4">Add some releases to your download basket to get started.</p>
                <a href="{{ url('/browse/All') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-search mr-2"></i> Browse Releases
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check all checkbox
    const checkAll = document.getElementById('check-all');
    const checkboxes = document.querySelectorAll('.cart-checkbox');

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Download selected
    document.querySelectorAll('.nzb_multi_operations_download_cart').forEach(btn => {
        btn.addEventListener('click', function() {
            const selected = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            if (selected.length === 0) {
                alert('Please select at least one item');
                return;
            }

            // Download all selected NZBs
            selected.forEach(guid => {
                window.open('/getnzb?id=' + guid, '_blank');
            });
        });
    });

    // Delete selected
    document.querySelectorAll('.nzb_multi_operations_cartdelete').forEach(btn => {
        btn.addEventListener('click', function() {
            const selected = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            if (selected.length === 0) {
                alert('Please select at least one item');
                return;
            }

            if (confirm('Are you sure you want to delete ' + selected.length + ' item(s) from your cart?')) {
                // Delete via AJAX
                fetch('/cart/delete/' + selected.join(','), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => {
                    if (response.ok) {
                        // Reload page to show updated cart
                        window.location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    });
});
</script>
@endsection

