<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Deleted Users Management</h4>
        </div>
    </div>

    <div class="card-body">

{if isset($smarty.session.success)}
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {$smarty.session.success}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
{/if}
{if isset($smarty.session.error)}
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {$smarty.session.error}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
{/if}

<form name="deletedusersearch" method="get" action="{{url("/admin/deleted-users")}}" id="deleted-user-search-form" class="mb-4">
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="username" class="form-label">Username:</label>
                    <input id="username" type="text" name="username" value="{$username}" class="form-control" placeholder="Username">
                </div>
                <div class="col-md-3">
                    <label for="email" class="form-label">Email:</label>
                    <input id="email" type="text" name="email" value="{$email}" class="form-control" placeholder="Email">
                </div>
                <div class="col-md-3">
                    <label for="host" class="form-label">Host:</label>
                    <input id="host" type="text" name="host" value="{$host}" class="form-control" placeholder="Host">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Created From:</label>
                    <input type="date" name="created_from" value="{$created_from}" class="form-control" placeholder="YYYY-MM-DD">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Created To:</label>
                    <input type="date" name="created_to" value="{$created_to}" class="form-control" placeholder="YYYY-MM-DD">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Deleted From:</label>
                    <input type="date" name="deleted_from" value="{$deleted_from}" class="form-control" placeholder="YYYY-MM-DD">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Deleted To:</label>
                    <input type="date" name="deleted_to" value="{$deleted_to}" class="form-control" placeholder="YYYY-MM-DD">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <input type="submit" value="Search" class="btn btn-success">
            <a href="{{url("/admin/deleted-users")}}" class="btn btn-secondary">Reset</a>
        </div>
    </div>
</form>

{if $deletedusers|@count > 0}
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h3 class="card-title mb-0">Soft-Deleted Users</h3>
                <form id="bulk-action-form" method="post" action="{{url('/admin/deleted-users/bulk')}}" class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="hidden" name="_token" value="{$csrf_token}">
                    <div class="input-group input-group-sm">
                        <label class="input-group-text" for="bulk-action-select">Bulk Action</label>
                        <select name="action" id="bulk-action-select" class="form-select form-select-sm">
                            <option value="" selected disabled>Choose...</option>
                            <option value="restore">Restore Selected</option>
                            <option value="delete">Permanently Delete Selected</option>
                        </select>
                        <button type="submit" class="btn btn-primary" id="bulk-action-submit" disabled>Apply</button>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Selection controls">
                        <!-- Improved selection buttons -->
                        <div class="btn-group btn-group-sm selection-toolbar" role="group" aria-label="Selection controls">
                            <button type="button" class="btn btn-outline-primary" id="select-all" data-bs-toggle="tooltip" data-bs-title="Select every row">
                                <i class="fas fa-check-double me-1"></i><span class="d-none d-sm-inline">Select All</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="deselect-all" data-bs-toggle="tooltip" data-bs-title="Clear selection">
                                <i class="fas fa-ban me-1"></i><span class="d-none d-sm-inline">Clear</span>
                            </button>
                            <button type="button" class="btn btn-outline-warning" id="invert-selection" data-bs-toggle="tooltip" data-bs-title="Invert current selection">
                                <i class="fas fa-exchange-alt me-1"></i><span class="d-none d-sm-inline">Invert</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data table table-striped table-hover align-middle" id="deleted-users-table">
                        <thead>
                            <tr>
                                <th style="width:32px;" class="text-center"><input type="checkbox" id="master-checkbox" title="Select/Deselect All"></th>
                                <th>Username
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=username_asc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-up"></i></a>
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=username_desc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-down"></i></a>
                                </th>
                                <th>Email
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=email_asc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-up"></i></a>
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=email_desc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-down"></i></a>
                                </th>
                                <th>Host
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=host_asc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-up"></i></a>
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=host_desc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-down"></i></a>
                                </th>
                                <th>Created Date
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=createdat_asc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-up"></i></a>
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=createdat_desc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-down"></i></a>
                                </th>
                                <th>Deleted Date
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=deletedat_asc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-up"></i></a>
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=deletedat_desc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-down"></i></a>
                                </th>
                                <th>Last Login
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=lastlogin_asc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-up"></i></a>
                                    <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=lastlogin_desc{if $queryString != ''}&{$queryString}{/if}"><i class="fas fa-sort-alpha-down"></i></a>
                                </th>
                                <th>Options</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$deletedusers item=user}
                                <tr>
                                    <td class="text-center"><input type="checkbox" class="row-checkbox" name="user_ids[]" form="bulk-action-form" value="{$user->id}" aria-label="Select {$user->username}"></td>
                                    <td>{$user->username}</td>
                                    <td>{$user->email}</td>
                                    <td>{$user->host}</td>
                                    <td>{$user->created_at|date_format:"%Y-%m-%d %H:%M"}</td>
                                    <td>{$user->deleted_at|date_format:"%Y-%m-%d %H:%M"}</td>
                                    <td>{if $user->lastlogin != ""}{$user->lastlogin|date_format:"%Y-%m-%d %H:%M"}{else}Never{/if}</td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-success btn-sm me-2 restore-user"
                                            data-user-id="{$user->id}"
                                            data-username="{$user->username}"
                                            title="Restore User">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm delete-permanently"
                                            data-user-id="{$user->id}"
                                            data-username="{$user->username}"
                                            title="Permanently Delete User">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div><strong>Total:</strong> {$deletedusers->total()} user(s)</div>
                    <div>{if $deletedusers->hasPages()}{$deletedusers->links()}{/if}</div>
                </div>
            </div>
        </div>
    </div>
