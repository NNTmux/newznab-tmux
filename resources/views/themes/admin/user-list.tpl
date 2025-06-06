<div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">{$title}</h4>
                <a href="{{url("/admin/user-edit?action=add")}}" class="btn btn-outline-success">
                    <i class="fa fa-plus me-2"></i>Add New User
                </a>
            </div>
        </div>

        <div class="card-body">
            <form name="usersearch" method="get" action="{{url("/admin/user-list")}}" id="user-search-form" class="mb-4">
                {{csrf_field()}}
                <div class="row">
                    <div class="col-md-12">
                        <div class="row g-3">
                            <div class="col-md-3 col-sm-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input id="username" type="text" name="username" value="{$username}" class="form-control" placeholder="Search by username">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input id="email" type="text" name="email" value="{$email}" class="form-control" placeholder="Search by email">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="mb-3">
                                    <label for="host" class="form-label">Host</label>
                                    <input id="host" type="text" name="host" value="{$host}" class="form-control" placeholder="Search by IP/host">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select id="role" name="role" class="form-select">
                                        <option value="">-- any --</option>
                                        {html_options values=$role_ids output=$role_names selected=$role}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search me-2"></i>Search Users
                        </button>
                    </div>
                </div>
            </form>

            {if $userlist->count() > 0}
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>
                                    <div class="d-flex align-items-center gap-2">
                                        <span>Username</span>
                                        <div class="sort-controls">
                                            <a href="{$orderbyusername_asc}" class="sort-icon {if isset($orderby) && $orderby == 'username_asc'}active{/if}" title="Sort Ascending">
                                                <i class="fas fa-sort-alpha-down"></i>
                                            </a>
                                            <a href="{$orderbyusername_desc}" class="sort-icon {if isset($orderby) && $orderby == 'username_desc'}active{/if}" title="Sort Descending">
                                                <i class="fas fa-sort-alpha-down-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </th>
                                <th>
                                    <div class="d-flex align-items-center gap-2">
                                        <span>Email</span>
                                        <div class="sort-controls">
                                            <a href="{$orderbyemail_asc}" class="sort-icon {if isset($orderby) && $orderby == 'email_asc'}active{/if}" title="Sort Ascending">
                                                <i class="fas fa-sort-alpha-down"></i>
                                            </a>
                                            <a href="{$orderbyemail_desc}" class="sort-icon {if isset($orderby) && $orderby == 'email_desc'}active{/if}" title="Sort Descending">
                                                <i class="fas fa-sort-alpha-down-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </th>
                                <th>
                                    <div class="d-flex align-items-center gap-2">
                                        <span>Role</span>
                                        <div class="sort-controls">
                                            <a href="{$orderbyrole_asc}" class="sort-icon {if isset($orderby) && $orderby == 'role_asc'}active{/if}" title="Sort Ascending">
                                                <i class="fas fa-sort-alpha-down"></i>
                                            </a>
                                            <a href="{$orderbyrole_desc}" class="sort-icon {if isset($orderby) && $orderby == 'role_desc'}active{/if}" title="Sort Descending">
                                                <i class="fas fa-sort-alpha-down-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </th>
                                <th>
                                    <div class="d-flex align-items-center gap-2">
                                        <span>Join Date</span>
                                        <div class="sort-controls">
                                            <a href="{$orderbycreatedat_asc}" class="sort-icon {if isset($orderby) && $orderby == 'createdat_asc'}active{/if}" title="Sort Oldest First">
                                                <i class="fas fa-sort-numeric-down"></i>
                                            </a>
                                            <a href="{$orderbycreatedat_desc}" class="sort-icon {if isset($orderby) && $orderby == 'createdat_desc'}active{/if}" title="Sort Newest First">
                                                <i class="fas fa-sort-numeric-down-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </th>
                                <th>
                                    <div class="d-flex align-items-center gap-2">
                                        <span>Last Login</span>
                                        <div class="sort-controls">
                                            <a href="{$orderbylastlogin_asc}" class="sort-icon {if isset($orderby) && $orderby == 'lastlogin_asc'}active{/if}" title="Sort Oldest First">
                                                <i class="fas fa-sort-numeric-down"></i>
                                            </a>
                                            <a href="{$orderbylastlogin_desc}" class="sort-icon {if isset($orderby) && $orderby == 'lastlogin_desc'}active{/if}" title="Sort Newest First">
                                                <i class="fas fa-sort-numeric-down-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </th>
                                <th>
                                    <div class="d-flex align-items-center gap-2">
                                        <span>Status</span>
                                    </div>
                                </th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $userlist as $user}
                                <tr>
                                    <td>
                                        <a href="{{url("/admin/user-edit?id={$user->id}")}}" class="fw-bold text-decoration-none">
                                            {$user->username}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fa fa-envelope text-muted me-2"></i>
                                            <a href="{{url("/profile?id={$user->id}")}}" class="text-decoration-none">{$user->email}</a>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class="fa fa-globe me-1"></i>{$user->host}
                                            {if $user->country_code}
                                                <span class="ms-2">
                                                    <img src="https://flagcdn.com/16x12/{$user->country_code|lower}.png" alt="{$user->country_name}" width="16" height="12" class="me-1">
                                                    <span>{$user->country_name}</span>
                                                </span>
                                            {/if}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-{if $user->roles_id == 2}danger{elseif $user->roles_id == 3}warning{elseif $user->roles_id == 4}success{else}secondary{/if} rounded-pill">
                                            {$user->rolename}
                                        </span>
                                        {if !empty($user->rolechangedate)}
                                            <small class="d-block text-muted mt-1" title="Role expiration date">
                                                <i class="fa fa-calendar me-1"></i>{$user->rolechangedate}
                                            </small>
                                        {/if}
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fa fa-calendar-plus-o text-muted me-2"></i>
                                            <span title="{$user->created_at}">{$user->created_at}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fa fa-clock-o text-muted me-2"></i>
                                            <span title="{$user->lastlogin}">{$user->lastlogin}</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="mb-2">
                                            {if $user->verified == 1}
                                                <span class="badge bg-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Email verified"><i class="fa fa-check me-1"></i>Verified</span>
                                            {else}
                                                <span class="badge bg-warning text-dark" data-bs-toggle="tooltip" data-bs-placement="top" title="Email not verified"><i class="fa fa-exclamation-triangle me-1"></i>Unverified</span>
                                            {/if}
                                        </div>
                                        <div>
                                            {if $user->apiaccess}
                                                <span class="badge bg-info" data-bs-toggle="tooltip" data-bs-placement="top" title="{$user->apiaccess}"><i class="fa fa-key me-1"></i>API Access</span>
                                            {/if}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="badge {if isset($user->deleted_at)}bg-danger{else}bg-success{/if} rounded-pill">
                                                {if isset($user->deleted_at)}
                                                    <i class="fa fa-trash me-1"></i>Soft-Deleted
                                                {else}
                                                    <i class="fa fa-check me-1"></i>Active
                                                {/if}
                                            </span>
                                        </div>
                                        {if isset($user->deleted_at)}
                                            <small class="d-block text-muted mt-1" title="Deletion date">
                                                <i class="fa fa-calendar me-1"></i>{$user->deleted_at}
                                            </small>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column gap-2">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Grabs:</span>
                                                <span class="fw-bold">{$user->grabs}</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">API Req:</span>
                                                <span class="fw-bold">{$user->apirequests}</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Invites:</span>
                                                <span class="fw-bold">{$user->invites}</span>
                                            </div>
                                            <div>
                                                <a href="{{url("/admin/user-edit?id={$user->id}#notes")}}" class="badge bg-{if $user->notes|count_characters > 0}warning{else}secondary{/if}" data-bs-toggle="tooltip" data-bs-placement="top" title="{if $user->notes|count_characters > 0}View{else}Add{/if} Notes">
                                                    <i class="fa {if $user->notes|count_characters > 0}fa-sticky-note{else}fa-sticky-note-o{/if} me-1"></i>Notes
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="{{url("/admin/user-edit?id={$user->id}")}}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit user">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            {if $user->verified != 1}
                                                <a href="{{url("/admin/verify?id={$user->id}")}}" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Verify user">
                                                    <i class="fa fa-check-circle"></i>
                                                </a>
                                                <a href="{{url("/admin/resendverification?id={$user->id}")}}" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Resend verification email">
                                                    <i class="fa fa-envelope"></i>
                                                </a>
                                            {/if}
                                            {if $user->roles_id != "2"}
                                                <a href="{{url("/admin/user-delete?id={$user->id}")}}" class="btn btn-sm btn-outline-danger custom_delete_action" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete user">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            {/if}
                                        </div>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            {else}
                <div class="alert alert-info">
                    <i class="fa fa-info-circle me-2"></i>No users found matching your criteria.
                </div>
            {/if}
        </div>

        {if $userlist->count() > 0}
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">Showing {$userlist->firstItem()} to {$userlist->lastItem()} of {$userlist->total()} users</span>
                    </div>
                    <div class="pagination-container overflow-auto">
                        {$userlist->onEachSide(5)->links()}
                    </div>
                </div>
            </div>
        {/if}
    </div>

    <!-- Delete User Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteUserModalLabel">
                    <i class="fa fa-exclamation-triangle me-2"></i>Delete User Confirmation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold mb-2">You are about to delete the following user:</p>
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fa fa-user me-2 fs-5"></i>
                    <span id="deleteUserName" class="fw-bold fs-5"></span>
                </div>
                <p>This action will soft-delete the user account. The user will no longer be able to log in, but their data will remain in the database.</p>
                <p class="text-danger fw-bold">Are you absolutely sure you want to proceed?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fa fa-trash me-1"></i>Delete User
                </a>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Enhanced delete confirmation
    document.querySelectorAll('.custom_delete_action').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();

            // Get the user details
            const row = this.closest('tr');
            const username = row.querySelector('td:first-child a').textContent.trim();
            const deleteUrl = this.getAttribute('href');

            // Set the modal content
            document.getElementById('deleteUserName').textContent = username;
            document.getElementById('confirmDeleteBtn').setAttribute('href', deleteUrl);

            // Show the modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            deleteModal.show();
        });
    });

    // Toast notification system
    function showToast(message, type) {
        const toastContainer = document.getElementById('toast-container');

        const toastElement = document.createElement('div');
        toastElement.className = `toast align-items-center text-white bg-${type} border-0`;
        toastElement.setAttribute('role', 'alert');
        toastElement.setAttribute('aria-live', 'assertive');
        toastElement.setAttribute('aria-atomic', 'true');

        const toastFlex = document.createElement('div');
        toastFlex.className = 'd-flex';

        const toastBody = document.createElement('div');
        toastBody.className = 'toast-body';
        toastBody.innerHTML = message;

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close btn-close-white me-2 m-auto';
        closeButton.setAttribute('data-bs-dismiss', 'toast');
        closeButton.setAttribute('aria-label', 'Close');

        toastFlex.appendChild(toastBody);
        toastFlex.appendChild(closeButton);
        toastElement.appendChild(toastFlex);
        toastContainer.appendChild(toastElement);

        const toast = new bootstrap.Toast(toastElement, {
            delay: 5000
        });
        toast.show();

        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }

    // If there's a success parameter in URL (will be added by our controller)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('deleted')) {
        const username = urlParams.get('username');
        showToast(`User "${username}" has been successfully deleted.`, 'success');
    }
});
{/literal}
</script>

<!-- Toast container for notifications -->
<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<style>
{literal}
/* Sort controls styling */
.sort-controls {
    display: flex;
    flex-direction: column;
    font-size: 0.8rem;
    margin-left: 0.3rem;
}

.sort-icon {
    color: #6c757d;
}

/* Toast styling */
.toast {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
{/literal}
</style>
