<div class="release-bulk-actions flex flex-wrap items-center gap-2" role="group" aria-label="Actions for selected releases">
    <span class="text-xs font-medium text-gray-600 dark:text-gray-400">With selected</span>
    <button type="button" class="nzb_multi_operations_download release-bulk-actions__button bg-green-600 text-white hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800" title="Download selected NZBs">
        <i class="fa fa-cloud-download" aria-hidden="true"></i>
        <span>Download</span>
    </button>
    <button type="button" class="nzb_multi_operations_cart release-bulk-actions__button bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-800" title="Send selected releases to Download Basket">
        <i class="fa fa-shopping-basket" aria-hidden="true"></i>
        <span>Add to basket</span>
    </button>
    @if(auth()->check() && auth()->user()->hasRole('Admin'))
        <button type="button" class="nzb_multi_operations_delete release-bulk-actions__button bg-red-600 text-white hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800" title="Delete selected releases">
            <i class="fa fa-trash" aria-hidden="true"></i>
            <span>Delete</span>
        </button>
    @endif
</div>
