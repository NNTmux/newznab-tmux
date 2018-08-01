<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$users = User::all();

$oldRoles = \Illuminate\Support\Facades\DB::table('user_roles')->get()->toArray();

$roles = array_pluck(Role::query()->get(['name'])->toArray(), 'name');

foreach ($oldRoles as $oldRole) {
    if (! in_array($oldRole->name, $roles, false)) {
        Role::create(['name' => $oldRole->name,
            'apirequests' => $oldRole->apirequests,
            'downloadrequests' => $oldRole->downloadrequests,
            'defaultinvites' => $oldRole->defaultinvites,
            'canpreview' => $oldRole->canpreview,
            'hideads' => $oldRole->hideads,
            'donation' => $oldRole->donation,
            'addyears' => $oldRole->addyears,
            'rate_limit' => $oldRole->rate_limit,
            ]);
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
