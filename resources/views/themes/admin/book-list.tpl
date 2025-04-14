<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{$title}</h4>
        </div>
    </div>

    <div class="card-body">
        <form name="booksearch" method="get" action="{{url("/admin/book-list")}}" id="book-search-form" class="mb-4">
            {{csrf_field()}}
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search by title, author, or ASIN"
                               id="booksearch" name="booksearch" value="{$lastSearch|escape:'html'}">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search me-2"></i>Search
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {if $booklist}
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Publisher</th>
                            <th class="text-center">Cover</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$booklist item=book}
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">{$book.id}</span>
                                </td>
                                <td>
                                    <div class="mb-1">
                                        <a href="{{url("/admin/book-edit?id={$book.id}")}}" class="title fw-semibold">
                                            {$book.title|escape:'htmlall'}
                                        </a>
                                    </div>
                                    {if $book.asin}
                                        <div class="small text-muted">
                                            <i class="fa fa-barcode me-1"></i>ASIN: {$book.asin}
                                        </div>
                                    {/if}
                                </td>
                                <td>{$book.author|escape:'htmlall'}</td>
                                <td>{$book.publisher|escape:'htmlall'}</td>
                                <td class="text-center">
                                    {if $book.cover == 1}
                                        <span class="badge bg-success"><i class="fa fa-check me-1"></i>Yes</span>
                                    {else}
                                        <span class="badge bg-danger"><i class="fa fa-times me-1"></i>No</span>
                                    {/if}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-calendar text-muted me-2"></i>
                                        <span title="{$book.created_at}">{$book.created_at|date_format}</span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{url("/admin/book-edit?id={$book.id}")}}" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit this book">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <a href="{{url("/admin/book-delete?id={$book.id}")}}" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete book" onclick="return confirm('Are you sure you want to delete this book?')">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="alert alert-info">
                <i class="fa fa-info-circle me-2"></i>No books available. Use the Add New Book button to add some.
            </div>
        {/if}
    </div>

    {if $booklist && $booklist->count() > 0}
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    Showing {$booklist->firstItem()} to {$booklist->lastItem()} of {$booklist->total()} books
                </div>
                <div class="pagination-container overflow-auto">
                    {$booklist->onEachSide(5)->links()}
                </div>
            </div>
        </div>
    {/if}
</div>

<script>
{literal}
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
{/literal}
</script>

<style>
{literal}
/* Improve table responsiveness */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Badge styling */
.badge {
    font-weight: 500;
    padding: 0.4em 0.6em;
}

/* Pagination container */
.pagination-container {
    max-width: 100%;
}

/* Improve action buttons on small screens */
@media (max-width: 767.98px) {
    .btn-group .btn {
        padding: 0.375rem 0.5rem;
    }

    .card-footer .d-flex {
        flex-direction: column;
        gap: 0.5rem;
    }

    .pagination-container {
        justify-content: center !important;
    }
}

/* Improve search form on small screens */
@media (max-width: 767.98px) {
    #book-search-form .row {
        margin-right: 0;
        margin-left: 0;
    }

    #book-search-form .col-md-6 {
        padding-right: 0;
        padding-left: 0;
    }
}
{/literal}
</style>
