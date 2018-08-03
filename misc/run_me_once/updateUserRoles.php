<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$users = User::all();

$oldRoles = DB::table('user_roles')->get()->toArray();

$roles = array_pluck(Role::query()->get(['name'])->toArray(), 'name');

$permissions = array_pluck(Permission::query()->select('name')->get()->toArray(), 'name');

$neededPerms = ['preview', 'hideads', 'edit release'];

foreach ($neededPerms as $neededPerm) {
    if (! in_array($neededPerm, $permissions, false)) {
        Permission::create(['name' => $neededPerm]);
    }
}

foreach ($oldRoles as $oldRole) {
    if (! in_array($oldRole->name, $roles, false)) {
        $role = Role::create(
            [
                'name' => $oldRole->name,
                'apirequests' => $oldRole->apirequests,
                'downloadrequests' => $oldRole->downloadrequests,
                'defaultinvites' => $oldRole->defaultinvites,
                'donation' => $oldRole->donation,
                'addyears' => $oldRole->addyears,
                'rate_limit' => $oldRole->rate_limit,
            ]
        );

        if ((int) $oldRole->canpreview === 1) {
            $role->givePermissionTo('preview');
        }

        if ((int) $oldRole->hideads === 1) {
            $role->givePermissionTo('hideads');
        }

        if ($oldRole->name === 'Moderator') {
            $role->givePermissionTo('edit release');
        }
    }
}

foreach ($users as $user) {
    if ($user->role !== null && $user->hasRole($user->role->name) === false) {
        $user->assignRole($user->role->name);
        echo 'Role: '.$user->role->name.' assigned to user: '.$user->username.PHP_EOL;
    } elseif ($user->role === null) {
        $user->assignRole('User');
    } else {
        echo 'User '.$user->username.' already has the role: '.$user->role->name.PHP_EOL;
    }
}
