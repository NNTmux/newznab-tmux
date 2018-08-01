<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$users = User::all();

$oldRoles = DB::table('user_roles')->get()->toArray();

$roles = array_pluck(Role::query()->get(['name'])->toArray(), 'name');

Permission::create(['name' => 'preview']);
Permission::create(['name' => 'hideads']);

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
    }
}

foreach ($users as $user) {
    if ($user->hasRole($user->role->name) === false) {
        $user->assignRole($user->role->name);
        echo 'Role: '.$user->role->name.' assigned to user: '.$user->username.PHP_EOL;
    } else {
        echo 'User '.$user->username.' already has the role: '.$user->role->name.PHP_EOL;
    }
}
