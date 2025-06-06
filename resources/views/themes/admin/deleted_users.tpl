<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Deleted Users Management</h4>
        </div>
    </div>

    <div class="card-body">

{if isset($smarty.session.success)}
    <div class="alert alert-success">{$smarty.session.success}</div>
{/if}
{if isset($smarty.session.error)}
    <div class="alert alert-error">{$smarty.session.error}</div>
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
                                            <a href="{$smarty.const.WWW_TOP}/admin/deleted-users/restore/{$user->id}" class="btn btn-success btn-sm me-2" title="Restore User">
                                                <i class="fas fa-user-check"></i> Restore
                                            </a>
                                            <a href="{$smarty.const.WWW_TOP}/admin/deleted-users/permanent-delete/{$user->id}" class="btn btn-danger btn-sm" title="Permanently Delete User" onclick="return confirm('Are you sure you want to permanently delete this user? This action cannot be undone.');">
                                                <i class="fas fa-trash-alt"></i> Delete Permanently
                                            </a>
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
