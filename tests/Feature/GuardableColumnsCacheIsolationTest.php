<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Eloquent caches each model's table columns statically; tests that build a smaller table than a later
 * test must not make that later test silently drop attributes.
 */
class GuardableColumnsCacheIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge();
        DB::reconnect();
    }

    #[Test]
    public function a_minimal_roles_table_caches_its_columns(): void
    {
        $this->createRolesTable(withInviteColumns: false);

        $role = Role::query()->create(['name' => 'User', 'guard_name' => 'web', 'rate_limit' => 60]);

        $this->assertSame('User', $role->fresh()?->name);
    }

    #[Test]
    #[Depends('a_minimal_roles_table_caches_its_columns')]
    public function a_later_full_roles_table_keeps_columns_missing_from_the_earlier_table(): void
    {
        $this->createRolesTable(withInviteColumns: true);

        $role = Role::query()->firstOrCreate(
            ['name' => 'User', 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => true, 'defaultinvites' => 3]
        );

        $stored = DB::table('roles')->where('id', $role->id)->first();

        $this->assertNotNull($stored);
        $this->assertSame(3, (int) $stored->defaultinvites);
        $this->assertSame(1, (int) $stored->isdefault);
    }

    private function createRolesTable(bool $withInviteColumns): void
    {
        Schema::create('roles', function (Blueprint $table) use ($withInviteColumns): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->integer('rate_limit')->default(60);
            if ($withInviteColumns) {
                $table->boolean('isdefault')->default(false);
                $table->unsignedInteger('defaultinvites')->default(0);
            }
            $table->timestamps();
        });
    }
}
