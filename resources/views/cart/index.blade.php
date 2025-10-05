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
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input id="check-all" type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                                    </label>
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
                                            <button type="button"
                                               class="cart-delete-link px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm"
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

@push('styles')
<style>
/* Confirmation Modal Styles */
.confirmation-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    animation: fadeIn 0.2s ease-out;
}

.confirmation-modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    max-width: 500px;
    width: 90%;
    padding: 24px;
    animation: slideUp 0.3s ease-out;
}

.confirmation-modal-header {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}

.confirmation-modal-icon {
    width: 48px;
    height: 48px;
    background-color: #fee2e2;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
}

.confirmation-modal-icon i {
    color: #ef4444;
    font-size: 24px;
}

.confirmation-modal-title {
    font-size: 20px;
    font-weight: 600;
    color: #1f2937;
}

.confirmation-modal-body {
    color: #6b7280;
    margin-bottom: 24px;
    line-height: 1.6;
}

.confirmation-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.confirmation-modal-footer button {
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    font-size: 14px;
}

.btn-cancel {
    background-color: #f3f4f6;
    color: #374151;
}

.btn-cancel:hover {
    background-color: #e5e7eb;
}

.btn-confirm {
    background-color: #ef4444;
    color: white;
}

.btn-confirm:hover {
    background-color: #dc2626;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>
@endpush

@push('scripts')
<script>
console.log('Script tag loaded');

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    console.log('Cart page JavaScript initialized');
    console.log('showToast available:', typeof showToast);

    // Show custom confirmation modal
    function showConfirmation(message, onConfirm) {
        console.log('showConfirmation called with message:', message);

        const modal = document.createElement('div');
        modal.className = 'confirmation-modal';
        modal.innerHTML = `
            <div class="confirmation-modal-content">
                <div class="confirmation-modal-header">
                    <div class="confirmation-modal-icon">
                        <i class="fa fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="confirmation-modal-title">Confirm Deletion</h3>
                </div>
                <div class="confirmation-modal-body">
                    ${message}
                </div>
                <div class="confirmation-modal-footer">
                    <button class="btn-cancel">Cancel</button>
                    <button class="btn-confirm">Delete</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        console.log('Modal added to DOM, body children:', document.body.children.length);

        const cancelBtn = modal.querySelector('.btn-cancel');
        const confirmBtn = modal.querySelector('.btn-confirm');

        function closeModal() {
            modal.style.animation = 'fadeOut 0.2s ease-out';
            setTimeout(() => modal.remove(), 200);
        }

        cancelBtn.addEventListener('click', function() {
            console.log('Cancel clicked');
            closeModal();
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                console.log('Backdrop clicked');
                closeModal();
            }
        });

        confirmBtn.addEventListener('click', function() {
            console.log('Confirm clicked');
            closeModal();
            onConfirm();
        });

        // Focus on cancel button by default
        setTimeout(() => cancelBtn.focus(), 100);

        // ESC key to close
        const escHandler = function(e) {
            if (e.key === 'Escape') {
                console.log('ESC pressed');
                closeModal();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    }

    // Check all checkbox functionality
    const checkAll = document.getElementById('check-all');
    const checkboxes = document.querySelectorAll('.cart-checkbox');

    console.log('Check-all element:', checkAll);
    console.log('Found checkboxes:', checkboxes.length);

    // Function to update the check-all checkbox state
    function updateCheckAllState() {
        if (!checkAll || checkboxes.length === 0) return;

        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        checkAll.checked = checkedCount === checkboxes.length;
        checkAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        console.log('Check-all state updated:', { checkedCount, total: checkboxes.length, checked: checkAll.checked });
    }

    // Handle check-all checkbox change
    if (checkAll) {
        console.log('Setting up check-all listener');

        checkAll.addEventListener('change', function() {
            console.log('Check-all changed, new state:', this.checked);
            const isChecked = this.checked;

            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });

            console.log('Updated all checkboxes to:', isChecked);
        });
    }

    // Handle individual checkbox changes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Individual checkbox changed');
            updateCheckAllState();
        });
    });

    // Initialize the check-all state on page load
    updateCheckAllState();

    // Download selected
    document.querySelectorAll('.nzb_multi_operations_download_cart').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Download button clicked');

            const selected = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            if (selected.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('Please select at least one item', 'error');
                } else {
                    alert('Please select at least one item');
                }
                return;
            }

            // Download all selected NZBs
            selected.forEach(guid => {
                window.open('/getnzb?id=' + guid, '_blank');
            });

            if (typeof showToast === 'function') {
                showToast('Downloading ' + selected.length + ' item(s)', 'success');
            }
        });
    });

    // Delete selected
    document.querySelectorAll('.nzb_multi_operations_cartdelete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Bulk delete button clicked');

            const selected = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            if (selected.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('Please select at least one item', 'error');
                } else {
                    alert('Please select at least one item');
                }
                return;
            }

            showConfirmation(
                `Are you sure you want to delete <strong>${selected.length}</strong> item(s) from your cart?`,
                function() {
                    console.log('Deletion confirmed, sending request...');

                    // Delete via AJAX
                    fetch('/cart/delete/' + selected.join(','), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(response => {
                        console.log('Delete response:', response);
                        if (response.ok) {
                            if (typeof showToast === 'function') {
                                showToast('Items deleted successfully', 'success');
                            }
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            if (typeof showToast === 'function') {
                                showToast('Failed to delete items', 'error');
                            } else {
                                alert('Failed to delete items');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (typeof showToast === 'function') {
                            showToast('Failed to delete items', 'error');
                        } else {
                            alert('Failed to delete items');
                        }
                    });
                }
            );
        });
    });

    // Individual delete confirmation
    const deleteLinks = document.querySelectorAll('.cart-delete-link');
    console.log('Found', deleteLinks.length, 'delete links');

    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            console.log('Delete link clicked');

            const releaseName = this.getAttribute('data-release-name');
            const deleteUrl = this.getAttribute('data-delete-url');

            console.log('Release name:', releaseName);
            console.log('Delete URL:', deleteUrl);

            showConfirmation(
                `Are you sure you want to remove <strong>${releaseName}</strong> from your cart?`,
                function() {
                    console.log('Navigating to:', deleteUrl);

                    if (typeof showToast === 'function') {
                        showToast('Removing item from cart...', 'info');
                    }

                    setTimeout(() => {
                        window.location.href = deleteUrl;
                    }, 500);
                }
            );
        });
    });

    console.log('All event listeners attached');
});
</script>
@endpush

