<div class="release-bulk-actions" role="group" aria-label="Actions for selected releases">
    <span class="release-bulk-actions__label">With selected</span>
    <button type="button" class="nzb_multi_operations_download release-bulk-actions__button release-bulk-actions__download" title="Download selected NZBs">
        <i class="fa fa-cloud-download" aria-hidden="true"></i>
        <span>Download</span>
    </button>
    <button type="button" class="nzb_multi_operations_cart release-bulk-actions__button release-bulk-actions__basket" title="Send selected releases to Download Basket">
        <i class="fa fa-shopping-basket" aria-hidden="true"></i>
        <span>Add to basket</span>
    </button>
    @if(auth()->check() && auth()->user()->hasRole('Admin'))
        <button type="button" class="nzb_multi_operations_delete release-bulk-actions__button release-bulk-actions__delete" title="Delete selected releases">
            <i class="fa fa-trash" aria-hidden="true"></i>
            <span>Delete</span>
        </button>
    @endif
</div>
