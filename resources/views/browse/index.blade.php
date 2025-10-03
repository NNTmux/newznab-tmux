@extends('layouts.main')

@section('content')
<div class="bg-white rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site->home_link ?? '/') }}" class="text-gray-700 hover:text-blue-600 inline-flex items-center">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </li>
                @if(isset($parentcat) && $parentcat != '')
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <a href="{{ url('/browse/' . ($parentcat == 'music' ? 'Audio' : $parentcat)) }}" class="text-gray-700 hover:text-blue-600">{{ $parentcat }}</a>
                        </div>
                    </li>
                    @if(isset($catname) && $catname != '' && $catname != 'all')
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                <span class="text-gray-500">{{ $catname }}</span>
                            </div>
                        </li>
                    @endif
                @else
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-gray-500">Browse / {{ $catname ?? 'All' }}</span>
                        </div>
                    </li>
                @endif
            </ol>
        </nav>
    </div>

    @if($results->count() > 0)
        <form id="nzb_multi_operations_form" method="get">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Left Section -->
                    <div class="space-y-3">
                        @if(isset($shows))
                            <div class="flex flex-wrap gap-2 text-sm">
                                <a href="{{ route('series') }}" class="text-blue-600 hover:text-blue-800" title="View available TV series">Series List</a>
                                <span class="text-gray-400">|</span>
                                <a href="{{ route('myshows') }}" class="text-blue-600 hover:text-blue-800" title="Manage your shows">Manage My Shows</a>
                                <span class="text-gray-400">|</span>
                                <a href="{{ url('/rss/myshows?dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token) }}" class="text-blue-600 hover:text-blue-800" title="RSS Feed">RSS Feed</a>
                            </div>
                        @endif

                        @if(isset($covgroup) && $covgroup != '')
                            <div class="flex items-center gap-2 text-sm">
                                <span class="text-gray-600">View:</span>
                                <a href="{{ url('/' . $covgroup . '/' . $category) }}" class="text-blue-600 hover:text-blue-800">Covers</a>
                                <span class="text-gray-400">|</span>
                                <span class="font-semibold text-gray-800">List</span>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            <small class="text-gray-600">With Selected:</small>
                            <div class="flex gap-1">
                                <button type="button" class="nzb_multi_operations_download px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm" title="Download NZBs">
                                    <i class="fa fa-cloud-download"></i>
                                </button>
                                <button type="button" class="nzb_multi_operations_cart px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm" title="Send to Download Basket">
                                    <i class="fa fa-shopping-basket"></i>
                                </button>
                                @if(auth()->user()->hasRole('Admin'))
                                    <button type="button" class="nzb_multi_operations_delete px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition text-sm" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Center Section - Pagination Info -->
                    <div class="flex items-center justify-center">
                        <div class="text-sm text-gray-600">
                            Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{ $results->total() }} results
                        </div>
                    </div>

                    <!-- Right Section - Sort Options -->
                    <div class="flex items-center justify-end">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600">Sort by:</label>
                            <select class="border border-gray-300 rounded px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="window.location.href=this.value">
                                <option value="{{ $orderbyposted ?? '#' }}" {{ request('ob') == 'posted_desc' ? 'selected' : '' }}>Posted</option>
                                <option value="{{ $orderbyname ?? '#' }}" {{ request('ob') == 'name_asc' ? 'selected' : '' }}>Name</option>
                                <option value="{{ $orderbysize ?? '#' }}" {{ request('ob') == 'size_desc' ? 'selected' : '' }}>Size</option>
                                <option value="{{ $orderbyfiles ?? '#' }}" {{ request('ob') == 'files_desc' ? 'selected' : '' }}>Files</option>
                                <option value="{{ $orderbystats ?? '#' }}" {{ request('ob') == 'stats_desc' ? 'selected' : '' }}>Stats</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-3 text-left">
                                <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" id="chkSelectAll">
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Category</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Posted</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Size</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Files</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Stats</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($results as $result)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <input type="checkbox" class="chkRelease rounded border-gray-300 text-blue-600 focus:ring-blue-500" name="release[]" value="{{ $result->guid }}">
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex items-start">
                                        @if($result->cover ?? false)
                                            <img src="{{ $result->cover }}" class="w-12 h-16 object-cover rounded mr-3" alt="Cover">
                                        @endif
                                        <div>
                                            <a href="{{ url('/details/' . $result->guid) }}" class="text-blue-600 hover:text-blue-800 font-medium">{{ $result->searchname }}</a>
                                            <div class="text-xs text-gray-500 mt-1">
                                                @if($result->group_name)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700">
                                                        <i class="fas fa-users mr-1"></i> {{ $result->group_name }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $result->category_name ?? 'Other' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ \Carbon\Carbon::parse($result->postdate)->diffForHumans() }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $result->size_formatted ?? number_format($result->size / 1073741824, 2) . ' GB' }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $result->totalpart ?? 0 }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <div class="flex items-center gap-2">
                                        <span title="Grabs"><i class="fas fa-download text-green-600"></i> {{ $result->grabs ?? 0 }}</span>
                                        <span title="Comments"><i class="fas fa-comment text-blue-600"></i> {{ $result->comments ?? 0 }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ url('/getnzb/' . $result->guid) }}" class="px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm" title="Download NZB">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        <a href="{{ url('/details/' . $result->guid) }}" class="px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm" title="View Details">
                                            <i class="fa fa-info"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $results->links() }}
            </div>
        </form>
    @else
        <div class="px-6 py-12 text-center">
            <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-700 mb-2">No releases found</h3>
            <p class="text-gray-500">Try adjusting your search criteria or browse other categories.</p>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Select all checkbox functionality
    document.getElementById('chkSelectAll')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.chkRelease');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });

    // Multi-operations download
    document.querySelector('.nzb_multi_operations_download')?.addEventListener('click', function() {
        const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            alert('Please select at least one release');
            return;
        }
        // Implement multi-download logic
        selected.forEach(guid => {
            window.open('/getnzb/' + guid, '_blank');
        });
    });

    // Multi-operations cart
    document.querySelector('.nzb_multi_operations_cart')?.addEventListener('click', function() {
        const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            alert('Please select at least one release');
            return;
        }
        // Implement cart logic via AJAX
        fetch('/cart/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ releases: selected })
        }).then(response => response.json())
          .then(data => alert(data.message || 'Added to cart'));
    });
</script>
@endpush
@endsection