</div>
{else}
<div class="alert alert-info">No soft-deleted users found.</div>
{/if}

    </div> <!-- end of card-body -->
</div> <!-- end of card -->

<!-- Delete User Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteUserModalLabel">Confirm Permanent Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Warning: This action cannot be undone!
                </div>
                <p>You are about to permanently delete user <strong id="deleteUserName"></strong>.</p>
                <p>All user data including history, comments, and settings will be permanently removed from the database.</p>
                <p>Are you sure you want to continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Permanently Delete
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Restore User Confirmation Modal -->
<div class="modal fade" id="restoreUserModal" tabindex="-1" aria-labelledby="restoreUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="restoreUserModalLabel">Confirm User Restoration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Please confirm this action
                </div>
                <p>You are about to restore user <strong id="restoreUserName"></strong>.</p>
                <p>This will reactivate the user account and grant them access to the system again.</p>
                <p>Are you sure you want to continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmRestoreBtn" class="btn btn-success">
                    <i class="fas fa-user-check"></i> Restore User
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Modal logic ---
    const deleteUserModalEl = document.getElementById('deleteUserModal');
    const restoreUserModalEl = document.getElementById('restoreUserModal');
    const deleteUserModal = deleteUserModalEl ? new bootstrap.Modal(deleteUserModalEl) : null;
    const restoreUserModal = restoreUserModalEl ? new bootstrap.Modal(restoreUserModalEl) : null;

    // Delete permanently buttons
    document.querySelectorAll('.delete-permanently').forEach(btn => {
        btn.addEventListener('click', () => {
            const userId = btn.getAttribute('data-user-id');
            const username = btn.getAttribute('data-username');

            document.getElementById('deleteUserName').textContent = username;
            document.getElementById('confirmDeleteBtn').href = '{$smarty.const.WWW_TOP}/admin/deleted-users/permanent-delete/' + userId;
            if (deleteUserModal) deleteUserModal.show();
        });
    });

    // Restore user buttons
    document.querySelectorAll('.restore-user').forEach(btn => {
        btn.addEventListener('click', () => {
            const userId = btn.getAttribute('data-user-id');
            const username = btn.getAttribute('data-username');

            document.getElementById('restoreUserName').textContent = username;
            document.getElementById('confirmRestoreBtn').href = '{$smarty.const.WWW_TOP}/admin/deleted-users/restore/' + userId;
            if (restoreUserModal) restoreUserModal.show();
        });
    });

    // --- Bulk selection logic ---
    const bulkForm = document.getElementById('bulk-action-form');
    const bulkSelect = document.getElementById('bulk-action-select');
    const bulkSubmit = document.getElementById('bulk-action-submit');
    const masterCheckbox = document.getElementById('master-checkbox');
    const selectAllBtn = document.getElementById('select-all');
    const deselectAllBtn = document.getElementById('deselect-all');
    const invertSelectionBtn = document.getElementById('invert-selection');

    function getRowCheckboxes() {
        return Array.from(document.querySelectorAll('.row-checkbox'));
    }

    function updateBulkSubmitState() {
        if (!bulkSubmit || !bulkSelect) return;
        const anyChecked = getRowCheckboxes().some(cb => cb.checked);
        bulkSubmit.disabled = !anyChecked || !bulkSelect.value;
    }

    function updateMasterCheckbox() {
        if (!masterCheckbox) return;
        const checkboxes = getRowCheckboxes();
        if (checkboxes.length === 0) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
            return;
        }

        const checkedCount = checkboxes.filter(cb => cb.checked).length;
        if (checkedCount === 0) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
        } else if (checkedCount === checkboxes.length) {
            masterCheckbox.checked = true;
            masterCheckbox.indeterminate = false;
        } else {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = true;
        }
    }

    // Master checkbox click handler
    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function() {
            const checkboxes = getRowCheckboxes();
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkSubmitState();
        });
    }

    // Individual checkbox change handler
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('row-checkbox')) {
            updateMasterCheckbox();
            updateBulkSubmitState();
        }
    });

    // Select All button
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const checkboxes = getRowCheckboxes();
            checkboxes.forEach(cb => cb.checked = true);
            updateMasterCheckbox();
            updateBulkSubmitState();
        });
    }

    // Deselect All button
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
            const checkboxes = getRowCheckboxes();
            checkboxes.forEach(cb => cb.checked = false);
            updateMasterCheckbox();
            updateBulkSubmitState();
        });
    }

    // Invert Selection button
    if (invertSelectionBtn) {
        invertSelectionBtn.addEventListener('click', function() {
            const checkboxes = getRowCheckboxes();
            checkboxes.forEach(cb => cb.checked = !cb.checked);
            updateMasterCheckbox();
            updateBulkSubmitState();
        });
    }

    // Bulk action select change handler
    if (bulkSelect) {
        bulkSelect.addEventListener('change', updateBulkSubmitState);
    }

    // Bulk form submission handler
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            const action = bulkSelect ? bulkSelect.value : '';
            if (!action) {
                e.preventDefault();
                return;
            }

            const selectedCheckboxes = getRowCheckboxes().filter(cb => cb.checked);
            if (selectedCheckboxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one user.');
                return;
            }

            const verb = action === 'restore' ? 'restore' : 'permanently delete';
            const confirmation = confirm('Are you sure you want to ' + verb + ' ' + selectedCheckboxes.length + ' selected user(s)?');
            if (!confirmation) {
                e.preventDefault();
            }
        });
    }

    // Initialize states
    updateMasterCheckbox();
    updateBulkSubmitState();
});
</script>

<style>
    .selection-toolbar .btn {
        --bs-btn-padding-y: .35rem;
        --bs-btn-padding-x: .7rem;
        --bs-btn-font-size: .75rem;
        transition: background-color .15s ease, color .15s ease, box-shadow .15s ease;
    }
    .selection-toolbar .btn i { font-size: .9rem; }
    .selection-toolbar .btn-outline-primary:hover   { background:#0d6efd; color:#fff; }
    .selection-toolbar .btn-outline-secondary:hover { background:#6c757d; color:#fff; }
    .selection-toolbar .btn-outline-warning:hover   { background:#ffc107; color:#212529; }
    .selection-toolbar .btn:focus { box-shadow: 0 0 0 .15rem rgba(13,110,253,.25); }
    @media (max-width: 575.98px){
        .selection-toolbar .btn span { display:none; }
        .selection-toolbar .btn { --bs-btn-padding-x: .55rem; }
    }
</style>
