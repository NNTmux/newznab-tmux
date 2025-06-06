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
            <div class="row">
                <div class="col-md-4">
                    <label for="username">Username:</label>
                    <input id="username" type="text" name="username" value="{$username}" class="form-control" placeholder="Username">
                </div>
                <div class="col-md-4">
                    <label for="email">Email:</label>
                    <input id="email" type="text" name="email" value="{$email}" class="form-control" placeholder="Email">
                </div>
                <div class="col-md-4">
                    <label for="host">Host:</label>
                    <input id="host" type="text" name="host" value="{$host}" class="form-control" placeholder="Host">
                </div>
            </div>
        </div>
        <div class="card-footer">
            <input type="submit" value="Search" class="btn btn-success">
        </div>
    </div>
</form>

{if $deletedusers|@count > 0}
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Soft-Deleted Users</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <table class="data table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Username
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=username_asc"><i class="fas fa-sort-alpha-up"></i></a>
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=username_desc"><i class="fas fa-sort-alpha-down"></i></a>
                                    </th>
                                    <th>Email
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=email_asc"><i class="fas fa-sort-alpha-up"></i></a>
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=email_desc"><i class="fas fa-sort-alpha-down"></i></a>
                                    </th>
                                    <th>Host
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=host_asc"><i class="fas fa-sort-alpha-up"></i></a>
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=host_desc"><i class="fas fa-sort-alpha-down"></i></a>
                                    </th>
                                    <th>Created Date
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=createdat_asc"><i class="fas fa-sort-alpha-up"></i></a>
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=createdat_desc"><i class="fas fa-sort-alpha-down"></i></a>
                                    </th>
                                    <th>Deleted Date
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=deletedat_asc"><i class="fas fa-sort-alpha-up"></i></a>
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=deletedat_desc"><i class="fas fa-sort-alpha-down"></i></a>
                                    </th>
                                    <th>Last Login
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=lastlogin_asc"><i class="fas fa-sort-alpha-up"></i></a>
                                        <a href="{$smarty.const.WWW_TOP}/admin/deleted-users?ob=lastlogin_desc"><i class="fas fa-sort-alpha-down"></i></a>
                                    </th>
                                    <th>Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$deletedusers item=user}
                                    <tr>
                                        <td>{$user->username}</td>
                                        <td>{$user->email}</td>
                                        <td>{$user->host}</td>
                                        <td>{$user->created_at|date_format:"%Y-%m-%d %H:%M"}</td>
                                        <td>{$user->deleted_at|date_format:"%Y-%m-%d %H:%M"}</td>
                                        <td>{if $user->lastlogin != ""}{$user->lastlogin|date_format:"%Y-%m-%d %H:%M"}{else}Never{/if}</td>
                                        <td>
                                            <button class="btn btn-success btn-sm me-2 restore-user"
                                                data-user-id="{$user->id}"
                                                data-username="{$user->username}"
                                                title="Restore User">
                                                <i class="fas fa-user-check"></i> Restore
                                            </button>
                                            <button class="btn btn-danger btn-sm delete-permanently"
                                                data-user-id="{$user->id}"
                                                data-username="{$user->username}"
                                                title="Permanently Delete User">
                                                <i class="fas fa-trash-alt"></i> Delete Permanently
                                            </button>
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{if $deletedusers->hasPages()}
<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-center">
            {$deletedusers->links()}
        </div>
    </div>
</div>
{/if}

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
    // Set up delete confirmation modal
    const deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    const deleteButtons = document.querySelectorAll('.delete-permanently');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');

            document.getElementById('deleteUserName').textContent = username;
            document.getElementById('confirmDeleteBtn').href = '{$smarty.const.WWW_TOP}/admin/deleted-users/permanent-delete/' + userId;

            deleteUserModal.show();
        });
    });

    // Set up restore confirmation modal
    const restoreUserModal = new bootstrap.Modal(document.getElementById('restoreUserModal'));
    const restoreButtons = document.querySelectorAll('.restore-user');

    restoreButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');

            document.getElementById('restoreUserName').textContent = username;
            document.getElementById('confirmRestoreBtn').href = '{$smarty.const.WWW_TOP}/admin/deleted-users/restore/' + userId;

            restoreUserModal.show();
        });
    });
});
</script>
