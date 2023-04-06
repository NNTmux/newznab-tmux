<?php

use Spatie\Permission\Models\Permission;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

// Reset cached roles and permissions
app('cache')->forget('spatie.permission.cache');
Permission::create(['name' => 'preview', 'guard_name' => 'rss']);
Permission::create(['name' => 'hideads', 'guard_name' => 'rss']);
Permission::create(['name' => 'edit release', 'guard_name' => 'rss']);
Permission::create(['name' => 'view console', 'guard_name' => 'rss']);
Permission::create(['name' => 'view movies', 'guard_name' => 'rss']);
Permission::create(['name' => 'view audio', 'guard_name' => 'rss']);
Permission::create(['name' => 'view pc', 'guard_name' => 'rss']);
Permission::create(['name' => 'view tv', 'guard_name' => 'rss']);
Permission::create(['name' => 'view adult', 'guard_name' => 'rss']);
Permission::create(['name' => 'view books', 'guard_name' => 'rss']);
Permission::create(['name' => 'view other', 'guard_name' => 'rss']);
